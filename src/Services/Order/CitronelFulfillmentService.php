<?php

namespace aliirfaan\CitronelCommerce\Services\Order;

use aliirfaan\CitronelErrorCatalogue\Traits\ErrorCatalogue;
use aliirfaan\CitronelCommerce\Models\Order\OrderFulfillment;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\CitronelCommerce\Events\Order\FulfillmentProcessed;
use aliirfaan\CitronelCommerce\Events\Order\FulfillmentFailed;
use aliirfaan\CitronelJob\Traits\HasJobPolicy;
use aliirfaan\CitronelCommerce\Services\Product\CitronelProductService;
use aliirfaan\CitronelCommerce\Models\Order\ManualFulfillmentRetry;
use aliirfaan\CitronelCommerce\Enums\Order\OrderStatus;
use aliirfaan\CitronelCommerce\Enums\Payment\PaymentStatus;
use aliirfaan\CitronelErrorCatalogue\Services\CitronelErrorCatalogueService;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CitronelFulfillmentService
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

    protected $errorCatalogueService;

    public function __construct()
    {
        $this->orderFulfillmentModel = new OrderFulfillment();
        $this->auditService = new AuditLogService();

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);

        $this->productService = new CitronelProductService();
        $this->errorCatalogueService = new CitronelErrorCatalogueService();

        $this->mainProcess = $this->errorCatalogueService->getMainProcess('order');
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
        $subProcess = $this->errorCatalogueService->getSubProcess('order', 'fulfillment');

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
                    'actor_id' => $order->actor_id,
                    'order_item_fulfillment_status' => OrderStatus::CREATED->value,
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
        $subProcess = $this->errorCatalogueService->getSubProcess('order', 'fulfillment');
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
                $fulfilledAt = date(config('citronel.db_date_time_db_format'));
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

            $generateProductOrderFulfillmentItemUpdateExtra = $fulfillProductOrderItemResponse['result'];

            $productOrderFulfillmentItemUpdateData = $productInterfaceObj->generateProductOrderFulfillmentItemUpdate($item, $generateProductOrderFulfillmentItemUpdateExtra);

            $fulfillmentUpdateData = array_merge($fulfillmentUpdateData, $productOrderFulfillmentItemUpdateData);

            $this->orderFulfillmentModel::where('id', $item->id)->update($fulfillmentUpdateData);

            $data['message'] = $fulfillProductOrderItemResponse['message'];
        }

        $item = $this->orderFulfillmentModel::where('id', $item->id)->first();

        // log
        $correlationToken = $item->order_item->order->correlation_token;
        $auditData = $this->auditService->generatePreliminaryAuditData(null, $correlationToken);
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

        if (is_null($data['errors'])) {
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
            OrderStatus::CREATED->value
        ];

        if ($isRetry) {
            $statusToCheck[] = OrderStatus::PROCESSING_RETRY->value;
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
        $result = $this->orderFulfillmentModel->where('order_id', $orderId);
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
        return $this->orderFulfillmentModel
            ->where('order_id', $orderId)
            ->with([
                'payments' => function($query){
                    $query->where('payments.payment_status', PaymentStatus::PAID->value);
                }])
            ->get();
    }

    public function getFulfillmentById($id)
    {
        $data = $this->helperService->returnFormat();
  
        $result = $this->orderFulfillmentModel->where('id', $id)->first();
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
     * Method getActorFulfillmentsByProduct
     *
     * @param mixed $actorId [explicite description]
     * @param mixed $productId [explicite description]
     * @param string $status [explicite description]
     *
     * @return mixed
     */
    public function getActorFulfillmentsByProduct($actorId, $productId, $status = 'fulfilled')
    {
        return $this->orderFulfillmentModel->where('order_fulfillments.actor_id', $actorId)
            ->join('payments', 'payments.order_id', '=', 'order_fulfillments.order_id')
            ->where('order_fulfillments.product_id', $productId)
            ->where('order_fulfillments.order_item_fulfillment_status', $status)
            ->where('payments.payment_status', PaymentStatus::PAID->value)
            ->orderBy('order_fulfillments.fulfilled_at', 'desc')
            ->select(
                'order_fulfillments.*',
                'payments.gateway_merchant_transaction_no',
            );
    }
    
    /**
     * Method getActorPendingFulfillmentsCount
     *
     * Get fulfillment count for an actor where status is processing_retry
     * and created_at is within the last x seconds
     * This can be used to block order creation if actor has pending fulfillments
     *
     * @param string $actorId [explicite description]
     * @param int $seconds [explicite description]
     *
     * @return int
     */
    public function getActorPendingFulfillmentsCount($actorId, $seconds)
    {
        $status = OrderStatus::PROCESSING_RETRY->value;
        $timeLimit = Carbon::now()->subSeconds(intval($seconds));

        return $this->orderFulfillmentModel->where('order_fulfillments.actor_id', $actorId)
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
            OrderStatus::CREATED->value,
            OrderStatus::PROCESSING->value,
            OrderStatus::PROCESSING_RETRY->value
        ];

        return $this->orderFulfillmentModel
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
        return $this->orderFulfillmentModel
            ->where('order_fulfillments.order_id', $orderId)
            ->join('payments', 'payments.order_id', '=', 'order_fulfillments.order_id')
            ->join('payment_method_configurations', 'payments.payment_method_configuration_id', '=', 'payment_method_configurations.id')
            ->join('payment_methods', 'payment_methods.id', '=', 'payment_method_configurations.payment_method_id')
            ->where('payments.payment_status', PaymentStatus::PAID->value)
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
     * It is possible that order has been fulfilled at supplier side but we did not get the response:
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
        $subProcess = $this->errorCatalogueService->getSubProcess('order', 'fulfillment');

        $productInterfaceObj = null;

        $validateProductForManualFulfillmentResponse = $this->productService->validateProductForManualFulfillment($item->order_item->product);
        if (!$validateProductForManualFulfillmentResponse['success']) {
            $data = $validateProductForManualFulfillmentResponse;
        }

        $retryCount = intval($item->retry_count); // number of times retry has been attempted for this item, both manual and auto retries
        if (is_null($data['errors']) && ($retryCount >= intval($item->order_item->product->max_retry_count))) {
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::order/messages.order_item_fulfillment_max_retry_reached');
        }

        if (is_null($data['errors'])) {
            $shouldFullfillItem = $this->shouldFulfillItemManually($item->order_item_fulfillment_status);
            if (!$shouldFullfillItem) {
                $data['errors'] = true;
                $data['message'] = __('citronel-commerce::order/messages.order_item_fulfillment_retry_not_allowed');
            }
        }

        if (is_null($data['errors'])) {
            $retrySaveData = [
                'id' => (string) Str::uuid(),
                'order_fulfillment_id' => $item->id,
                'retry_user_id' => array_key_exists('retry_user_id', $extra) ? $extra['retry_user_id'] : null,
                'retry_fulfillment_status' => OrderStatus::CREATED->value,
                'retried_at' => date(config('citronel.db_date_time_db_format'))
            ];
            $manualRetryObj = ManualFulfillmentRetry::create($retrySaveData);

            $statusProcessing = OrderStatus::PROCESSING_RETRY->value;
            $retryCount = $retryCount + 1;
            $this->orderFulfillmentModel::where('id', $item->id)->update([
                'order_item_fulfillment_status' => $statusProcessing
            ]);

            $productInterfaceObj = $this->helperService->makeObject($item->order_item->product->product_class, ['product' => $item->order_item->product]);
            $productOrderFulfillmentItemUpdateData = [];

            $getSupplierOrderForManualFulfillmentResponse = $productInterfaceObj->getSupplierOrderForManualFulfillment($item, $extra);
            if ($getSupplierOrderForManualFulfillmentResponse['success']) {
                $supplierOrder = $getSupplierOrderForManualFulfillmentResponse['result'];

                $orderItemFulfillmentStatus = OrderStatus::UNFULFILLED->value;

                $processSupplierOrderForManualFulfillmentResponse = $productInterfaceObj->processSupplierOrderForManualFulfillment($item, $extra);
                if ($processSupplierOrderForManualFulfillmentResponse['success']) {
                    $orderItemFulfillmentStatus = OrderStatus::FULFILLED->value;
                    $fulfilledAt = date(config('citronel.db_date_time_db_format'));

                    $generateProductOrderFulfillmentItemUpdateExtra = $processSupplierOrderForManualFulfillmentResponse['result'];

                    $productOrderFulfillmentItemUpdateData = $productInterfaceObj->generateProductOrderFulfillmentItemUpdate($item, $generateProductOrderFulfillmentItemUpdateExtra);
                }

                $data['message'] = $processSupplierOrderForManualFulfillmentResponse['message'];

            } else {
                $fulfillProductOrderItemResponse = $productInterfaceObj->fulfillProductOrderItem($item, $extra);
                if ($fulfillProductOrderItemResponse['success']) {
                    $orderItemFulfillmentStatus = OrderStatus::FULFILLED->value;
                    $fulfilledAt = date(config('citronel.db_date_time_db_format'));

                    $generateProductOrderFulfillmentItemUpdateExtra = $fulfillProductOrderItemResponse['result'];

                    $productOrderFulfillmentItemUpdateData = $productInterfaceObj->generateProductOrderFulfillmentItemUpdate($item, $generateProductOrderFulfillmentItemUpdateExtra);

                } else {
                    $orderItemFulfillmentStatus = OrderStatus::UNFULFILLED->value;
                }

                $data['message'] = $fulfillProductOrderItemResponse['message'];
            }

            $retrySaveData = [
                'retry_fulfillment_status' => $orderItemFulfillmentStatus
            ];
            ManualFulfillmentRetry::where('id', $manualRetryObj->id)->update(
                $retrySaveData
            );

            $fulfillmentUpdateData = [
                'order_item_fulfillment_status' => $orderItemFulfillmentStatus,
                'fulfilled_at' => $fulfilledAt,
                'retry_count' => $retryCount,
            ];
            $fulfillmentUpdateData = array_merge($fulfillmentUpdateData, $productOrderFulfillmentItemUpdateData);

            $this->orderFulfillmentModel::where('id', $item->id)->update($fulfillmentUpdateData);

            $item = $this->orderFulfillmentModel::where('id', $item->id)->first();

            switch ($item->order_item_fulfillment_status) {
                case OrderStatus::FULFILLED->value:
                    if (is_null($data['message'])) {
                        $data['message'] = $productInterfaceObj->successItemFulfillmentMessage($item, $extra);
                    }
                    break;
                case OrderStatus::UNFULFILLED->value:
                    if (is_null($data['message'])) {
                        $data['message'] = $productInterfaceObj->failedItemFulfillmentMessage($item, $extra);
                    }
                    $data['errors'] = true;
                    break;
            }
        }

        // log
        $correlationToken = $item->order_item->order->correlation_token;
        $auditData = $this->auditService->generatePreliminaryAuditData(null, $correlationToken);
        $auditData['al_action_type'] = config('audit.action_types.update.name');
        $auditData['al_event_name'] = $subProcess['events']['item_fulfillment_processed']['name'];
        $auditData['al_correlation_id'] = $correlationToken;
        $auditData['al_is_success'] = $data['success'];

        if (is_null($data['errors'])) {
            $data['success'] = true;
            $auditData['al_is_success'] = $data['success'];
        }
        $data['result'] = $item;

        $auditData['al_message'] = $data['message'];

        FulfillmentProcessed::dispatch($auditData);

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
            OrderStatus::UNFULFILLED->value,
        ];

        if (in_array($orderItemFulfillmentStatus, $statusToCheck)) {
            return true;
        }

        return false;
    }
}
