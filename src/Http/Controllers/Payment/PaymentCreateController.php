<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentMethodService;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentService;
use aliirfaan\CitronelCommerce\Jobs\Order\CreateOrderFulfillment;

class PaymentCreateController extends PaymentController
{
    public function create(Request $request, $order_guid, AuditLogService $auditService, CitronelOrderService $orderService, CitronelPaymentMethodService $paymentMethodService, CitronelPaymentService $paymentService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $subProcess = $this->errorCatalogueService->getSubProcess($this->mainProcess['key'], 'create');

        $this->actor = $request->get('actor', null);

        $this->auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $this->auditData['al_event_name'] = $subProcess['name'];

        try {
            $subProcessKey = $subProcess['key'];
            
            $getOrderResponse = $orderService->getOrderByGuid($order_guid);
            if (!$getOrderResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('payment', 'create', 'invalid_order');
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
                $subProcessErrorKey = $subProcess['events']['expired_order']['key'];
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->auditData['al_event_name'] = $subProcess['events']['expired_order']['name'];
                $this->auditData['al_is_success'] = $checkOrderExpiryResponse['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $order->id;
                AuditLogged::dispatch($this->auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $checkOrderExpiryResponse['message'], ['code' => $code['code']]);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $validateOrderForPaymentResponse = $orderService->validateOrderForPayment($order);
            if (!$validateOrderForPaymentResponse['success']) {
                $subProcessErrorKey = $subProcess['events']['invalid_order_for_payment']['key'];
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->auditData['al_event_name'] = $subProcess['events']['invalid_order_for_payment']['name'];
                $this->auditData['al_is_success'] = $validateOrderForPaymentResponse['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $order->id;
                AuditLogged::dispatch($this->auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $validateOrderForPaymentResponse['message'], ['code' => $code['code']]);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            // validate payment method
            $getPaymentMethodConfigurationResponse = $paymentMethodService->getPaymentMethodConfigurationById($order->order_payment_method_configuration_id);
            if (!$getPaymentMethodConfigurationResponse['success']) {
                $subProcessErrorKey = $subProcess['events']['invalid_payment_method']['key'];
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $this->auditData['al_is_success'] = $this->data['success'];
                $this->auditData['al_event_name'] = $subProcess['events']['invalid_payment_method']['name'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $order_guid;
                $this->auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($this->auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $paymentMethodConfiguration = $getPaymentMethodConfigurationResponse['result'];

            // load payment method class
            $paymentInterfaceObj = $this->helperService->makeObject($paymentMethodConfiguration->payment_class, ['paymentMethodConfigurationId' => $paymentMethodConfiguration->id]);

            // validate currency for this payment method
            $isCurrencyAllowedResponse = $paymentInterfaceObj->isCurrencyAllowed($order->order_currency_code);
            if (!$isCurrencyAllowedResponse['success']) {
                $subProcessErrorKey = $subProcess['events']['invalid_currency']['key'];
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->auditData['al_event_name'] = $subProcess['events']['invalid_currency']['name'];
                $this->auditData['al_is_success'] = $isCurrencyAllowedResponse['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $order->id;
                AuditLogged::dispatch($this->auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $isCurrencyAllowedResponse['message'], ['code' => $code['code']]);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            // validate amount for this payment method
            $validatePaymentMethodAmountResponse = $paymentInterfaceObj->validateTransactionAmount($order->order_grand_total);
            if (!$validateOrderForPaymentResponse['success']) {
                $subProcessErrorKey = $subProcess['events']['invalid_amount']['key'];
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->auditData['al_event_name'] = $subProcess['events']['invalid_amount']['name'];
                $this->auditData['al_is_success'] = $validatePaymentMethodAmountResponse['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $order->id;
                AuditLogged::dispatch($this->auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $validatePaymentMethodAmountResponse['message'], ['code' => $code['code']]);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            // verify if last payment was accepted at gateway but not processed on platform
            if ($paymentService->shouldVerifyLastPaymentBeforeCreate()) {
                $verifyLastPaymentResponse = $paymentService->verifyLastPaymentForOrder($order);
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

            // create payment
            $paymentRemarks = $orderService->generateOrderPaymentDescription($order);
            $createPaymentExtra = [
                'payment_interface_obj' => $paymentInterfaceObj,
                'payment_remarks' => $paymentRemarks,
            ];
            $createPaymentResponse = $paymentService->createPayment($order, $createPaymentExtra);
            $payment = $createPaymentResponse['result'];

            // create payment gateway order, some payment gateways require us to first register an order
            $registerGatewayOrderData = [
                'actor' => $this->actor,
                'payment' => $payment
            ];
            $registerGatewayOrderResponse = $paymentInterfaceObj->registerGatewayOrder($payment, $registerGatewayOrderData);

            if (!$registerGatewayOrderResponse['success']) {
                $subProcessErrorKey = $subProcess['events']['register_gateway_order_failure']['key'];
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->auditData['al_event_name'] = $subProcess['events']['register_gateway_order_failure']['name'];
                $this->auditData['al_is_success'] = $registerGatewayOrderResponse['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_message'] = $registerGatewayOrderResponse['message'];
                AuditLogged::dispatch($this->auditData);

                $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $registerGatewayOrderResponse['message']);

                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $paymentGatewayOrder = $registerGatewayOrderResponse['result'];

            // update payment
            if (!is_null($registerGatewayOrderResponse['result']['payment_data'])) {
                $savePaymentData = $registerGatewayOrderResponse['result']['payment_data'];
                $paymentService->updatePayment($payment->id, $savePaymentData);
            }

            $formattedResult['payment'] = $payment;
            $formattedResult['payment_gateway_configuration'] = array_merge(
                $paymentInterfaceObj->getMappedConfigurations(),
                $paymentGatewayOrder['leg_data'] // make sure this is always an array
            );

            $this->data['result'] = $formattedResult;
            $this->data['success'] = true;
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);
            
        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
