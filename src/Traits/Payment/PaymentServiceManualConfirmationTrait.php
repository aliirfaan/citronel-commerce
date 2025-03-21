<?php

namespace aliirfaan\CitronelCommerce\Traits\Payment;

use aliirfaan\CitronelCommerce\Enums\Payment\PaymentStatus;
use aliirfaan\CitronelCommerce\Models\Payment\ManualPaymentConfirmation;
use aliirfaan\CitronelCommerce\Events\Payment\PaymentProcessed;

trait PaymentServiceManualConfirmationTrait
{
    /**
     * helperService
     *
     * @var mixed
     */
    public $helperService;

    /**
     * orderMediatorService
     *
     * @var mixed
     */
    public $orderMediatorService;

    /**
     * paymentModel
     *
     * @var mixed
     */
    protected $paymentModel;

    /**
     * auditService
     *
     * @var mixed
     */
    protected $auditService;

    /**
     * errorCatalogueService
     *
     * @var mixed
     */
    public $errorCatalogueService;

    /**
     * Method validatePaymentForManualSuccessConfirmation
     *
     * Check if order status is paid as order have have been paid with another payment
     * Check if payment was already paid
     *
     * @param mixed $payment [explicite description]
     *
     * @return array
     */
    public function validatePaymentForManualConfirmation($payment)
    {
        $data = $this->helperService->returnFormat();

        // check order status
        $orderStatus = $payment->order->order_status;
        if ($orderStatus == $this->orderMediatorService->orderStatus::PAID->value) {
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::order/messages.order_already_paid');
        }

        if (is_null($data['errors'])) {
            $paymentStatus = $payment->payment_status;
            if ($paymentStatus == PaymentStatus::PAID->value) {
                $data['errors'] = true;
                $data['message'] = __('citronel-commerce::payment/messages.manual_payment_update_not_allowed');
            }
        }

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }

    /**
     * Method manuallyConfirmPayment
     *
     * @param mixed $payment [explicite description]
     * @param mixed $paymentGatewayService [explicite description]
     * @param array $extra [explicite description]
     * @param string $channel [explicite description]
     *
     * @return void
     */
    public function manuallyConfirmPayment($payment, $paymentGatewayService, $extra = [], $channel = 'manual')
    {
        $data = $this->helperService->returnFormat();
        $subProcess = $this->errorCatalogueService->getSubProcess('payment', 'manual_payment_update');
        $shouldUpdateOrder = false;

        $manuallyConfirmPaymentResponse = $paymentGatewayService->manuallyConfirmPayment($payment, $extra);
        if (!$manuallyConfirmPaymentResponse['success']) {
            $data['errors'] = true;
            $data['message'] = $manuallyConfirmPaymentResponse['message'];
        }

        if (is_null($data['errors'])) {

            $shouldUpdateOrder = true;

            $confirmPaymentResult = $manuallyConfirmPaymentResponse['result']['payment'];

            $paymentConfirmationSaveData = [
                'id' => (string) Str::uuid(),

                'payment_id' => $payment->id,

                'update_actor_id' => array_key_exists('update_actor_id', $extra) ? $extra['update_actor_id'] : null,

                'update_payment_status' => array_key_exists('payment_status', $confirmPaymentResult) ? $confirmPaymentResult['payment_status'] : null,

                'update_gateway_transaction_no' => array_key_exists('gateway_transaction_no', $confirmPaymentResult) ? $confirmPaymentResult['gateway_transaction_no'] : null,

                'update_gateway_response_code' => array_key_exists('gateway_response_code', $confirmPaymentResult) ? $confirmPaymentResult['gateway_response_code'] : null,

                'update_gateway_response_status' => array_key_exists('gateway_response_status', $confirmPaymentResult) ? $confirmPaymentResult['gateway_response_status'] : null,

                'update_gateway_response_message' => array_key_exists('gateway_response_message', $confirmPaymentResult) ? $confirmPaymentResult['gateway_response_message'] : null,

                'update_paid_at' => array_key_exists('paid_at', $confirmPaymentResult) ? $confirmPaymentResult['paid_at'] : null,

                'manually_updated_at' => date('Y-m-d H:i:s'),

                'update_remarks' => array_key_exists('update_remarks', $extra) ? $extra['update_remarks'] : null,
            ];
            $manualPaymentConfirmationObj = ManualPaymentConfirmation::create($paymentConfirmationSaveData);

            $savePaymentData = [
                'payment_status' => $manualPaymentConfirmationObj->update_payment_status,

                'payment_channel' => $channel,

                'gateway_transaction_no' => $manualPaymentConfirmationObj->update_gateway_transaction_no,

                'gateway_response_code' => $manualPaymentConfirmationObj->update_gateway_response_code,

                'gateway_response_status' => $manualPaymentConfirmationObj->update_gateway_response_status,

                'gateway_response_message' => $manualPaymentConfirmationObj->update_gateway_response_message,

                'paid_at' => $manualPaymentConfirmationObj->update_paid_at,
            ];
            $this->paymentModel::where('id', $payment->id)->update(
                $savePaymentData
            );

            $payment = $this->paymentModel::where('id', $payment->id)->first();

            switch ($payment->payment_status) {
                case PaymentStatus::PAID->value:
                    break;
                case PaymentStatus::CANCELLED->value:
                    $data['errors'] = true;
                    break;
                case PaymentStatus::FAILED->value:
                default:
                    $data['errors'] = true;
                    break;
            }

            // log
            $correlationToken = $payment->order->correlation_token;
            $auditData = $this->auditService->generatePreliminaryAuditData(null, $correlationToken);
            $auditData['al_target_id'] = $payment->id;
            $auditData['al_action_type'] = config('audit.action_types.update.name');
            $auditData['al_event_name'] = $subProcess['name'];
            $auditData['al_is_success'] = 0;

            $data['message'] = __('citronel-commerce::payment/messages.payment_confirmed', ['status' => $payment->payment_status]);

            if (is_null($data['errors'])) {
                $data['success'] = true;
                $auditData['al_is_success'] = 1;
            }
            $data['result']['payment'] = $payment;
            $data['result']['should_update_order'] = $shouldUpdateOrder;
    
            $auditData['al_message'] = $data['message'];
            $auditData['al_response'] = json_encode($manualPaymentConfirmationObj);
            
            PaymentProcessed::dispatch($auditData);
        }

        return $data;
    }

    /**
     * Method validatePaymentForManualProcessing
     * For manual payment, we process payment where status is unpaid or failed also
     *
     * @param mixed $payment [explicite description]
     *
     * @return array
     */
    public function validatePaymentForManualProcessing($payment)
    {
        $data = $this->helperService->returnFormat();

        $paymentStatusArr = [
            PaymentStatus::UNPAID->value,
            PaymentStatus::FAILED->value,
        ];

        if (!in_array($payment->payment_status, $paymentStatusArr)) {
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::payment/messages.payment_already_processed');
        }

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }
}
