<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;
use aliirfaan\CitronelCommerce\Services\Order\CitronelFulfillmentService;
use aliirfaan\CitronelCommerce\Exceptions\Order\ItemFulfillmentException;
use aliirfaan\CitronelCommerce\Enums\Order\OrderStatus;
use aliirfaan\CitronelCommerce\Jobs\Order\FulfillItem;
use aliirfaan\CitronelCommerce\Services\Currency\CitronelCurrencyService;

class OrderFulfillController extends OrderController
{
    public function fulfill(Request $request, $order_guid, AuditLogService $auditService, CitronelOrderService $orderService, CitronelFulfillmentService $fulfillmentService, CitronelCurrencyService $currencyService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $this->subProcess = $this->errorCatalogueService->getSubProcess($this->mainProcess['key'], 'fulfill');

        $this->actor = $request->get('actor', null);

        $this->auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $this->auditData['al_event_name'] = $this->subProcess['name'];
        
        $requestArray = $request->json()->all();

        try{
            $subProcessKey = $this->subProcess['key'];

            $getOrderResponse = $orderService->getOrderByGuid($order_guid);
            if (!$getOrderResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent($this->mainProcess['key'], $this->subProcess['key'], 'invalid_order');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $this->auditData['al_is_success'] = $this->data['success'];
                $this->auditData['al_event_name'] = $subProcessEvent['name'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $order_guid;
                $this->auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($this->auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $order = $getOrderResponse['result'];

            // check order expiry
            $checkOrderExpiryResponse = $orderService->checkOrderExpiry($order);
            if (!$checkOrderExpiryResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent($this->mainProcess['key'], $this->subProcess['key'], 'expired_order');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->auditData['al_event_name'] = $subProcessEvent['name'];
                $this->auditData['al_is_success'] = $checkOrderExpiryResponse['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $order->id;
                AuditLogged::dispatch($this->auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $checkOrderExpiryResponse['message'], ['code' => $code['code']]);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            // validate order for fulfillment
            $validateOrderForFulfillmentResponse = $orderService->validateOrderForFulfillment($order);
            if (!$validateOrderForFulfillmentResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent($this->mainProcess['key'], $this->subProcess['key'], 'invalid_order');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->auditData['al_event_name'] = $subProcessEvent['name'];
                $this->auditData['al_is_success'] = $validateOrderForFulfillmentResponse['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $order->id;
                AuditLogged::dispatch($this->auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $validateOrderForFulfillmentResponse['message'], ['code' => $code['code']]);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            /**
             * Fulfill items
             * If items are sync, fulfill them now
             * If items are async, dispatch job to fulfill them
             */
            $fulfillItemExtra = [
                'language' => $this->locale,
            ];
            $itemFulfillmentResponseMessages = []; // store fulfillment messages
            $jobPolicyId = 'fulfill_item';

            $createdFulfillmentStatus = OrderStatus::CREATED->value;
            $getFulfillmentsByOrderIdResponse = $fulfillmentService->getFulfillmentsByOrderId($order->id, $createdFulfillmentStatus);
            foreach ($getFulfillmentsByOrderIdResponse as $item) {
                $productInterfaceObj = $this->helperService->makeObject($item->order_item->product->product_class, ['product' => $item->order_item->product]);

                if (!is_null($productInterfaceObj->product->fulfillment_conditions)) {
                    $checkFulfillmentConditionsResponse = $productInterfaceObj->checkFulfillmentConditions($item);
                    if (!$checkFulfillmentConditionsResponse) {
                        continue;
                    }
                }

                $fulfillmentTypeResponse = $productInterfaceObj->getFulfillmentItemType();
                if ($fulfillmentTypeResponse === 'sync') {
                    $itemFulfillmentResponse = $fulfillmentService->fulfillItem($item, $fulfillItemExtra);
                    if (is_array($itemFulfillmentResponse) && array_key_exists('message', $itemFulfillmentResponse)) {
                        $itemFulfillmentResponseMessages[] = $itemFulfillmentResponse['message'];
                    }
                } else {
                    // if asyn how to prevent double fulfillment!
                    FulfillItem::dispatch($jobPolicyId, $item,$fulfillItemExtra);
                    $itemFulfillmentResponseMessages[] = $productInterfaceObj->asyncItemFulfillmentMessage($item);
                }
            }

            // order fulfillment summary
            $generateOrderFulfillmentSummaryResponse = $fulfillmentService->generateOrderFulfillmentSummary($order);
            $orderFulfillmentSummary = $generateOrderFulfillmentSummaryResponse['result'];

            $orderFulfillmentSummary['order'] = $order;

            $payment = $fulfillmentService->getSuccessPaymentForOrderFulfillmentSummary($order->id);
            $orderFulfillmentSummary['payment'] = $payment;
    
            $orderFulfillmentSummary['totals']['sub_total'] = $currencyService->formatCurrencyAmount($order->order_subtotal, $order->order_currency_code);
            $orderFulfillmentSummary['totals']['grand_total'] = $currencyService->formatCurrencyAmount($order->order_grand_total, $order->order_currency_code);

            $this->data['result'] = $orderFulfillmentSummary;

            $this->data['success'] = true;
            $this->data['status_code'] = Response::HTTP_OK;

            // add item fulfillment messages
            $itemFulfillmentResponseMessagesString = implode(' ', $itemFulfillmentResponseMessages);
            $this->data['message'] = $this->data['message'] . ' ' . $itemFulfillmentResponseMessagesString;

            $this->resultResponse = new ApiResponseCollection($this->data);

        } catch (ItemFulfillmentException $e) {
            $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $e->getMessage());

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
