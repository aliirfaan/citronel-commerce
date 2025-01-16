<?php

namespace aliirfaan\CitronelCommerce\Services\Order;

use aliirfaan\CitronelErrorCatalogue\Traits\ErrorCatalogue;
use aliirfaan\CitronelCommerce\Models\Order\OrderFulfillment;
use aliirfaan\CitronelCommerce\Services\Helper\CitronelCommerceHelperService;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\CitronelCommerce\Events\Order\FulfillmentProcessed;
use aliirfaan\CitronelCommerce\Events\Order\FulfillmentFailed;
use aliirfaan\CitronelJob\Traits\HasJobPolicy;
use aliirfaan\CitronelCommerce\Services\Product\ProductService;
use aliirfaan\CitronelCommerce\Models\Order\ManualFulfillmentRetry;
use aliirfaan\CitronelCommerce\Enums\Order\OrderStatus;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// @todo to remove
use App\Services\Api\v1\MontyEsim\Order\MontyEsimPlatformOrderHistoryService;

class FulfillmentService
{
    use ErrorCatalogue, HasJobPolicy;

    /**
     * mainProcess
     *
     * @var string
     */
    public $mainProcess;

    /**
     * OrderFulfillmentModel
     *
     * @var mixed
     */
    protected $orderFulfillmentModel;
    
    /**
     * helperService
     *
     * @var mixed
     */
    public $helperService;

    /**
     * auditService
     *
     * @var mixed
     */
    protected $auditService;

    /**
     * productService
     *
     * @var mixed
     */
    protected $productService;

    public function __construct()
    {
        $this->orderFulfillmentModel = new OrderFulfillment();
        $this->auditService = new AuditService();
        $this->helperService = new CitronelCommerceHelperService();
        $this->productService = new ProductService();
        $this->mainProcess = 'order';
    }
    
    /**
     * Method createOrderFulfillment
     *
     * Take order items and create order fulfillment rows so that we can fulfill the order
     * To avoid duplicates, if row exists in fulfillment table, we do not insert
     *
     * @param mixed $order [explicite description]
     *
     * @return array
     */
    public function createOrderFulfillment($order)
    {
        $data = $this->helperService->returnFormat();
        $subProcess = config('error-catalogue.process.order.sub_process.fulfillment');

        if ($this->orderFulfillmentModel->where('order_id', $order->id)->exists()) {
            return;
        }

        DB::beginTransaction();

        $productTempArray = [];

        $countOrderItems = 1;
        $orderItems = $order->order_items;
        foreach ($orderItems as $anOrderItem) {
            $itemQuantity = $anOrderItem->quantity;
            for ($i = 1; $i <= $itemQuantity; $i++) {

                if (array_key_exists($anOrderItem->product_id, $productTempArray)) {
                    $productInterfaceObj = $productTempArray[$anOrderItem->product_id]['product_class'];
                } else {
                    $productInterfaceObj = $this->helperService->makeObject($anOrderItem->product->product_class, ['product' => $anOrderItem->product]);

                    $productTempArray[$anOrderItem->product_id] = [
                        'product' => $anOrderItem->product,
                        'product_class' => $productInterfaceObj
                    ];
                }

                $itemExtra = [
                    'order_number' => $order->order_number,
                    'order_item_count' => $countOrderItems,
                    'quantity_count' => $i,
                ];

                $createProductOrderFulfillmentItemExtra = $productInterfaceObj->createProductOrderFulfillmentItemExtra($anOrderItem, $itemExtra);

                $createProductOrderFulfillmentItemResponse = $productInterfaceObj->createProductOrderFulfillmentItem($anOrderItem, $createProductOrderFulfillmentItemExtra);

                $orderFulfillmentSaveData = [
                    'order_item_id' => $anOrderItem->id,
                    'customer_id' => $order->customer_id,
                    'order_item_fulfillment_status' => config('order.order_status.created.status'),
                    'order_id' => $order->id,
                    'product_id' => $anOrderItem->product_id,
                    'order_item_meta' => $anOrderItem->order_item_meta,
                    'correlation_token' => $order->correlation_token
                ];
                $orderFulfillmentSaveData = array_merge($orderFulfillmentSaveData, $createProductOrderFulfillmentItemResponse);

                $this->orderFulfillmentModel::create($orderFulfillmentSaveData);
            }

            $countOrderItems++;
        }

        DB::commit();

        $data['success'] = true;

        return $data;
    }
    
    /**
     * Method fulfillItem
     *
     * Fulfill an item
     *
     * @param mixed $item [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function fulfillItem($item, $extra = [])
    {
        $data = $this->helperService->returnFormat();
        $subProcess = config('error-catalogue.process.order.sub_process.fulfillment');
        $subProcessKey = $subProcess['key'];

        $orderItemFulfillmentStatus = $item->order_item_fulfillment_status;

        // check retry
        // first time, retry count = 1
        $isRetry = false;
        $retryCount = null;
        if (array_key_exists('retry_count', $extra)) {
            $retryCount = intval($extra['retry_count']);
            if (intval($retryCount) > 1) {
                $isRetry = true;
            }
        }

        $shouldFullfillItem = $this->shouldFulfillItem($item->order_item_fulfillment_status, $isRetry);
        if ($shouldFullfillItem) {

            $statusProcessing = OrderStatus::PROCESSING->value;
            $this->orderFulfillmentModel::where('id', $item->id)->update(
                ['order_item_fulfillment_status' => $statusProcessing]
            );

            $fulfilledAt = null;
            $productInterfaceObj = $this->helperService->makeObject($item->order_item->product->product_class, ['product' => $item->order_item->product]);

            $fulfillProductOrderItemResponse = $productInterfaceObj->fulfillProductOrderItem($item, $extra);
            if ($fulfillProductOrderItemResponse['success']) {
                $orderItemFulfillmentStatus = OrderStatus::FULFILLED->value;
                $fulfilledAt = date('Y-m-d H:i:s');
            } else {
                $orderItemFulfillmentStatus = OrderStatus::UNFULFILLED->value;

                /**
                 * check if request is the last retry for the job
                 * if retry job is active and if last retry, order status is set to unfulfilled, else order status is processing_retry
                 **/

                $jobPolicyId = 'fulfill_item';
                $jobPolicy = $this->getJobPolicy($jobPolicyId);

                $isLastRetry = false;
                if (array_key_exists('is_last_retry', $extra)) {
                    $isLastRetry = $extra['is_last_retry'];
                }
                if (!is_null($jobPolicy) && !$isLastRetry) {
                    $orderItemFulfillmentStatus = OrderStatus::PROCESSING_RETRY->value;
                }
            }

            $fulfillmentUpdateData = [
                'order_item_fulfillment_status' => $orderItemFulfillmentStatus,
                'fulfilled_at' => $fulfilledAt,
                'retry_count' => $retryCount,
            ];
            $productOrderFulfillmentItemUpdateData = $productInterfaceObj->generateProductOrderFulfillmentItemUpdate($item, $extra);

            $fulfillmentUpdateData = array_merge($fulfillmentUpdateData, $productOrderFulfillmentItemUpdateData);

            $this->orderFulfillmentModel::where('id', $item->id)->update($fulfillmentUpdateData);

            $data['message'] = $fulfillProductOrderItemResponse['message'];
        }

        $item = $this->orderFulfillmentModel::where('id', $item->id)->first();

        // log
        $correlationToken = $item->order_item->order->correlation_token;
        $auditData = $this->auditService->generatePreliminaryEventData(null, $correlationToken);
        $auditData['al_action_type'] = config('audit.action_types.update.name');
        $auditData['al_event_name'] = $subProcess['events']['item_fulfillment_processed']['name'];
        $auditData['al_correlation_id'] = $correlationToken;
        $auditData['al_is_success'] = true;

        // Status can also be processing_retry in case of retry
        switch ($item->order_item_fulfillment_status) {
            case OrderStatus::FULFILLED->value:
                if (is_null($data['message'])) {
                    $data['message'] = $productInterfaceObj->successItemFulfillmentMessage($item, $extra);
                }
                break;
            case OrderStatus::UNFULFILLED->value:
                FulfillmentFailed::dispatch($item);

                if (is_null($data['message'])) {
                    $data['message'] = $productInterfaceObj->failedItemFulfillmentMessage($item, $extra);
                }
                $data['errors'] = true;
                break;
            case OrderStatus::PROCESSING_RETRY->value:
                if (is_null($data['message'])) {
                    $data['message'] = $productInterfaceObj->failedItemFulfillmentMessage($item, $extra);
                }
                $data['errors'] = true;
                break;
        }

        if ($data['errors'] == null) {
            $data['success'] = true;
            $auditData['al_is_success'] = 1;
        }
        $data['result'] = $item;

        $auditData['al_message'] = $data['message'];

        FulfillmentProcessed::dispatch($auditData);

        return $data;
    }

    /**
     * Check status to know if we need to process fulfillment.
     *
     * By default we only process where status is created
     * If isRetry is true, we also process where status is processing_retry
     *
     * @param string $ [explicite description]
     *
     * @return bool
     */
    public function shouldFulfillItem($orderItemFulfillmentStatus, $isRetry = false)
    {
        $statusToCheck = [
            config('order.order_status.created.status'),
        ];

        if ($isRetry) {
            $statusToCheck[] = config('order.order_status.processing_retry.status');
        }

        if (in_array($orderItemFulfillmentStatus, $statusToCheck)) {
            return true;
        }

        return false;
    }
    
    /**
     * Method getFulfillmentsByOrderId
     *
     * @param $orderId $orderId [explicite description]
     *
     * @return mixed
     */
    public function getFulfillmentsByOrderId($orderId, $status = null)
    {
        $result = $this->orderFulfillmentApiQuery->where('order_id', $orderId);
        if (!is_null($status)) {
            $result->where('order_item_fulfillment_status', $status);
        }

        return $result->get();
    }
        
    /**
     * getFulfillmentsByOrderIdWithPaidPayments
     *
     * @param  string $orderId
     * @return Collection
     */
    public function getFulfillmentsByOrderIdWithPaidPayments(string $orderId)
    {
        return $this->orderFulfillmentApiQuery
            ->where('order_id', $orderId)
            ->with([
                'payments' => function($query){
                    $query->where('payments.payment_status', 'paid');
                }])
            ->get();
    }

    public function getFulfillmentById($id)
    {
        $data = $this->helperService->returnFormat();
  
        $result = $this->orderFulfillmentApiQuery->where('id', $id)->first();
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
     * Method getCustomerFulfillmentByProduct
     *
     * @param $customerId $customerId [explicite description]
     * @param $productId $productId [explicite description]
     * @param $status $status [explicite description]
     *
     * @return mixed
     */
    public function getCustomerFulfillmentsByProduct($customerId, $productId, $status = 'fulfilled')
    {
        return $this->orderFulfillmentApiQuery->where('order_fulfillments.customer_id', $customerId)
            ->join('payments', 'payments.order_id', '=', 'order_fulfillments.order_id')
            ->where('order_fulfillments.product_id', $productId)
            ->where('order_fulfillments.order_item_fulfillment_status', $status)
            ->where('payments.payment_status', 'paid')
            ->orderBy('order_fulfillments.fulfilled_at', 'desc')
            ->select(
                'order_fulfillments.*',
                'payments.gateway_merchant_transaction_no',
            );
    }
    
    /**
     * Method getCustomerPendingFulfillmentsCount
     *
     * Get fulfillment count for a customer where status is processing_retry
     * and created_at is within the last x seconds
     * This can be used to block order creation if customer has pending fulfillments
     *
     * @param string $customerId [explicite description]
     * @param int $seconds [explicite description]
     *
     * @return int
     */
    public function getCustomerPendingFulfillmentsCount($customerId, $seconds)
    {
        $status = config('order.order_status.processing_retry.status');
        $timeLimit = Carbon::now()->subSeconds(intval($seconds));

        return $this->orderFulfillmentApiQuery->where('order_fulfillments.customer_id', $customerId)
            ->where('order_fulfillments.order_item_fulfillment_status', $status)
            ->where('order_fulfillments.created_at', '>=', $timeLimit)
            ->count();
    }
    
    /**
     * Method getUnprocessedFulfillmentCountForOrder
     *
     * Get count of items that still needs to be processed
     *
     * @param mixed $orderId [explicite description]
     *
     * @return int
     */
    public function getUnprocessedFulfillmentCountForOrder($orderId)
    {
        $statusToCheck = [
            config('order.order_status.created.status'),
            config('order.order_status.processing.status'),
            config('order.order_status.processing_retry.status')
        ];

        return $this->orderFulfillmentApiQuery
        ->where('order_id', $orderId)
        ->whereIn('order_item_fulfillment_status', $statusToCheck)->count();
    }
    
    /**
     * Method getSuccessPaymentForOrder
     *
     * @param string $orderId [explicite description]
     *
     * @return mixed
     */
    public function getSuccessPaymentForOrder($orderId)
    {
        return $this->orderFulfillmentApiQuery
            ->where('order_fulfillments.order_id', $orderId)
            ->join('payments', 'payments.order_id', '=', 'order_fulfillments.order_id')
            ->join('payment_method_configurations', 'payments.payment_method_configuration_id', '=', 'payment_method_configurations.id')
            ->join('payment_methods', 'payment_methods.id', '=', 'payment_method_configurations.payment_method_id')
            ->where('payments.payment_status', config('payment.payment_status.paid.status'))
            ->select(
                'payments.gateway_merchant_transaction_no',
                'payments.paid_at',
                'payment_methods.title',
            )
            ->first();
    }

    /**
     * Method fulfillItem
     *
     * Fulfill an item manually
     * It is possible that order has been fulfilles at supplier side but we did not get the response:
     * - check if order has been fulfilled at supplier side
     * - if order has been fulfilled at supplier side, fulfill item
     * - else continue with manual fulfillment by calling product manual fulfillment method
     *
     * @param mixed $item [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function manuallyFulfillItem($item, $extra = [])
    {
        $data = $this->helperService->returnFormat();
        $subProcess = config('error-catalogue.process.order.sub_process.fulfillment');
        $subProcessKey = $subProcess['key'];

        try {
            $productInterfaceObj = null;

            $validateProductForManualFulfillmentResponse = $this->productService->validateProductForManualFulfillment($item->order_item->product);
            if (!$validateProductForManualFulfillmentResponse['success']) {
                $data = $validateProductForManualFulfillmentResponse;
            }

            $retryCount = intval($item->retry_count); // number of times retry has been attempted for this item, both manual and auto retries
            if (is_null($data['errors']) && ($retryCount >= intval($item->order_item->product->max_retry_count))) {
                $data['errors'] = true;
                $data['message'] = __('order/messages.order_item_fulfillment_max_retry_reached');
            }

            if (is_null($data['errors'])) {
                $shouldFullfillItem = $this->shouldFulfillItemManually($item->order_item_fulfillment_status);
                if (!$shouldFullfillItem) {
                    $data['errors'] = true;
                    $data['message'] = __('order/messages.order_item_fulfillment_retry_not_allowed');
                }
            }

            if (is_null($data['errors'])) {
                $retrySaveData = [
                    'id' => (string) Str::uuid(),
                    'order_fulfillment_id' => $item->id,
                    'retry_user_id' => array_key_exists('retry_user_id', $extra) ? $extra['retry_user_id'] : null,
                    'retry_fulfillment_status' => config('order.order_status.created.status'),
                    'retried_at' => date('Y-m-d H:i:s')
                ];
                $manualRetryObj = ManualRetryApiCommand::create($retrySaveData);

                $statusProcessing = config('order.order_status.processing.status');
                $retryCount = $retryCount + 1;
                $this->orderFulfillmentApiCommand::where('id', $item->id)->update([
                    'order_item_fulfillment_status' => $statusProcessing
                ]);

                $fulfilledAt = null;
                $supplierOrderId = null;
                $iccid = null;
                $result_code = null;
                $result_message = null;

                $getSupplierOrderForManualFulfillmentResponse = $this->getSupplierOrderForManualFulfillment($item, $extra);
                if ($getSupplierOrderForManualFulfillmentResponse['success']) {
                    $supplierOrder = $getSupplierOrderForManualFulfillmentResponse['result'];

                    $orderItemFulfillmentStatus = config('order.order_status.unfulfilled.status');
                    if ($supplierOrder['order_status'] === 'Successful') {
                        $orderItemFulfillmentStatus = config('order.order_status.fulfilled.status');
                        $fulfilledAt = date('Y-m-d H:i:s');
                    }

                    $supplierOrderId = array_key_exists('order_id', $supplierOrder) ? $supplierOrder['order_id'] : null;
                    $iccid = array_key_exists('iccid', $supplierOrder) ? $supplierOrder['iccid'] : null;

                    $data['message'] = array_key_exists('order_status', $supplierOrder) ? $supplierOrder['order_status'] : null;
                } else {
                    $productInterfaceObj = $this->helperService->makeObject($item->order_item->product->product_class, ['product'=> $item->order_item->product]);

                    $fulfillProductOrderItemResponse = $productInterfaceObj->fulfillProductOrderItem($item, $extra);
                    if ($fulfillProductOrderItemResponse['success']) {
                        $orderItemFulfillmentStatus = config('order.order_status.fulfilled.status');
                        $fulfilledAt = date('Y-m-d H:i:s');
                    } else {
                        $orderItemFulfillmentStatus = config('order.order_status.unfulfilled.status');
                    }
    
                    if (!is_null($fulfillProductOrderItemResponse['result']) && array_key_exists('data', $fulfillProductOrderItemResponse['result'])) {
                        $supplierOrderId = array_key_exists('order_id', $fulfillProductOrderItemResponse['result']['data']) ? $fulfillProductOrderItemResponse['result']['data']['order_id'] : null;
    
                        $iccid = array_key_exists('iccid', $fulfillProductOrderItemResponse['result']['data']) ? $fulfillProductOrderItemResponse['result']['data']['iccid'] : null;
    
                        $result_code = array_key_exists('response_code', $fulfillProductOrderItemResponse['result']['data']) ? $fulfillProductOrderItemResponse['result']['data']['response_code'] : null;
    
                        $result_message = array_key_exists('message', $fulfillProductOrderItemResponse['result']['data']) ? $fulfillProductOrderItemResponse['result']['data']['message'] : null;
                    }

                    $data['message'] = $fulfillProductOrderItemResponse['message'];
                }

                $retrySaveData = [
                    'retry_fulfillment_status' => $orderItemFulfillmentStatus
                ];
                ManualRetryApiCommand::where('id', $manualRetryObj->id)->update(
                    $retrySaveData
                );

                $this->orderFulfillmentApiCommand::where('id', $item->id)->update(
                    [
                        'order_item_fulfillment_status' => $orderItemFulfillmentStatus,
                        'fulfilled_at' => $fulfilledAt,
                        'retry_count' => $retryCount,
                        'supplier_order_id' => $supplierOrderId,
                        'iccid' => $iccid,
                        'result_code' => $result_code,
                        'result_message' => $result_message
                    ]
                );

                $item = $this->orderFulfillmentApiCommand::where('id', $item->id)->first();

                switch ($item->order_item_fulfillment_status) {
                    case config('order.order_status.fulfilled.status'):
                        if (is_null($data['message'])) {
                            $data['message'] = $productInterfaceObj->successItemFulfillmentMessage($item, $extra);
                        }
                        break;
                    case config('order.order_status.unfulfilled.status'):
                        if (is_null($data['message'])) {
                            $data['message'] = $productInterfaceObj->failedItemFulfillmentMessage($item, $extra);
                        }
                        $data['errors'] = true;
                        break;
                }
            }

            // log
            $correlationToken = $item->order_item->order->correlation_token;
            $auditData = $this->auditService->generatePreliminaryEventData(null, $correlationToken);
            $auditData['al_action_type'] = config('audit.action_types.update.name');
            $auditData['al_event_name'] = $subProcess['events']['item_fulfillment_processed']['name'];
            $auditData['al_correlation_id'] = $correlationToken;
            $auditData['al_is_success'] = $data['success'];

            if ($data['errors'] == null) {
                $data['success'] = true;
                $auditData['al_is_success'] = $data['success'];
            }
            $data['result'] = $item;

            $auditData['al_message'] = $data['message'];

            FulfillmentProcessed::dispatch($auditData);

        } catch (\Illuminate\Database\QueryException $e) {
            report($e);

            $code = $this->helperService->generateProcessCode($this->mainProcessKey, $subProcessKey, null, $this->databaseErrorCatalogue()['code']);
            $data['message'] = __($this->databaseErrorCatalogue()['lang'], ['code' => $code['code']]);
        }
        
        return $data;
    }

    
    /**
     * Check status to know if we need to process manual fulfillment.
     *
     * @param string $orderItemFulfillmentStatus [explicite description]
     *
     * @return bool
     */
    public function shouldFulfillItemManually($orderItemFulfillmentStatus)
    {
        $statusToCheck = [
            config('order.order_status.unfulfilled.status'),
        ];

        if (in_array($orderItemFulfillmentStatus, $statusToCheck)) {
            return true;
        }

        return false;
    }
    
    /**
     * Get order by order reference from supplier
     * return success only if order exists if we will use it for manual fulfillment
     *
     * @param mixed $item [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function getSupplierOrderForManualFulfillment($item, $extra = [])
    {
        $data = $this->helperService->returnFormat();

        $bodyData = [
            'order_reference' => $item->reseller_order_reference
        ];

        $montyEsimPlatformOrderHistoryService = new MontyEsimPlatformOrderHistoryService();
        $correlationToken = $item->order_item->order->correlation_token;

        $orderHistoryResponse = $montyEsimPlatformOrderHistoryService->orderHistory($correlationToken, $bodyData);
        if (!$orderHistoryResponse['success']) {
            $data['errors'] = true;
            $data['message'] = $orderHistoryResponse['message'];
        }

        if (is_null($data['errors'])) {
            $orderHistoryResult = $orderHistoryResponse['result']['data'];
            if (array_key_exists('orders', $orderHistoryResult) && count($orderHistoryResult['orders']) > 0) {
                $order = $orderHistoryResult['orders'][0];
                $data['result'] = $order;
                $data['success'] = true;
            }
        }

        return $data;
    }
}
