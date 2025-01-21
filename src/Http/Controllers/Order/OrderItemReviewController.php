<?php

namespace App\Http\Controllers\Api\v1\Order;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Currency\CitronelCurrencyService;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentMethodService;
use aliirfaan\CitronelCore\Http\Controllers\CitronelController;
use aliirfaan\CitronelCommerce\Models\Order\OrderItem;
use aliirfaan\CitronelCore\Traits\CitronelApiControllerTrait;

class OrderItemReviewController extends CitronelController
{
    use CitronelApiControllerTrait;

    public function __construct(OrderItem $modelApiCommand, OrderItem $modelApiQuery)
    {
        parent::__construct();

        $this->namespace = 'order';
        $this->mainProcess = config('error-catalogue.process.order.key');

        $this->modelApiCommand = $modelApiCommand;
        $this->modelApiQuery = $modelApiQuery;
    }

    public function review(Request $request, $order_guid, $order_item_id, AuditLogService $auditService, CitronelCurrencyService $currencyService, CitronelOrderService $orderService, CitronelPaymentMethodService $paymentMethodService)
    {
        $correlationToken = $this->helperService->getCorrelationToken($request);
        $reponseHeaders = $this->helperService->correlationResponseHeader($correlationToken);

        $subProcess = config('error-catalogue.process.order.sub_process.item_review');

        $this->actor = $request->get('actor', null);

        $auditData = $auditService->generatePreliminaryEventData($request, $correlationToken, $this->actor);
        $auditData['al_event_name'] = $subProcess['name'];
        
        $requestArray = $request->json()->all();

        try{
            $subProcessKey = $subProcess['key'];

            // validate order
            $validationRules = $this->modelApiCommand->reviewValidationRules();
            $validationResponse = $this->apiHelperService->validateRequestFields($requestArray, $validationRules);
            if (!is_null($validationResponse)) {
                $code = $this->helperService->generateProcessCode($this->mainProcess['key'], $subProcessKey, null, $this->validationErrorCatalogue()['code']);
                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = json_encode($requestArray);
                $auditData['al_response'] = json_encode($validationResponse);
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $getOrderResponse = $orderService->getOrderByGuid($order_guid);
            if (!$getOrderResponse['success']) {
                $subProcessErrorKey = config('error-catalogue.process.order.sub_process.item_review.events.invalid_order.key');
                $code = $this->helperService->generateProcessCode($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_event_name'] = config('error-catalogue.process.order.sub_process.item_review.events.invalid_order.name');
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = $order_guid;
                $auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $order = $getOrderResponse['result'];

            // authorize
            Gate::forUser($this->actor)->authorize('matchActorToken', $order->actor_id);

            // check order expiry
            $checkOrderExpiryResponse = $orderService->checkOrderExpiry($order);
            if (!$checkOrderExpiryResponse['success']) {
                $subProcessErrorKey = config('error-catalogue.process.order.sub_process.item_review.events.expired_order.key');
                $code = $this->helperService->generateProcessCode($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $auditData['al_event_name'] = config('error-catalogue.process.order.sub_process.item_review.events.expired_order.name');
                $auditData['al_is_success'] = $checkOrderExpiryResponse['success'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = $order->id;
                AuditLogged::dispatch($auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $checkOrderExpiryResponse['message'], ['code' => $code['code']]);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            // get order item
            $orderItem = $order->order_items()->where('id', $order_item_id)->first();
            if (is_null($orderItem)) {
                $subProcessErrorKey = config('error-catalogue.process.order.sub_process.item_review.events.invalid_order_item.key');
                $code = $this->helperService->generateProcessCode($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_event_name'] = config('error-catalogue.process.order.sub_process.item_review.events.invalid_order_item.name');
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = $order_guid;
                $auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            // load product class
            $productInterfaceObj = $this->helperService->makeObject($orderItem->product->product_class, ['product'=> $orderItem->product]);

            $validationRules = $productInterfaceObj->orderItemUpdateValidationRules();
            $validationRulesCustomMessages = $productInterfaceObj->orderItemUpdateValidationRulesMessages();
            $validationResponse = $this->apiHelperService->validateRequestFields($requestArray, $validationRules, $validationRulesCustomMessages);
            if (!is_null($validationResponse)) {
                $code = $this->helperService->generateProcessCode($this->mainProcess['key'], $subProcessKey, null, $this->validationErrorCatalogue()['code']);
                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = json_encode($requestArray);
                $auditData['al_response'] = json_encode($validationResponse);
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $updateOrderItemSaveData = [
                'quantity' => $requestArray['quantity']
            ];

            $reviewOrderItemResponse = $orderService->reviewOrderItem($updateOrderItemSaveData, $orderItem);

            $this->data['extra'] = $paymentMethodService->generatePaymentMethodExtra();
            $currencyExtra = $currencyService->generateCurrencyExtra();
            $this->data['extra'] = array_merge($this->data['extra'], $currencyExtra);

            $this->data['success'] = true;
            $this->data['result'] = $reviewOrderItemResponse['result'];
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
