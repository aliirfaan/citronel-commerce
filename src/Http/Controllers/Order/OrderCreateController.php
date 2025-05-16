<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\CitronelCommerce\Models\Order\OrderItem;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Product\CitronelProductService;
use aliirfaan\CitronelCommerce\Services\Currency\CitronelCurrencyService;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentMethodService;
use aliirfaan\CitronelCommerce\Events\Order\OrderCreated;
use aliirfaan\CitronelCommerce\Services\Order\CitronelFulfillmentService;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentService;
use aliirfaan\CitronelCommerce\Jobs\Order\CreateOrderFulfillment;
use aliirfaan\CitronelCommerce\Jobs\Order\FulfillItem;
use aliirfaan\CitronelCommerce\Enums\Order\OrderStatus;

class OrderCreateController extends OrderController
{
    public function create(Request $request, AuditLogService $auditService, OrderItem $orderItemApi, CitronelProductService $productService, CitronelCurrencyService $currencyService, CitronelOrderService $orderService, CitronelPaymentMethodService $paymentMethodService, CitronelFulfillmentService $fulfillmentService, CitronelPaymentService $paymentService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $this->subProcess = $this->errorCatalogueService->getSubProcess($this->mainProcess['key'], 'create');

        $this->actor = $request->get('actor', null);

        $this->auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $this->auditData['al_event_name'] = $this->subProcess['name'];
        
        $requestArray = $request->json()->all();

        // save product info as we query so that we avoid multiple queries
        $productTempArray = [];

        try {
            $subProcessKey = $this->subProcess['key'];

            $validationRules = $this->modelApiCommand->createValidationRules();

            // check if this order strategy has a pre validation
            $fulfillmentStrategyClass = null;
            if (array_key_exists('custom_order_data', $requestArray) && array_key_exists('fulfillment_strategy_class', $requestArray['custom_order_data'])) {

                $fulfillmentStrategyClass = $this->helperService->makeObject($requestArray['custom_order_data']['fulfillment_strategy_class']);

                $validationRules = array_merge($this->modelApiCommand->createValidationRules(), $fulfillmentStrategyClass->orderStrategyCreatePreCreateValidationRules());
            }

            // validate order
            $validationRules = $this->modelApiCommand->createValidationRules();
            $validationResponse = $this->apiHelperService->validateRequestFields($requestArray, $validationRules);
            if (!is_null($validationResponse)) {
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, null, $this->validationErrorCatalogue()['code']);
                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);

                $this->auditData['al_is_success'] = $this->data['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = json_encode($requestArray);
                $this->auditData['al_response'] = json_encode($validationResponse);
                AuditLogged::dispatch($this->auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            if (!is_null($this->actor) && $orderService->shouldVerifyPendingFulfillmentsBeforeCreate())
            {
                // prevent actor from creating order if he/she has pending fulfilments in the last x seconds
                $pendingFulfillmentTimeframeSeconds = intval(config('order.order_pending_fulfillment_check_timeframe_seconds'));
                if ($pendingFulfillmentTimeframeSeconds > 0) {
                    $actorPendingFulfillmentsCount = $fulfillmentService->getActorPendingFulfillmentsCount($this->actor->id, $pendingFulfillmentTimeframeSeconds);
                    if (intval($actorPendingFulfillmentsCount) > 0) {
                        $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'pending_fulfillment_block');
                        $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                        $waitTimeInSeconds = intval(config('order.order_create_resume_time_seconds'));
                        $waitTimeHumanized = Carbon::now()->addSeconds($waitTimeInSeconds)->diffForHumans(Carbon::now(), true);
                        $orderCreationHoldMessage = __('citronel-commerce::order/messages.order_create_on_hold', ['wait_time' => $waitTimeHumanized]);

                        $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], $orderCreationHoldMessage);

                        $this->auditData['al_is_success'] = $this->data['success'];
                        $this->auditData['al_code'] = $code['code'];
                        AuditLogged::dispatch($this->auditData);
                    
                        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                    }
                }
            }

            // verify if last payment for last order was accepted at gateway but not processed on platform
            if (!is_null($this->actor) && $orderService->shouldVerifyLastOrderBeforeCreate()) {
                $lastOrderVerificationTimeframeSeconds = intval(config('order.last_order_verification_timeframe_seconds'));
                $getLastOrderToVerifyForActorResponse = $orderService->getLastOrderToVerifyForActor($this->actor, $lastOrderVerificationTimeframeSeconds);
                if ($getLastOrderToVerifyForActorResponse['success']) {
                    $lastOrder = $getLastOrderToVerifyForActorResponse['result'];

                    $verifyLastPaymentResponse = $paymentService->verifyLastPaymentForOrder($lastOrder);
                    if ($verifyLastPaymentResponse['success']) {
                        $payment = $verifyLastPaymentResponse['result']['payment'];

                        $shouldUpdateOrder = array_key_exists('should_update_order', $verifyLastPaymentResponse['result']) ? $verifyLastPaymentResponse['result']['should_update_order'] : false;
                        if ($shouldUpdateOrder) {
                            $orderStatus = $paymentService->mapOrderStatusFromPaymentStatus($payment->payment_status);
                            $saveOrderData = [
                                'order_status' => $orderStatus
                            ];
                            $orderService->updateOrder($payment->order_id, $saveOrderData);

                            // dispatch job to create order fulfilment
                            CreateOrderFulfillment::dispatchSync($payment->order);

                            /**
                             * Fulfill items
                             * If items are sync, fulfill them now
                             * If items are async, dispatch job to fulfill them
                             */
                            $itemFulfillmentResponseMessages = []; // store fulfillment messages
                            $jobPolicyId = 'fulfill_item';

                            $createdFulfillmentStatus = OrderStatus::CREATED->value;
                            $getFulfillmentsByOrderIdResponse = $fulfillmentService->getFulfillmentsByOrderId($payment->order->id, $createdFulfillmentStatus);
                            foreach ($getFulfillmentsByOrderIdResponse as $item) {
                                $productInterfaceObj = $this->helperService->makeObject($item->order_item->product->product_class, ['product' => $item->order_item->product]);

                                $itemFulfillmentPreProcessResponse = $productInterfaceObj->fulfillItemPreProcess($item);
                                if (!$itemFulfillmentPreProcessResponse['success']) {
                                    $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $itemFulfillmentPreProcessResponse['message']);

                                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                                }
                                
                                if (!is_null($itemFulfillmentPreProcessResponse['result'])) {
                                    $this->data['result']['fulfillment_preprocess'] = $itemFulfillmentPreProcessResponse['result'];
                                }

                                if (!is_null($productInterfaceObj->product->fulfillment_conditions)) {
                                    $checkFulfillmentConditionsResponse = $productInterfaceObj->checkFulfillmentConditions($item);
                                    if (!$checkFulfillmentConditionsResponse) {
                                        continue;
                                    }
                                }

                                $fulfillmentTypeResponse = $productInterfaceObj->getFulfillmentItemType();
                                if ($fulfillmentTypeResponse === 'sync') {
                                    $itemFulfillmentResponse = $fulfillmentService->fulfillGroupItems($item);
                                    $itemFulfillmentResponseMessages[] = $itemFulfillmentResponse['message'];
                                } else {
                                    FulfillItem::dispatch($jobPolicyId, $item);
                                    $itemFulfillmentResponseMessages[] = $productInterfaceObj->asyncItemFulfillmentMessage($item);
                                }
                            }
                        }

                        $this->data['result']['payment'] = $verifyLastPaymentResponse['result']['payment'];

                        $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $verifyLastPaymentResponse['message']);

                        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                    }
                }
            }

            // validate order items
            $orderItems = $requestArray['order_items'];
            $validationRulesCustomMessages = $orderItemApi->createValidationRulesMessages();
            $validationRules = $orderItemApi->createValidationRules();
            foreach ($orderItems as $anOrderItem) {
                $validationResponse = $this->apiHelperService->validateRequestFields($anOrderItem, $validationRules, $validationRulesCustomMessages);
                if (!is_null($validationResponse)) {
                    $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, null, $this->validationErrorCatalogue()['code']);
                    $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);
    
                    $this->auditData['al_is_success'] = $this->data['success'];
                    $this->auditData['al_code'] = $code['code'];
                    $this->auditData['al_request'] = json_encode($anOrderItem);
                    $this->auditData['al_response'] = json_encode($validationResponse);
                    AuditLogged::dispatch($this->auditData);
                
                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                }
            }

            // validate order items product
            foreach ($orderItems as $anOrderItem) {
                $productId = $anOrderItem['product_id'];
                $getProductResponse = $productService->getProductById($productId);
                if (!$getProductResponse['success']) {
                    $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'invalid_product');
                    $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $this->subProcess['key'], null, $this->recordNotFoundErrorCatalogue()['code']);
    
                    $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                    $this->auditData['al_is_success'] = $this->data['success'];
                    $this->auditData['al_event_name'] = $subProcessEvent['name'];
                    $this->auditData['al_code'] = $code['code'];
                    $this->auditData['al_request'] = json_encode($anOrderItem);
                    $this->auditData['al_message'] = $code['status'];
                    AuditLogged::dispatch($this->auditData);
                
                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                }
                $product = $getProductResponse['result'];

                // load product class
                $productInterfaceObj = $this->helperService->makeObject($product->product_class, ['product'=> $product]);

                // validate order for this specific product
                $validationRules = $productInterfaceObj->orderItemCreateValidationRules($anOrderItem);
                if (!empty($validationRules)) {
                    $validationResponse = $this->apiHelperService->validateRequestFields($anOrderItem, $validationRules['rules'], $validationRules['messages'], $validationRules['attributes']);

                    if (!is_null($validationResponse)) {
                        $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, null, $this->validationErrorCatalogue()['code']);
                        $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);
        
                        $this->auditData['al_is_success'] = $this->data['success'];
                        $this->auditData['al_code'] = $code['code'];
                        $this->auditData['al_request'] = json_encode($anOrderItem);
                        $this->auditData['al_response'] = json_encode($validationResponse);
                        AuditLogged::dispatch($this->auditData);
                    
                        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                    }
                }

                $productTempArray[$productId] = [
                    'product' => $product,
                    'product_class' => $productInterfaceObj
                ];
            }

            // order create pre process for order items
            $orderItemCreatePreProcessExtra = [
                'correlation_token' => $correlationToken
            ];
            foreach ($orderItems as $anOrderItem) {
                $productId = $anOrderItem['product_id'];
                $productInterfaceObj = $productTempArray[$productId]['product_class'];

                $orderItemCreatePreProcessResponse = $productInterfaceObj->orderItemCreatePreProcess($anOrderItem, $orderItemCreatePreProcessExtra);
                if (!$orderItemCreatePreProcessResponse['success']) {
                    $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'invalid_pre_process');
                    $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                    $this->auditData['al_event_name'] = $subProcessEvent['name'];
                    $this->auditData['al_is_success'] = $orderItemCreatePreProcessResponse['success'];
                    $this->auditData['al_code'] = $code['code'];
                    $this->auditData['al_request'] = json_encode($anOrderItem);
                    $this->auditData['al_message'] = $orderItemCreatePreProcessResponse['message'];
                    AuditLogged::dispatch($this->auditData);

                    $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], $orderItemCreatePreProcessResponse['message']);
                
                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                }
            }

            // at this point we have valid data to create order
            $orderCurrencyCode = array_key_exists('order_currency_code', $requestArray) ? strtoupper($requestArray['order_currency_code']) : $currencyService->getDefaultCurrencyCode();

            // get current currency rate
            $currencyRate = null;
            if (!$this->lockCurrency && ($orderCurrencyCode !== $currencyService->getBaseCurrencyCode())) {
                $getCurrencyRateResponse = $currencyService->getLatestCurrencyRate();
                if (is_null($getCurrencyRateResponse)) {
                    $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'invalid_currency');
                    $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                    $this->auditData['al_event_name'] = $subProcessEvent['name'];
                    $this->auditData['al_is_success'] = $getCurrencyRateResponse['success'];
                    $this->auditData['al_code'] = $code['code'];
                    $this->auditData['al_request'] = $orderCurrencyCode;
                    AuditLogged::dispatch($this->auditData);

                    $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);
                
                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                }
                $currencyRate = $getCurrencyRateResponse;
            }

            $shouldSendReceipt = config('order.order_should_send_receipt');
            if (!is_null($fulfillmentStrategyClass)) {
                $shouldSendReceipt = $fulfillmentStrategyClass->shouldSendReceipt();
            }

            $receiptChannels = config('order.order_receipt_channels');
            if (!is_null($fulfillmentStrategyClass)) {
                $receiptChannels = $fulfillmentStrategyClass->allowedReceiptChannels();
            }

            // create order
            $createOrderSaveData = [
                'actor_id' => $this->actor ? $this->actor->id : null,
                'currency_rate' => $currencyRate,
                'order_currency_code' => $orderCurrencyCode,
                'correlation_token' => $correlationToken,
                'should_send_receipt' => $shouldSendReceipt,
                'receipt_channels' => $receiptChannels,
            ];
            if (array_key_exists('custom_order_data', $requestArray)) {
                $createOrderSaveData = array_merge($createOrderSaveData, $requestArray['custom_order_data']);
            }

            $createOrderExtra = [
                'product_temp_array' => $productTempArray,
                'correlation_token' => $correlationToken,
                'lock_currency' => $this->lockCurrency,
            ];

            // create order preprocess
            $orderPreProcessResponse = $orderService->orderCreatePreprocess($createOrderSaveData, $orderItems, $createOrderExtra);
            if (!$orderPreProcessResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'invalid_pre_process');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], $orderPreProcessResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $createOrderResponse = $orderService->createOrder($createOrderSaveData, $orderItems, $createOrderExtra);
            if (!$createOrderResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'create_failure');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->auditData['al_event_name'] = $subProcessEvent['name'];
                $this->auditData['al_is_success'] = $createOrderResponse['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_message'] = $createOrderResponse['message'];
                AuditLogged::dispatch($this->auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], $createOrderResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $newOrder = $createOrderResponse['result']['order'];

            $productTempArray = null;

            $generatePaymentMethodExtra = [
                'order' => $newOrder,
                'fulfillment_strategy_class' => $fulfillmentStrategyClass,
            ];
            $this->data['extra'] = $paymentMethodService->generatePaymentMethodExtra($generatePaymentMethodExtra);

            $generateCurrencyExtra = [
                'order' => $newOrder,
                'fulfillment_strategy_class' => $fulfillmentStrategyClass,
            ];
            $currencyExtra = $currencyService->generateCurrencyExtra($generateCurrencyExtra);
            $this->data['extra'] = array_merge($this->data['extra'], $currencyExtra);

            $this->data['result']['order'] = $newOrder;

            $generateOrderSummaryBeforeConfirmationResponse = $orderService->generateOrderSummaryBeforeConfirmation($newOrder);
            $this->data['result']['summary'] = $generateOrderSummaryBeforeConfirmationResponse['result'];

            $this->data['success'] = true;
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);

            $this->auditData['al_action_type'] = config('audit.action_types.create.name');
            $this->auditData['al_event_name'] = $this->subProcess['events']['created']['name'];
            $this->auditData['al_is_success'] = $this->data['success'];
            $this->auditData['al_response'] = $newOrder->id;

            OrderCreated::dispatch($this->auditData);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
