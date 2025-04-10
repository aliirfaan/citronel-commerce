<?php

namespace aliirfaan\CitronelCommerce\Services\Refund;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use aliirfaan\CitronelErrorCatalogue\Traits\ErrorCatalogue;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\CitronelCommerce\Models\Order\OrderItem;
use aliirfaan\CitronelCommerce\Services\Currency\CitronelCurrencyService;
use aliirfaan\CitronelCommerce\Models\Order\OrderFulfillment;
use aliirfaan\CitronelCommerce\Models\PaymentRefund\PaymentRefund;
use aliirfaan\CitronelCommerce\Models\OrderFulfillmentRefund\OrderFulfillmentRefund;
use aliirfaan\CitronelCommerce\Enums\Order\OrderStatus;
use aliirfaan\CitronelCommerce\Enums\Refund\RefundStatus;
use aliirfaan\CitronelCommerce\Enums\Refund\ReturnStatus;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;

class CitronelRefundService
{
    use ErrorCatalogue;

    /**
     * orderApiCommand
     *
     * @var mixed
     */
    protected $orderApiCommand;
    
    /**
     * orderApiQuery
     *
     * @var mixed
     */
    protected $orderApiQuery;
    
    /**
     * orderDetailApiCommand
     *
     * @var mixed
     */
    protected $orderItemApiCommand;

    
    /**
     * orderItemApiQuery
     *
     * @var mixed
     */
    protected $orderItemApiQuery;
    
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
     * mainProcessKey
     *
     * @var string
     */
    public $mainProcessKey;
    
    /**
     * currencyService
     *
     * @var mixed
     */
    private $currencyService;

    /**
     * orderFulfillmentApiQuery
     *
     * @var mixed
     */
    protected $orderFulfillmentApiQuery;

    protected $paymentRefundApiCommand;

    protected $paymentRefundApiQuery;

    protected $orderFulfillmentRefundApiCommand;

    public function __construct(CitronelCurrencyService $currencyService, CitronelOrderService $orderService)
    {
        $this->orderApiCommand = $orderService->orderModel;
        $this->orderApiQuery = $orderService->orderModel;
        $this->orderItemApiCommand = new OrderItem();
        $this->orderItemApiQuery = new OrderItem();
        $this->orderFulfillmentApiQuery = new OrderFulfillment();
        $this->auditService = new AuditLogService();

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);

        $this->currencyService = $currencyService;
        $this->paymentRefundApiCommand = new PaymentRefund();
        $this->paymentRefundApiQuery = new PaymentRefund();
        $this->orderFulfillmentRefundApiCommand = new OrderFulfillmentRefund();
        $this->mainProcessKey = 'refund';
    }

    public function isFullOrderRefundAllowed()
    {
        return config('refund.allow_full_order_refund');
    }

    /**
     * Method getOrderFulfillmentsToRefund
     *
     * If orderFulfillmentIds is empty, return all fulfillments, else return only the specified fulfillments
     *
     * Skip
     * - fulfillments where status is processing or processing_retry
     * - fulfillments that have already been refunded/returned
     *
     * @param mixed $order [explicite description]
     * @param array $orderFulfillmentIds [explicite description]
     *
     * @return mixed
     */
    public function getOrderFulfillmentsToRefund($order, $orderFulfillmentIds = [])
    {
        $processingStatus = [
            OrderStatus::PROCESSING->value,
            OrderStatus::PROCESSING_RETRY->value
        ];

        $result = $this->orderFulfillmentApiQuery
            ->where('order_id', $order->id)
            ->whereNotIn('order_item_fulfillment_status', $processingStatus);

        if (count($orderFulfillmentIds) > 0) {
            $result->whereIn('order_fulfillments.id', $orderFulfillmentIds);
        }

        $result->leftJoin('order_fulfillment_refunds', 'order_fulfillments.id', '=', 'order_fulfillment_refunds.order_fulfillment_id')
            ->whereNull('order_fulfillment_refunds.order_fulfillment_id')
            ->select('order_fulfillments.*');

        return $result->get();
    }

    public function validateOrderFulfillmentsToRefund($orderFulfillments, $extra = [])
    {
        $data = $this->helperService->returnFormat();

        $orderFulfillmentItemsToRefund = [];
        $productTempArray = [];

        foreach ($orderFulfillments as $orderFulfillment) {
            // check fulfillment status, if not fulfilled, add to refund list
            if ($orderFulfillment->order_item_fulfillment_status !== OrderStatus::FULFILLED->value) {
                $orderFulfillmentItemsToRefund[] = $orderFulfillment;
                continue;
            }

            // load product class
            if (array_key_exists($orderFulfillment->order_item->product_id, $productTempArray)) {
                $productInterfaceObj = $productTempArray[$orderFulfillment->order_item->product_id]['product_class'];
            } else {
                $productInterfaceObj = $this->helperService->makeObject($orderFulfillment->order_item->product->product_class, ['product'=> $orderFulfillment->order_item->product]);

                $productTempArray[$orderFulfillment->order_item->product_id] = [
                    'product' => $orderFulfillment->order_item->product,
                    'product_class' => $productInterfaceObj
                ];
            }

            // check if supplier returned item
            $processSupplierOrderFulfillmentItemReturnResponse = $productInterfaceObj->processSupplierOrderFulfillmentItemReturn($orderFulfillment, $extra);
            if (!$processSupplierOrderFulfillmentItemReturnResponse['success']) {
                $data['errors'] = true;
                $data['message'] = $processSupplierOrderFulfillmentItemReturnResponse['message'];
                break;
            }
            $supplierReturnResult = $processSupplierOrderFulfillmentItemReturnResponse['result'];

            if (is_null($data['errors']) && array_key_exists('was_returned', $supplierReturnResult) && $supplierReturnResult['was_returned']) {
                $orderFulfillmentItemsToRefund[] = $orderFulfillment;
            }
        }

        if (empty($orderFulfillmentItemsToRefund)) {
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::refund/messages.refund_no_items');
        }

        if (is_null($data['errors'])) {
            $data['success'] = true;
            $data['result'] = $orderFulfillmentItemsToRefund;
        }

        return $data;
    }

    public function initiateOrderRefund($order, $orderFulfillmentIds = [], $extra = [])
    {
        $data = $this->helperService->returnFormat();

        $getOrderFulfillmentsToRefundResponse = $this->getOrderFulfillmentsToRefund($order, $orderFulfillmentIds);

        if ($getOrderFulfillmentsToRefundResponse->isEmpty()) {
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::refund/messages.refund_no_items');
        }
        
        $orderFulfillmentItemsToRefund = [];
        if (is_null($data['errors'])) {
            $validateOrderFulfillmentsToRefundResponse = $this->validateOrderFulfillmentsToRefund($getOrderFulfillmentsToRefundResponse, $extra);
            if (!$validateOrderFulfillmentsToRefundResponse['success']) {
                $data['errors'] = true;
                $data['message'] = $validateOrderFulfillmentsToRefundResponse['message'];
            } else {
                $orderFulfillmentItemsToRefund = $validateOrderFulfillmentsToRefundResponse['result'];
            }
        }
        
        if (is_null($data['errors'])) {
            // create refund order
            $createOrderRefundInitiationResponse = $this->createOrderRefundInitiation($order, $orderFulfillmentItemsToRefund, $extra);
            if (!$createOrderRefundInitiationResponse['success']) {
                $data['errors'] = true;
                $data['message'] = $createOrderRefundInitiationResponse['message'];
            } else {
                $data['message'] = $createOrderRefundInitiationResponse['message'];
            }
        }
            
        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }

    public function createOrderRefundInitiation($order, $orderFulfillmentItemsToRefund, $extra = [])
    {
        $data = $this->helperService->returnFormat();
    
        DB::beginTransaction();
    
        $paymentRefundSaveData = [
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'ticket_number' => array_key_exists('ticket_number', $extra) ? $extra['ticket_number'] : null,
            'refund_reason' => array_key_exists('reason', $extra) ? $extra['reason'] : null,
            'refund_status' => RefundStatus::CREATED->value,
            'create_actor_id' => array_key_exists('return_actor_id', $extra) ? $extra['return_actor_id'] : null,
            'refund_created_at' => now(),
        ];
    
        $newPaymentRefund = $this->paymentRefundApiCommand->create($paymentRefundSaveData);
    
        $bulkInsertData = [];
        $paymentRefundGrandTotal = '0'; // Initialize as a string for bcmath
    
        foreach ($orderFulfillmentItemsToRefund as $orderFulfillment) {
            // Calculate refund amount using bcmath
            $orderFulfillmentRefundAmount = (string) $this->calculateOrderFulfillmentRefundAmount($orderFulfillment);
    
            // Add refund amount to the grand total using bcmath to avoid precision issues
            $paymentRefundGrandTotal = bcadd($paymentRefundGrandTotal, $orderFulfillmentRefundAmount, config('citronel.decimals'));
    
            $orderFulfillmentRefundSaveData = [
                'id' => (string) Str::uuid(),
                'order_fulfillment_id' => $orderFulfillment->id,
                'payment_refund_id' => $newPaymentRefund->id,
                'return_actor_id' => $newPaymentRefund->create_actor_id,
                'refund_amount' => $orderFulfillmentRefundAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ];
    
            if ($orderFulfillment->order_item_fulfillment_status === OrderStatus::FULFILLED->value) {
                $orderFulfillmentRefundSaveData['return_status'] = ReturnStatus::COMPLETED->value;
                $orderFulfillmentRefundSaveData['returned_at'] = now();
            }
    
            $bulkInsertData[] = $orderFulfillmentRefundSaveData;
        }
    
        // bulk insert
        $this->orderFulfillmentRefundApiCommand->insert($bulkInsertData);
    
        // update payment refund grand total using bcmath
        $newPaymentRefund->refund_grand_total = $paymentRefundGrandTotal;
        $newPaymentRefund->save();
    
        DB::commit();
    
        $data['message'] = __('citronel-commerce::refund/messages.refund_initiated');
    
        if (is_null($data['errors'])) {
            $data['success'] = true;
        }
    
        return $data;
    }

    public function calculateOrderFulfillmentRefundAmount($orderFulfillmentItem)
    {
        // this is always in base currency
        $orderFulfillmentItemRefundAmount = $orderFulfillmentItem->order_item->product_price;

        $orderCurrencyCode = $orderFulfillmentItem->order_item->order->order_currency_code;
        if ($orderCurrencyCode !== $this->currencyService->getBaseCurrencyCode()) {
            $currencyRate = $this->currencyService->getCurrencyRateById($orderFulfillmentItem->order_item->order->currency_rate_id);
            $orderFulfillmentItemRefundAmount = $this->currencyService->convertAmount($orderFulfillmentItemRefundAmount, $orderCurrencyCode, $currencyRate);
        }

        return $orderFulfillmentItemRefundAmount;
    }

    public function getPaymentRefundById($id)
    {
        $data = $this->helperService->returnFormat();
  
        $result = $this->paymentRefundApiQuery::where('id', $id)
        ->where('refund_status', RefundStatus::CREATED->value)
        ->first();
        if (is_null($result)) {
          $data['errors'] = true;
        }

        if (is_null($data['errors'])) {
          $data['result'] = $result;
          $data['success'] = true;
        }
        
        return $data;
    }

    public function updateOrderRefund($paymentRefund, $extra = [])
    {
        $data = $this->helperService->returnFormat();

        $paymentRefundSaveData = [
            'refund_transaction_no' => array_key_exists('refund_transaction_no', $extra) ? $extra['refund_transaction_no'] : null,
            'refund_status' => RefundStatus::REFUNDED->value,
            'update_actor_id' => array_key_exists('update_actor_id', $extra) ? $extra['update_actor_id'] : null,
            'refunded_at' => now(),
        ];

        // update payment refund
        $this->paymentRefundApiCommand::where('id', $paymentRefund->id)->update($paymentRefundSaveData);

        $data['message'] = __('citronel-commerce::refund/messages.refund_updated');

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }
}
