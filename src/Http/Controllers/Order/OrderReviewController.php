<?php

namespace aliirfaan\CitronelCommerce\Controllers\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Currency\CitronelCurrencyService;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentMethodService;

class OrderReviewController extends OrderController
{
    public function review(Request $request, $order_guid, AuditLogService $auditService, CitronelCurrencyService $currencyService, CitronelOrderService $orderService, CitronelPaymentMethodService $paymentMethodService)
    {
        $correlationToken = $this->helperService->getCorrelationToken($request);
        $reponseHeaders = $this->helperService->correlationResponseHeader($correlationToken);

        $subProcess = $this->errorCatalogueService->getSubProcess($this->mainProcess['key'], 'review');

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
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'review', 'invalid_order');
                $code = $this->helperService->generateProcessCode($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_event_name'] = $subProcessEvent['name'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = $order_guid;
                $auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $order = $getOrderResponse['result'];

            // check order expiry
            $checkOrderExpiryResponse = $orderService->checkOrderExpiry($order);
            if (!$checkOrderExpiryResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'review', 'expired_order');
                $code = $this->helperService->generateProcessCode($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $auditData['al_event_name'] = $subProcessEvent['name'];
                $auditData['al_is_success'] = $checkOrderExpiryResponse['success'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = $order->id;
                AuditLogged::dispatch($auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $checkOrderExpiryResponse['message'], ['code' => $code['code']]);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            // validate payment method
            $getPaymentMethodConfigurationResponse = $paymentMethodService->getPaymentMethodConfigurationById($requestArray['order_payment_method_configuration_id']);
            if (!$getPaymentMethodConfigurationResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'review', 'invalid_payment_method');
                $code = $this->helperService->generateProcessCode($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_event_name'] = $subProcessEvent['name'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = $order_guid;
                $auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $paymentMethodConfiguration = $getPaymentMethodConfigurationResponse['result'];

            $orderCurrencyCode = array_key_exists('order_currency_code', $requestArray) ? strtoupper($requestArray['order_currency_code']) : null;

            if (is_null($orderCurrencyCode)) {
                // load payment method class
                $paymentInterfaceObj = $this->helperService->makeObject($paymentMethodConfiguration->payment_class, ['paymentMethodConfigurationId' => $paymentMethodConfiguration->id]);

                $orderCurrencyCode = $paymentInterfaceObj->defaultCurrency;
            }

            $updateOrderSaveData = [
                'order_currency_code' => $orderCurrencyCode,
                'order_payment_method_configuration_id' => $paymentMethodConfiguration->id
            ];

            $reviewOrderResponse = $orderService->reviewOrder($updateOrderSaveData, $order);

            $this->data['extra'] = $paymentMethodService->generatePaymentMethodExtra();
            $currencyExtra = $currencyService->generateCurrencyExtra();
            $this->data['extra'] = array_merge($this->data['extra'], $currencyExtra);

            $this->data['success'] = true;
            $this->data['result'] = $reviewOrderResponse['result'];
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
