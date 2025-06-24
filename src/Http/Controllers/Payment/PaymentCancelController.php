<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentMethodService;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentService;
use aliirfaan\CitronelCommerce\Services\Currency\CitronelCurrencyService;

class PaymentCancelController extends PaymentController
{
    public function cancel(Request $request, $gateway_merchant_transaction_no, AuditLogService $auditService, CitronelPaymentMethodService $paymentMethodService, CitronelPaymentService $paymentService, CitronelCurrencyService $currencyService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $this->subProcess = $this->errorCatalogueService->getSubProcess($this->mainProcess['key'], 'cancel');

        $this->actor = $request->get('actor', null);

        $this->auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken);
        $this->auditData['al_event_name'] = $this->subProcess['name'];

        $requestArray = $request->json()->all();

        try {
            $subProcessKey = $this->subProcess['key'];
            
            // validate
            $requestArray = $request->json()->all();

            $requestArray['gateway_merchant_transaction_no'] = $gateway_merchant_transaction_no;

            // get payment
            $getPaymentResponse = $paymentService->getPaymentByMerchantGatewayTxNum($requestArray['gateway_merchant_transaction_no']);
            if (!$getPaymentResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('payment', 'cancel', 'invalid_payment');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $this->auditData['al_is_success'] = $this->data['success'];
                $this->auditData['al_event_name'] = $subProcessEvent['name'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $requestArray['gateway_merchant_transaction_no'];
                $this->auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($this->auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $payment = $getPaymentResponse['result'];

            // validate payment for cancellation
            $validatePaymentForCancellationResponse = $paymentService->validatePaymentForCancellation($payment);
            if (!$validatePaymentForCancellationResponse['success']) {
                $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $validatePaymentForCancellationResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            // validate payment method
            $getPaymentMethodConfigurationResponse = $paymentMethodService->getPaymentMethodConfigurationById($payment->payment_method_configuration_id);
            if (!$getPaymentMethodConfigurationResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('payment', 'update', 'invalid_payment_method');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $this->auditData['al_is_success'] = $this->data['success'];
                $this->auditData['al_event_name'] = $subProcessEvent['name'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $payment->payment_method_configuration_id;
                $this->auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($this->auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $paymentMethodConfiguration = $getPaymentMethodConfigurationResponse['result'];

            // load payment method class
            $paymentInterfaceObj = $this->helperService->makeObject($paymentMethodConfiguration->payment_class, ['paymentMethod' => $paymentMethodConfiguration]);

            // payment gateway payment cancellation
            $paymentGatewayServiceCancelPaymentResponse = $paymentInterfaceObj->cancelPayment($payment);
            if (!$paymentGatewayServiceCancelPaymentResponse['success']) {
                $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $paymentGatewayServiceCancelPaymentResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $formattedAmount  = $currencyService->formatCurrencyAmount($payment->grand_total, $payment->currency_code)['formatted_with_code'];
            $processPaymentExtra = [
                'amount' => $formattedAmount,
                'payment_reference' => $payment->gateway_merchant_transaction_no
            ];
            
            $processPaymentResponse = $paymentService->cancelPayment($payment, $paymentInterfaceObj, $processPaymentExtra);
            if (!$processPaymentResponse['success']) {
                $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $processPaymentResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $payment = $processPaymentResponse['result']['payment'];

            $this->data['result']['payment'] = $processPaymentResponse['result']['payment'];
            $this->data['success'] = $processPaymentResponse['success'];
            $this->data['status_code'] = Response::HTTP_OK;
            $this->data['message'] = $processPaymentResponse['message'];

            $this->resultResponse = new ApiResponseCollection($this->data);
        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
