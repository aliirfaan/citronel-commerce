<?php

namespace aliirfaan\CitronelCommerce\Services\Payment;

use Illuminate\Support\Str;
use aliirfaan\CitronelErrorCatalogue\Services\CitronelErrorCatalogueService;
use aliirfaan\CitronelCommerce\Services\Helper\CitronelCommerceHelperService;
use aliirfaan\CitronelCommerce\Models\Payment\Payment;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\CitronelErrorCatalogue\Traits\ErrorCatalogue;
use aliirfaan\CitronelCommerce\Enums\Payment\PaymentStatus;

// @todo
class CitronelPaymentService
{
    use ErrorCatalogue;

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
     * helperService
     *
     * @var mixed
     */
    public $helperService;

    /**
     * mainProcess for error catalogue mapping
     *
     * @var string
     */
    public $mainProcess;

    /**
     * errorCatalogueService
     *
     * @var mixed
     */
    public $errorCatalogueService;

    /**
     * Method __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->paymentModel = new Payment();
        $this->auditService = new AuditLogService();
        $this->helperService = new CitronelCommerceHelperService();
        $this->errorCatalogueService = new CitronelErrorCatalogueService();
        $this->mainProcess = 'payment_service';
    }

    /**
     * Method createPayment
     *
     * @param mixed $order [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function createPayment($order, $extra = [])
    {
        $data = $this->helperService->returnFormat();

        $paymentSaveData = [
            'payment_guid' => $this->generatePaymentGuid(),
            'order_id' => $order->id,
            'payment_method_configuration_id' => $order->order_payment_method_configuration_id,
            'payment_status' => PaymentStatus::UNPAID,
            'currency_code' => $order->order_currency_code,
            'subtotal' => floatval($order->order_subtotal),
            'grand_total' => floatval($order->order_grand_total),
            'payment_remarks' => array_key_exists('payment_remarks', $extra)? $extra['payment_remarks'] : $order->order_number,
        ];

        $newPayment = $this->paymentModel::create($paymentSaveData);

        $paymentInterfaceObj = $extra['payment_interface_obj'];
        $gatewayMerchantTransactionNo = $paymentInterfaceObj->generateGatewayMerchantTransactionNo($newPayment->id);

        // update payment
        $updatePaymentSaveData = [
            'gateway_merchant_transaction_no' => $gatewayMerchantTransactionNo,
        ];
        $this->paymentModel::where('id', $newPayment->id)->update($updatePaymentSaveData);

        $payment = $this->paymentModel->where('id', $newPayment->id)->first();

        $data['result'] = $payment;
        $data['success'] = true;

        return $data;
    }

    /**
     * Method updatePayment
     *
     * @param $paymentId $paymentId [explicite description]
     * @param $saveData $saveData [explicite description]
     *
     * @return array
     */
    public function updatePayment($paymentId, $saveData)
    {
        return $this->paymentModel::where('id', $paymentId)->update($saveData);
    }

    /**
     * Method getPayment
     *
     * @param $paymentId $paymentId [explicite description]
     *
     * @return array
     */
    public function getPayment($paymentId)
    {
        $data = $this->helperService->returnFormat();
        $subProcess = $this->mainProcess['sub_process']['get_payment'];

        $payment = $this->paymentModel::where('id', $paymentId)->first();
        if (!is_null($payment)) {
            $data['result'] = $payment;
            $data['success'] = true;
        } else {
            $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcess['key'], null, $this->recordNotFoundErrorCatalogue()['code']);
            $data['message'] = __($this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);
        }

        return $data;
    }

    public function getPaymentByMerchantGatewayTxNum($gatewayMerchantTxNum)
    {
        $data = $this->helperService->returnFormat();

        $result = $this->paymentModel::where('gateway_merchant_transaction_no', $gatewayMerchantTxNum)->first();
        if (is_null($result)) {
          $data['errors'] = true;
        }

        if (is_null($data['errors'])) {
          $data['result'] = $result;
          $data['success'] = true;
        }
        
        return $data;
    }

    /**
     * Method wasPaymentProcessed
     *
     * @param $paymentStatus $paymentStatus [explicite description]
     *
     * @return boolean
     */
    public function wasPaymentProcessed($paymentStatus)
    {
        if ($paymentStatus !== PaymentStatus::UNPAID->value) {
            return true;
        }

        return false;
    }

    /**
     * Method processPayment
     *
     * We may get payment update information later in cases where we are checking payment status at a later stage. So payment date is not necessarily dateNow()
     *
     * @param mixed $payment [explicite description]
     * @param mixed $paymentGatewayService [explicite description]
     * @param $gatewayResponseFields $gatewayResponseFields [explicite description]
     * @param array $gatewayProcessingResponse [explicite description]
     * @param $channel $channel [explicite description]
     * @param $correlationToken $correlationToken [explicite description]
     *
     * @return array
     */
    public function processPayment($payment, $paymentGatewayService, $gatewayResponseFields, $gatewayProcessingResponse, $channel = 'web_callback', $correlationToken = null, $extra = [])
    {
        $data = $this->helperService->returnFormat();
        $subProcess = config('error-catalogue.process.payment.sub_process.process');
        $subProcessKey = $subProcess['key'];
        $shouldUpdateOrder = false;

        $paymentStatus = $payment->payment_status;
        $wasPaymentProcessed = $this->wasPaymentProcessed($paymentStatus);

        $previouslyUpdatedByDifferentChannel = array_key_exists('previously_updated_by_different_channel', $gatewayProcessingResponse) ? $gatewayProcessingResponse['previously_updated_by_different_channel'] : false;
        
        if (!$previouslyUpdatedByDifferentChannel && !$wasPaymentProcessed) {
            $shouldUpdateOrder = true;

            $savePaymentData = [
                'payment_status' => $gatewayProcessingResponse['status'],
                'payment_channel' => $channel
            ];
            if ($gatewayProcessingResponse['status'] == config('payment.payment_status.paid.status')) {
                $savePaymentData['paid_at'] = $gatewayProcessingResponse['paid_at'];
            }
            $savePaymentData = \array_merge($savePaymentData, $gatewayResponseFields);

            $this->paymentApiCommand::where('id', $payment->id)->update(
                $savePaymentData
            );
        }

        $payment = $this->paymentApiQuery::where('id', $payment->id)->first();

        // log
        $auditData = $this->auditService->generatePreliminaryEventData(null, $correlationToken);
        $auditData['al_target_id'] = $payment->id;
        $auditData['al_action_type'] = config('audit.action_types.update.name');
        $auditData['al_event_name'] = $subProcess['name'];
        $auditData['al_is_success'] = 0;

        $paymentMessageExtra = array_merge($extra,
        ['gateway_response_message' => $payment->gateway_response_message]);
        switch ($payment->payment_status) {
            case config('payment.payment_status.paid.status'):
                $data['message'] = $paymentGatewayService->successPaymentMessage($paymentMessageExtra);
                break;
            case config('payment.payment_status.cancelled.status'):
                $data['errors'] = true;
                $data['message'] = $paymentGatewayService->cancelPaymentMessage($paymentMessageExtra);
                break;
            case config('payment.payment_status.failed.status'):
            default:
                $data['errors'] = true;
                $data['message'] = $paymentGatewayService->failedPaymentMessage($paymentMessageExtra);
                break;
        }

        if ($data['errors'] == null) {
            $data['success'] = true;
            $auditData['al_is_success'] = 1;
        }
        $data['result']['payment'] = $payment;
        $data['result']['should_update_order'] = $shouldUpdateOrder;

        $auditData['al_message'] = $data['message'];
        $auditData['al_response'] = json_encode($gatewayResponseFields);
        
        PaymentProcessed::dispatch($auditData);

        return $data;
    }
}

