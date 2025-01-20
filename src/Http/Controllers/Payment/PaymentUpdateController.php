<?php

namespace aliirfaan\CitronelCommerce\Controllers\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentMethodService;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentService;
use aliirfaan\CitronelCommerce\Jobs\Order\CreateOrderFulfillment;
use aliirfaan\CitronelCommerce\Services\Currency\CitronelCurrencyService;
use aliirfaan\CitronelCommerce\Exceptions\Order\ItemFulfillmentException;

class PaymentUpdateController extends PaymentController
{
    public function update(Request $request, $gateway_merchant_transaction_no, AuditLogService $auditService, CitronelOrderService $orderService, CitronelPaymentMethodService $paymentMethodService, CitronelPaymentService $paymentService, CitronelCurrencyService $currencyService)
    {
        $correlationToken = $this->helperService->getCorrelationToken($request);
        $reponseHeaders = $this->helperService->correlationResponseHeader($correlationToken);

        $subProcess = config('error-catalogue.process.payment.sub_process.update');

        $this->actor = $request->get('actor', null);

        $auditData = $auditService->generatePreliminaryEventData($request, $correlationToken);
        $auditData['al_event_name'] = $subProcess['name'];

        $requestArray = $request->json()->all();

        try {
            $subProcessKey = $subProcess['key'];
            
            // validate
            $requestArray = $request->json()->all();
            $validationRules = $paymentService->updatePaymentValidationRules();
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

            $requestArray['gateway_merchant_transaction_no'] = $gateway_merchant_transaction_no;

            // get payment
            $getPaymentResponse = $paymentService->getPaymentByMerchantGatewayTxNum($requestArray['gateway_merchant_transaction_no']);
            if (!$getPaymentResponse['success']) {
                $subProcessErrorKey = $subProcess['events']['invalid_payment']['key'];
                $code = $this->helperService->generateProcessCode($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_event_name'] = $subProcess['events']['invalid_payment']['name'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = $requestArray['gateway_merchant_transaction_no'];
                $auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $payment = $getPaymentResponse['result'];

            // validate payment method
            $getPaymentMethodConfigurationResponse = $paymentMethodService->getPaymentMethodConfigurationById($payment->payment_method_configuration_id);
            if (!$getPaymentMethodConfigurationResponse['success']) {
                $subProcessErrorKey = $subProcess['events']['invalid_payment_method']['key'];
                $code = $this->helperService->generateProcessCode($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

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

            // validate payment channel
            $paymentChannel = $requestArray['payment_channel'];
            $validatePaymentChannelResponse = $paymentInterfaceObj->validatePaymentChannel($paymentChannel);
            if (!$validatePaymentChannelResponse['success']) {
                $subProcessErrorKey = $subProcess['events']['invalid_payment_channel']['key'];
                $code = $this->helperService->generateProcessCode($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $auditData['al_event_name'] = $subProcess['events']['invalid_currency']['name'];
                $auditData['al_is_success'] = $validatePaymentChannelResponse['success']['success'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = $paymentChannel;
                AuditLogged::dispatch($auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $validatePaymentChannelResponse['message'], ['code' => $code['code']]);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            // validate update/callback fields for this payment method and channel
            $validationRules = $paymentInterfaceObj->callbackFieldsValidationRules($paymentChannel);
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

            // payment gateway payment processing
            $processPaymentGatewayExtra = ['payment' => $payment];
            $processPaymentGatewayServiceResponse = $paymentInterfaceObj->processPayment($requestArray, $processPaymentGatewayExtra, $paymentChannel);
            if (!$processPaymentGatewayServiceResponse['success']) {
                $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $processPaymentGatewayServiceResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $gatewayProcessingResponse = $processPaymentGatewayServiceResponse['result'];

            // map update/callback fields to payment interface fields
            $gatewayResponseFields = $paymentInterfaceObj->mapCallbackFields($requestArray, $paymentChannel);

            // internal payment processing
            $formattedAmount  = $currencyService->formatCurrencyAmount($payment->grand_total, $payment->currency_code)['formatted_with_symbol'];
            $processPaymentExtra = [
                'amount' => $formattedAmount,
                'payment_reference' => $payment->gateway_merchant_transaction_no
            ];
            
            $processPaymentResponse = $paymentService->processPayment($payment, $paymentInterfaceObj, $gatewayResponseFields, $gatewayProcessingResponse, $paymentChannel, $payment->order->correlation_token, $processPaymentExtra);
            if (!$processPaymentResponse['success']) {
                $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $processPaymentResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $payment = $processPaymentResponse['result']['payment'];

            // update order status only if payment was processed
            // for some gateways like my.t money, we may get callbacks from multiple channels
            $shouldUpdateOrder = array_key_exists('should_update_order', $processPaymentResponse['result']) ? $processPaymentResponse['result']['should_update_order'] : false;
            if ($shouldUpdateOrder) {
                $orderStatus = $paymentService->mapOrderStatus($payment->payment_status);
                $saveOrderData = [
                    'order_status' => $orderStatus
                ];
                $orderService->updateOrder($payment->order_id, $saveOrderData);

                // dispatch job to create order fulfilment
                CreateOrderFulfillment::dispatch($payment->order);
            }

            $this->data['result']['payment'] = $processPaymentResponse['result']['payment'];
            $this->data['success'] = $processPaymentResponse['success'];
            $this->data['status_code'] = Response::HTTP_OK;
            $this->data['message'] = $processPaymentResponse['message'];

            $this->resultResponse = new ApiResponseCollection($this->data);
    
        } catch (ItemFulfillmentException $e) {
            // when product fulfillmet is in sync mode, we will get this exception
            $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $e->getMessage());
  
        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
