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

class OrderCreateController extends OrderController
{
    public function create(Request $request, AuditLogService $auditService, OrderItem $orderItemApi, CitronelProductService $productService, CitronelCurrencyService $currencyService, CitronelOrderService $orderService, CitronelPaymentMethodService $paymentMethodService, CitronelFulfillmentService $fulfillmentService, CitronelPaymentService $paymentService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $subProcess = $this->errorCatalogueService->getSubProcess($this->mainProcess['key'], 'create');

        $this->actor = $request->get('actor', null);

        $auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $auditData['al_event_name'] = $subProcess['name'];
        
        $requestArray = $request->json()->all();

        // save product info as we query so that we avoid multiple queries
        $productTempArray = [];

        try {
            $subProcessKey = $subProcess['key'];

            // validate order
            $validationRules = $this->modelApiCommand->createValidationRules();
            $validationResponse = $this->apiHelperService->validateRequestFields($requestArray, $validationRules);
            if (!is_null($validationResponse)) {
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, null, $this->validationErrorCatalogue()['code']);
                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = json_encode($requestArray);
                $auditData['al_response'] = json_encode($validationResponse);
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            if ($orderService->shouldVerifyPendingFulfillmentsBeforeCreate())
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
                        $orderCreationHoldMessage = __('order/messages.order_create_on_hold', ['wait_time' => $waitTimeHumanized]);

                        $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], $orderCreationHoldMessage);

                        $auditData['al_is_success'] = $this->data['success'];
                        $auditData['al_code'] = $code['code'];
                        AuditLogged::dispatch($auditData);
                    
                        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                    }
                }
            }

            // verify if last payment for last order was accepted at gateway but not processed on platform
            if ($orderService->shouldVerifyLastOrderBeforeCreate()) {
                $lastOrderVerificationTimeframeSeconds = intval(config('order.last_order_verification_timeframe_seconds'));
                $getLastOrderToVerifyForActorResponse = $orderService->getLastOrderToVerifyForActor($this->actor, $lastOrderVerificationTimeframeSeconds);
                if ($getLastOrderToVerifyForActorResponse['success']) {
                    $lastOrder = $getLastOrderToVerifyForActorResponse['result'];

                    $verifyLastPaymentResponse = $paymentService->verifyLastPaymentForOrder($lastOrder);
                    if ($verifyLastPaymentResponse['success']) {
                        $payment = $verifyLastPaymentResponse['result']['payment'];

                        $shouldUpdateOrder = array_key_exists('should_update_order', $verifyLastPaymentResponse['result']) ? $verifyLastPaymentResponse['result']['should_update_order'] : false;
                        if ($shouldUpdateOrder) {
                            $orderStatus = $paymentService->mapOrderStatus($payment->payment_status);
                            $saveOrderData = [
                                'order_status' => $orderStatus
                            ];
                            $orderService->updateOrder($payment->order_id, $saveOrderData);

                            // dispatch job to create order fulfilment
                            CreateOrderFulfillment::dispatch($payment->order);
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
    
                    $auditData['al_is_success'] = $this->data['success'];
                    $auditData['al_code'] = $code['code'];
                    $auditData['al_request'] = json_encode($anOrderItem);
                    $auditData['al_response'] = json_encode($validationResponse);
                    AuditLogged::dispatch($auditData);
                
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

                    $auditData['al_is_success'] = $this->data['success'];
                    $auditData['al_event_name'] = $subProcessEvent['name'];
                    $auditData['al_code'] = $code['code'];
                    $auditData['al_request'] = json_encode($anOrderItem);
                    $auditData['al_message'] = $code['status'];
                    AuditLogged::dispatch($auditData);
                
                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                }
                $product = $getProductResponse['result'];

                // load product class
                $productInterfaceObj = $this->helperService->makeObject($product->product_class, ['product'=> $product]);

                // validate order for this specific product
                $validationRules = $productInterfaceObj->orderItemCreateValidationRules();
                if (!empty($validationRules)) {
                    $validationResponse = $this->apiHelperService->validateRequestFields($anOrderItem, $validationRules['rules'], $validationRules['messages'], $validationRules['attributes']);

                    if (!is_null($validationResponse)) {
                        $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, null, $this->validationErrorCatalogue()['code']);
                        $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);
        
                        $auditData['al_is_success'] = $this->data['success'];
                        $auditData['al_code'] = $code['code'];
                        $auditData['al_request'] = json_encode($anOrderItem);
                        $auditData['al_response'] = json_encode($validationResponse);
                        AuditLogged::dispatch($auditData);
                    
                        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                    }
                }

                $productTempArray[$productId] = [
                    'product' => $product,
                    'product_class' => $productInterfaceObj
                ];
            }

            // order create pre process for order items
            foreach ($orderItems as $anOrderItem) {
                $productId = $anOrderItem['product_id'];
                $productInterfaceObj = $productTempArray[$productId]['product_class'];

                $orderItemCreatePreProcessResponse = $productInterfaceObj->orderItemCreatePreProcess($anOrderItem);
                if (!$orderItemCreatePreProcessResponse['success']) {
                    $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'invalid_pre_process');
                    $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                    $auditData['al_event_name'] = $subProcessEvent['name'];
                    $auditData['al_is_success'] = $orderItemCreatePreProcessResponse['success'];
                    $auditData['al_code'] = $code['code'];
                    $auditData['al_request'] = json_encode($anOrderItem);
                    $auditData['al_message'] = $orderItemCreatePreProcessResponse['message'];
                    AuditLogged::dispatch($auditData);

                    $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], $orderItemCreatePreProcessResponse['message']);
                
                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                }
            }
            
            // at this point we have valid data to create order
            $orderCurrencyCode = array_key_exists('order_currency_code', $requestArray) ? strtoupper($requestArray['order_currency_code']) : $currencyService->getDefaultCurrencyCode();

            // get current currency rate
            $currencyRate = null;
            if ($orderCurrencyCode !== $currencyService->getBaseCurrencyCode()) {
                $getCurrencyRateResponse = $currencyService->getLatestCurrencyRate();
                if (is_null($getCurrencyRateResponse)) {
                    $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'invalid_currency');
                    $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                    $auditData['al_event_name'] = $subProcessEvent['name'];
                    $auditData['al_is_success'] = $getCurrencyRateResponse['success'];
                    $auditData['al_code'] = $code['code'];
                    $auditData['al_request'] = $orderCurrencyCode;
                    AuditLogged::dispatch($auditData);

                    $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);
                
                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                }
                $currencyRate = $getCurrencyRateResponse;
            }

            // create order
            $createOrderSaveData = [
                'actor_id' => $this->actor ? $this->actor->id : null,
                'currency_rate' => $currencyRate,
                'order_currency_code' => $orderCurrencyCode,
                'correlation_token' => $correlationToken
            ];
            if (array_key_exists('custom_order_data', $requestArray)) {
                $createOrderSaveData = array_merge($createOrderSaveData, $requestArray['custom_order_data']);
            }

            $createOrderExtra = [
                'product_temp_array' => $productTempArray,
                'correlation_token' => $correlationToken
            ];
            $createOrderResponse = $orderService->createOrder($createOrderSaveData, $orderItems, $createOrderExtra);
            if (!$createOrderResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'create_failure');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $auditData['al_event_name'] = $subProcessEvent['name'];
                $auditData['al_is_success'] = $createOrderResponse['success'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_message'] = $createOrderResponse['message'];
                AuditLogged::dispatch($auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], $createOrderResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $newOrder = $createOrderResponse['result'];

            $productTempArray = null;

            $this->data['extra'] = $paymentMethodService->generatePaymentMethodExtra();
            $currencyExtra = $currencyService->generateCurrencyExtra();
            $this->data['extra'] = array_merge($this->data['extra'], $currencyExtra);

            $this->data['success'] = true;
            $this->data['result']['order'] = $newOrder;
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);

            $auditData['al_action_type'] = config('audit.action_types.create.name');
            $auditData['al_event_name'] = $subProcess['events']['created']['name'];
            $auditData['al_is_success'] = $this->data['success'];
            $auditData['al_response'] = $newOrder->id;

            OrderCreated::dispatch($auditData);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
