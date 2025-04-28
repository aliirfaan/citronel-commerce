<?php

namespace aliirfaan\CitronelCommerce\Services\Payment;

use Illuminate\Support\Str;
use Carbon\Carbon;
use aliirfaan\CitronelErrorCatalogue\Services\CitronelErrorCatalogueService;
use aliirfaan\CitronelCommerce\Models\Payment\Payment;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\CitronelErrorCatalogue\Traits\ErrorCatalogue;
use aliirfaan\CitronelCommerce\Enums\Payment\PaymentStatus;
use aliirfaan\CitronelCommerce\Events\Payment\PaymentProcessed;
use aliirfaan\CitronelCommerce\Services\Order\OrderMediatorService;
use aliirfaan\CitronelCommerce\Traits\Payment\PaymentServiceManualConfirmationTrait;

class CitronelPaymentService
{
    use ErrorCatalogue, PaymentServiceManualConfirmationTrait;

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
     * orderMediatorService
     *
     * @var mixed
     */
    public $orderMediatorService;

    /**
     * Method __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->paymentModel = new Payment();
        $this->auditService = new AuditLogService();

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);

        $this->errorCatalogueService = new CitronelErrorCatalogueService();
        
        $this->orderMediatorService = new OrderMediatorService();

        $this->mainProcess = $this->errorCatalogueService->getMainProcess('payment');
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
            'subtotal' => (string) $order->order_subtotal,
            'grand_total' => (string) $order->order_grand_total,
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
        $subProcess = $this->mainProcess['sub_process']['process'];

        $paymentStatus = $payment->payment_status;
        $wasPaymentProcessed = $this->wasPaymentProcessed($paymentStatus);

        $previouslyUpdatedByDifferentChannel = array_key_exists('previously_updated_by_different_channel', $gatewayProcessingResponse) ? $gatewayProcessingResponse['previously_updated_by_different_channel'] : false;
        
        if (!$previouslyUpdatedByDifferentChannel && !$wasPaymentProcessed) {

            $savePaymentData = [
                'payment_status' => $gatewayProcessingResponse['status'],
                'payment_channel' => $channel,
                'paid_at' => array_key_exists('paid_at', $gatewayProcessingResponse) ? $gatewayProcessingResponse['paid_at'] : null,
                'cancelled_at' => array_key_exists('cancelled_at', $gatewayProcessingResponse) ? $gatewayProcessingResponse['cancelled_at'] : null,
            ];

            $savePaymentData = \array_merge($savePaymentData, $gatewayResponseFields);

            // for some gateways, we do not receive callback, hence request array to gateway response mapping is blank
            if (array_key_exists('payment_data', $gatewayProcessingResponse)) {
                $savePaymentData = \array_merge($savePaymentData, $gatewayProcessingResponse['payment_data']);
            }

            $this->paymentModel::where('id', $payment->id)->update(
                $savePaymentData
            );
        }

        $payment = $this->paymentModel::where('id', $payment->id)->first();

        // log
        $auditData = $this->auditService->generatePreliminaryAuditData(null, $correlationToken);
        $auditData['al_target_id'] = $payment->id;
        $auditData['al_action_type'] = config('audit.action_types.update.name');
        $auditData['al_event_name'] = $subProcess['name'];
        $auditData['al_is_success'] = 0;

        $paymentMessageExtra = array_merge($extra,
        ['gateway_response_message' => $payment->gateway_response_message]);
        switch ($payment->payment_status) {
            case PaymentStatus::PAID->value:
                $data['message'] = $paymentGatewayService->successPaymentMessage($paymentMessageExtra);
                break;
            case PaymentStatus::CANCELLED->value:
                $data['errors'] = true;
                $data['message'] = $paymentGatewayService->cancelPaymentMessage($paymentMessageExtra);
                break;
            case PaymentStatus::FAILED->value:
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
        $data['result']['should_update_order'] = $this->shouldUpdateOrderAfterPaymentProcessed($payment);

        $auditData['al_message'] = $data['message'];
        $auditData['al_response'] = json_encode($gatewayResponseFields);
        
        PaymentProcessed::dispatch($auditData);

        return $data;
    }

    public function mapOrderStatusFromPaymentStatus($paymentStatus)
    {
        return $this->orderMediatorService->mapOrderStatusFromPaymentStatus($paymentStatus);
    }

    /**
     * Method generatePaymentGuid
     *
     * @return string
     */
    public function generatePaymentGuid()
    {
        return (string) Str::uuid();
    }

    public function updatePaymentValidationRules()
    {
        return [
            'payment_channel' => 'required'
        ];
    }

    /**
     * Method validatePaymentForProcessing
     *
     * Check if payment was already processed
     * If payment was already processed, return a message
     *
     * @param mixed $payment [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function validatePaymentForProcessing($payment, $extra = [])
    {
        $data = $this->helperService->returnFormat();
        
        $wasPaymentProcessed = $this->wasPaymentProcessed($payment->payment_status);
        if ($wasPaymentProcessed) {
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::payment/messages.payment_already_processed');
        }

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }

    public function getActorPaymentsWithOrderItems(string $actorId, string $gatewayMerchantTransactionNo = null, string $orderNumber = null)
    {
        $actorPayments = $this->paymentModel
            ->orderBy('updated_at', 'desc')
            ->whereRelation('order', 'actor_id', $actorId);

        return $actorPayments->select('order_id', 'payment_method_configuration_id', 'payment_status', 'gateway_merchant_transaction_no', 'currency_code', 'grand_total', 'paid_at', 'updated_at as payment_updated_at')
         ->with(
            'payment_method_configuration:id,payment_method_id',
            'payment_method_configuration.payment_method:id,title',
            'order:id,order_guid,order_number,created_at as order_created_at',
            'order.order_items:id,order_id,product_id,bundle_code,quantity,order_item_meta',
            'order.order_items.product',
            'order.order_items.order_fulfillments:id,order_item_id,fulfilled_at,order_item_fulfillment_status'
        )
        ->whereHas('order', function ($query) {
            $query->where('created_at', '>=', Carbon::now()->subYear());
        });
    }

    /**
     * Method getLastPaymentToVerifyForOrder
     *
     * For safety. ensure last payment has the same grand total and currency code as the order
     * if last payment status is not unpaid, return the last payment
     *
     * @param mixed $order [explicite description]
     *
     * @return mixed
     */
    public function getLastPaymentToVerifyForOrder($order)
    {
        $data = $this->helperService->returnFormat();

        $payment = $this->paymentModel::where('order_id', $order->id)
            ->where('grand_total', $order->order_grand_total)
            ->where('currency_code', $order->order_currency_code)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!is_null($payment) && $payment->payment_status === PaymentStatus::UNPAID->value) {
            $data['result'] = $payment;
        } else {
            $data['errors'] = true;
        }

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }

    /**
     * Method verifyLastPaymentForOrder
     * Before creating a new payment, check if the last payment for the order was successful at gateway but not on our side
     * This check is to prevent duplicate payments
     *
     * @return array
     */
    public function verifyLastPaymentForOrder($order, $extra = [], $channel = 'retrieve_order')
    {
        $data = $this->helperService->returnFormat();

        $getLastPaymentToVerifyForOrderResponse = $this->getLastPaymentToVerifyForOrder($order);
        $lastPaymentForOrder = $getLastPaymentToVerifyForOrderResponse['result'];

        // no last payment, so we cannot verify
        if (is_null($lastPaymentForOrder)) {
            $data['errors'] = true;
        }

        if (is_null($data['errors'])) {
            $lastPaymentGatewayService = $this->helperService->makeObject($lastPaymentForOrder->payment_method_configuration->payment_class, ['paymentMethodConfigurationId' => $lastPaymentForOrder->payment_method_configuration->id]);

            $verifyGatewayOrderResponse = $lastPaymentGatewayService->verifyGatewayOrder($lastPaymentForOrder);
            if (!$verifyGatewayOrderResponse['success']) {
                $data['errors'] = true;
            }

             if (is_null($data['errors'])) {

                $paymentResult = $verifyGatewayOrderResponse['result']['payment'];

                $savePaymentData = [
                    'payment_status' => array_key_exists('payment_status', $paymentResult) ? $paymentResult['payment_status'] : null,
    
                    'payment_channel' => $channel,
    
                    'gateway_transaction_no' => array_key_exists('gateway_transaction_no', $paymentResult) ? $paymentResult['gateway_transaction_no'] : null,
    
                    'gateway_response_code' => array_key_exists('gateway_response_code', $paymentResult) ? $paymentResult['gateway_response_code'] : null,
    
                    'gateway_response_status' => array_key_exists('gateway_response_status', $paymentResult) ? $paymentResult['gateway_response_status'] : null,
    
                    'gateway_response_message' => array_key_exists('gateway_response_message', $paymentResult) ? $paymentResult['gateway_response_message'] : null,
    
                    'paid_at' => array_key_exists('paid_at', $paymentResult) ? $paymentResult['paid_at'] : null,
                ];
                $this->paymentModel::where('id', $lastPaymentForOrder->id)->update(
                    $savePaymentData
                );

                $payment = $this->paymentModel::where('id', $lastPaymentForOrder->id)->first();

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

                if ($data['errors'] == null) {
                    $data['success'] = true;

                    $replace = [
                        'amount' => $payment->grand_total,
                        'payment_reference' => $payment->gateway_merchant_transaction_no,
                        'payment_method' => $payment->payment_method_configuration->payment_method->title,
                    ];
                    $data['message'] =  __('citronel-commerce::payment/messages.previous_payment_process_success', $replace);
                }

                $data['result']['payment'] = $payment;
                $data['result']['should_update_order'] = $this->shouldUpdateOrderAfterPaymentProcessed($payment);
            }
        }

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }

    /**
     * Method shouldVerifyLastPaymentBeforeCreate
     * before creating a new payment, check if the last payment is paid at gateway in case we did not receive callback
     *
     * @return bool
     */
    public function shouldVerifyLastPaymentBeforeCreate()
    {
        return intval(config('citronel-payment.verify_last_payment_before_create'));
    }
    
    /**
     * Method paymentCreatePreprocess
     *
     * @param $order $order [explicite description]
     * @param $extra $extra [explicite description]
     *
     * @return array
     */
    public function paymentCreatePreprocess($order, $extra = [])
    {
        $data = $this->helperService->returnFormat();

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }
          
        return $data;
    }

    public function shouldUpdateOrderAfterPaymentProcessed($payment)
    {
        $shouldUpdateOrder = true;

        if ($payment->order->order_status == $this->orderMediatorService->orderStatus::PAID->value) {
            $shouldUpdateOrder = false;
        }

        return $shouldUpdateOrder;
    }
}
