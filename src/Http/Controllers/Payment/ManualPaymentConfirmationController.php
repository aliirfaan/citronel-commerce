<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentMethodService;
use aliirfaan\CitronelCommerce\Jobs\Order\CreateOrderFulfillment;
use aliirfaan\CitronelCommerce\Models\Order\ManualFulfillmentRetry;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentService;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;

class ManualPaymentConfirmationController extends PaymentController
{
    public function confirmPayment(Request $request, $gateway_merchant_transaction_no, AuditLogService $auditService, CitronelPaymentService $paymentService, ManualFulfillmentRetry $manualPaymentConfirmationApiCommand, CitronelPaymentMethodService $paymentMethodService, CitronelOrderService $orderService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $subProcess = $this->errorCatalogueService->getSubProcess('payment', 'manual_payment_update');

        $this->actor = $request->get('actor', null);

        $auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $auditData['al_event_name'] = $subProcess['name'];
        
        $requestArray = $request->json()->all();

        try {
            $subProcessKey = $subProcess['key'];

            // validate
            $validationRules = $manualPaymentConfirmationApiCommand->createValidationRules();
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

            // get payment
            $getPaymentResponse = $paymentService->getPaymentByMerchantGatewayTxNum($gateway_merchant_transaction_no);
            if (!$getPaymentResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('payment', 'manual_payment_update', 'invalid_item');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_event_name'] = $subProcessEvent['name'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = $gateway_merchant_transaction_no;
                $auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $payment = $getPaymentResponse['result'];

            // validate payment for manual success confirmation
            $validatePaymentForManualConfirmationResponse = $paymentService->validatePaymentForManualConfirmation($payment);
            if (!$validatePaymentForManualConfirmationResponse['success']) {
                $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $validatePaymentForManualConfirmationResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            // validate payment method
            $getPaymentMethodConfigurationResponse = $paymentMethodService->getPaymentMethodConfigurationById($payment->payment_method_configuration_id);
            if (!$getPaymentMethodConfigurationResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('payment', 'manual_payment_update', 'invalid_payment_method');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_event_name'] = $subProcess['events']['invalid_payment_method']['name'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = $payment->payment_method_configuration_id;
                $auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $paymentMethodConfiguration = $getPaymentMethodConfigurationResponse['result'];

            // load payment method class
            $paymentInterfaceObj = $this->helperService->makeObject($paymentMethodConfiguration->payment_class, ['paymentMethodConfigurationId' => $paymentMethodConfiguration->id]);

            // validate update fields for payment method
            $validationRules = $paymentInterfaceObj->manualPaymentConfirmationValidationRules();
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

            $manuallyConfirmPaymentExtra = $requestArray;
            $manuallyConfirmPaymentResponse = $paymentService->manuallyConfirmPayment($payment, $paymentInterfaceObj, $manuallyConfirmPaymentExtra);
            if (!$manuallyConfirmPaymentResponse['success']) {
                $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $manuallyConfirmPaymentResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $payment = $manuallyConfirmPaymentResponse['result']['payment'];

            $shouldUpdateOrder = array_key_exists('should_update_order', $manuallyConfirmPaymentResponse['result']) ? $manuallyConfirmPaymentResponse['result']['should_update_order'] : false;
            if ($shouldUpdateOrder) {
                $orderStatus = $paymentService->mapOrderStatus($payment->payment_status);
                $saveOrderData = [
                    'order_status' => $orderStatus
                ];
                $orderService->updateOrder($payment->order_id, $saveOrderData);

                // dispatch job to create order fulfilment
                CreateOrderFulfillment::dispatch($payment->order);
            }

            $this->data['result']['payment'] = $manuallyConfirmPaymentResponse['result']['payment'];
            $this->data['success'] = $manuallyConfirmPaymentResponse['success'];
            $this->data['status_code'] = Response::HTTP_OK;
            $this->data['message'] = $manuallyConfirmPaymentResponse['message'];

            $this->resultResponse = new ApiResponseCollection($this->data);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
