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

    protected $orderModel;

    public function __construct()
    {
        $this->orderFulfillmentModel = new OrderFulfillment();
        $this->auditService = new AuditLogService();

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);

        $this->productService = new CitronelProductService();
        $this->errorCatalogueService = new CitronelErrorCatalogueService();

        $this->mainProcess = $this->errorCatalogueService->getMainProcess('order');

        $this->orderModel = app(config('citronel-order.order_model'));
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

                $createProductOrderFulfillmentItemExtra = $productInterfaceObj->createFulfillmentItemExtra($anOrderItem, $itemExtra);

                $createProductOrderFulfillmentItemResponse = $productInterfaceObj->createFulfillmentItem($anOrderItem, $createProductOrderFulfillmentItemExtra);

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

        // process strategy
        // grouping
        if(!is_null($order->fulfillment_strategy_class)) {
            $fulfillmentStrategyClass = $this->helperService->makeObject($order->fulfillment_strategy_class);

            $fulfillmentStrategyClass->groupFulfillments($order);
        } else {
            // default grouping
            $this->groupFulfillments($order);
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

        // get parent
        $parentItem = $this->getParentItemByFulfillmentGroupId($item->order_item_fulfillment_grp_id);

        $isRetry = false;
        $retryCount = null;
        if (array_key_exists('retry_count', $extra)) {
            $retryCount = intval($extra['retry_count']);
            if (intval($retryCount) > 1) {
                $isRetry = true;
            }
        }

        $isLastRetry = false;
        if (array_key_exists('is_last_retry', $extra)) {
            $isLastRetry = $extra['is_last_retry'];
        }

        $hasFulfillmentErrors = false;
        $fulfillmentUpdateData = [];

        $fulfillmentStatusFilter = [
            OrderStatus::CREATED->value
        ];
        if ($isRetry) {
            $fulfillmentStatusFilter = [
                OrderStatus::UNFULFILLED->value,
                OrderStatus::PROCESSING_RETRY->value
            ];
        }
        $groupItems = $this->getFulfillmentsByFulfillmentGroupId($parentItem->order_item_fulfillment_grp_id, $fulfillmentStatusFilter);
        if ($groupItems->count() == 0) {
            return;
        }

        $productInterfaceObj = $this->helperService->makeObject($parentItem->order_item->product->product_class, ['product' => $parentItem->order_item->product]);

        $jobPolicyId = 'fulfill_item';
        $jobPolicy = $this->getJobPolicy($jobPolicyId);

        $statusProcessing = OrderStatus::PROCESSING->value;
        $fulfilledAt = date(config('citronel.db_date_time_db_format'));

        $this->orderFulfillmentModel::where('order_item_fulfillment_grp_id', $parentItem->order_item_fulfillment_grp_id)->update(
            ['order_item_fulfillment_status' => $statusProcessing]
        );

        $fulfillProductOrderItemResponse = $productInterfaceObj->fulfillGroupItems($groupItems, $extra);

        foreach ($groupItems as $groupItem) {

            $fulfillProductOrderGroupItemResponse = $fulfillProductOrderItemResponse['result'][$groupItem->id];

            if ($fulfillProductOrderGroupItemResponse['success']) {
                $orderItemFulfillmentStatus = OrderStatus::FULFILLED->value;
            } else {
                $fulfilledAt = null;
                $hasFulfillmentErrors = true;

                if (!is_null($jobPolicy) && !$isLastRetry) {
                    $orderItemFulfillmentStatus = OrderStatus::PROCESSING_RETRY->value;

                } else {
                    $orderItemFulfillmentStatus = OrderStatus::UNFULFILLED->value;

                    FulfillmentFailed::dispatch($groupItem);
                }
            }

            $groupFulfillmentUpdateData = [
                'order_item_fulfillment_status' => $orderItemFulfillmentStatus,
                'fulfilled_at' => $fulfilledAt,
                'retry_count' => $retryCount,
            ];

            $fulfillmentUpdateData[$groupItem->id] = $groupFulfillmentUpdateData;
        }

        $generateProductOrderFulfillmentItemUpdateExtra = $fulfillProductOrderItemResponse['result'];

        $productOrderFulfillmentItemUpdateData = $productInterfaceObj->generateFulfillmentItemUpdate($parentItem, $generateProductOrderFulfillmentItemUpdateExtra);

        $fulfillmentUpdateData = array_merge_recursive($fulfillmentUpdateData, $productOrderFulfillmentItemUpdateData);

        foreach ($fulfillmentUpdateData as $key => $value) {
            $this->orderFulfillmentModel::where('id', $key)->update($value);
        }

        $data['message'] = $fulfillProductOrderItemResponse['message'];

        // log
        $correlationToken = $parentItem->order_item->order->correlation_token;
        $auditData = $this->auditService->generatePreliminaryAuditData(null, $correlationToken);
        $auditData['al_action_type'] = config('audit.action_types.update.name');
        $auditData['al_event_name'] = $subProcess['events']['item_fulfillment_processed']['name'];
        $auditData['al_correlation_id'] = $correlationToken;
        $auditData['al_is_success'] = true;
        $auditData['order_data']['order_guid'] = $item->order_item->order->order_guid;

        if ($hasFulfillmentErrors) {
            $data['errors'] = true;
            $auditData['al_is_success'] = false;

            $data['message'] = $productInterfaceObj->failedItemFulfillmentMessage($parentItem, $extra);
        } else {
            $data['message'] = $productInterfaceObj->successItemFulfillmentMessage($parentItem, $extra);
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
     * @param mixed $orderId [explicite description]
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
     * // @todo
     *
     * @return array
     */
    public function manuallyFulfillItem($item, $extra = [])
    {
        $data = $this->helperService->returnFormat();
        $subProcess = $this->errorCatalogueService->getSubProcess('order', 'fulfillment');

        // get parent
        $parentItem = $this->getParentItemByFulfillmentGroupId($item->order_item_fulfillment_grp_id);

        $hasFulfillmentErrors = false;
        $validatedGroupItems = [];
        $fulfillmentUpdateData = [];

        $orderItemFulfillmentStatus = [
            OrderStatus::UNFULFILLED->value
        ];
        $groupItems = $this->getFulfillmentsByFulfillmentGroupId($item->order_item_fulfillment_grp_id, $orderItemFulfillmentStatus);
        if ($groupItems->count() == 0) {
            return;
        }

        $productInterfaceObj = $this->helperService->makeObject($parentItem->order_item->product->product_class, ['product' => $parentItem->order_item->product]);

        foreach ($groupItems as $groupItem) {
            $validateProductForManualFulfillmentResponse = $this->productService->validateProductForManualFulfillment($groupItem->order_item->product);
            if (!$validateProductForManualFulfillmentResponse['success']) {
                $data['errors'] = true;
                $data['message'] = $validateProductForManualFulfillmentResponse['message'];

                continue;
            }

            if (is_null($data['errors'])) {
                $shouldFullfillItem = $this->shouldFulfillItemManually($item->order_item_fulfillment_status);
                if (!$shouldFullfillItem) {
                    $data['errors'] = true;
                    $data['message'] = __('citronel-commerce::order/messages.order_item_fulfillment_retry_not_allowed');

                    continue;
                }
            }
            
            $validatedGroupItems[] = $groupItem;
        }

        if (empty($validatedGroupItems)) {
            return;
        }

        $fulfillProductOrderItemResponse = $productInterfaceObj->manuallyfulfillGroupItems($groupItems, $extra);

        foreach ($validatedGroupItems as $groupItem) {
            $retryCount = intval($groupItem->retry_count);
            $retrySaveData = [
                'id' => (string) Str::uuid(),
                'order_fulfillment_id' => $groupItem->id,
                'retry_user_id' => array_key_exists('retry_user_id', $extra) ? $extra['retry_user_id'] : null,
                'retry_fulfillment_status' => OrderStatus::CREATED->value,
                'retried_at' => date(config('citronel.db_date_time_db_format'))
            ];
            $manualRetryObj = ManualFulfillmentRetry::create($retrySaveData);

            $statusProcessing = OrderStatus::PROCESSING_RETRY->value;
            $retryCount = $retryCount + 1;
            $this->orderFulfillmentModel::where('id', $groupItem->id)->update([
                'order_item_fulfillment_status' => $statusProcessing
            ]);

            $fulfillProductOrderGroupItemResponse = $fulfillProductOrderItemResponse['result'][$groupItem->id];

            if ($fulfillProductOrderGroupItemResponse['success']) {
                $orderItemFulfillmentStatus = OrderStatus::FULFILLED->value;
                $fulfilledAt = date(config('citronel.db_date_time_db_format'));
            } else {
                $fulfilledAt = null;
                $hasFulfillmentErrors = true;
                $orderItemFulfillmentStatus = OrderStatus::UNFULFILLED->value;
            }

            $groupFulfillmentUpdateData = [
                'order_item_fulfillment_status' => $orderItemFulfillmentStatus,
                'fulfilled_at' => $fulfilledAt,
                'retry_count' => $retryCount,
            ];

            $fulfillmentUpdateData[$groupItem->id] = $groupFulfillmentUpdateData;
        }

        $generateProductOrderFulfillmentItemUpdateExtra = $fulfillProductOrderItemResponse['result'];

        $productOrderFulfillmentItemUpdateData = $productInterfaceObj->generateFulfillmentItemUpdate($item, $generateProductOrderFulfillmentItemUpdateExtra);

        $fulfillmentUpdateData = array_merge_recursive($fulfillmentUpdateData, $productOrderFulfillmentItemUpdateData);

        foreach ($fulfillmentUpdateData as $key => $value) {
            $this->orderFulfillmentModel::where('id', $key)->update($value);
        }

        $data['message'] = $fulfillProductOrderItemResponse['message'];

        // log
        $correlationToken = $item->order_item->order->correlation_token;
        $auditData = $this->auditService->generatePreliminaryAuditData(null, $correlationToken);
        $auditData['al_action_type'] = config('audit.action_types.update.name');
        $auditData['al_event_name'] = $subProcess['events']['item_fulfillment_processed']['name'];
        $auditData['al_correlation_id'] = $correlationToken;
        $auditData['al_is_success'] = $data['success'];

        if ($hasFulfillmentErrors) {
            $data['errors'] = true;
            $auditData['al_is_success'] = false;

            $data['message'] = $productInterfaceObj->failedItemFulfillmentMessage($item, $extra);
        } else {
            $data['message'] = $productInterfaceObj->successItemFulfillmentMessage($item, $extra);
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
    
    /**
     * Method getFulfillmentsByFulfillmentGroupId
     *
     * @param mixed $groupId [explicite description]
     * @param string|array $orderItemFulfillmentStatus [explicite description]
     *
     * @return mixed
     */
    public function getFulfillmentsByFulfillmentGroupId($groupId, $orderItemFulfillmentStatus = null)
    {
        $result = $this->orderFulfillmentModel->where('order_item_fulfillment_grp_id', $groupId);

        if (is_array($orderItemFulfillmentStatus)) {
            // If $status is an array, use whereIn
            $result->whereIn('order_item_fulfillment_status', $orderItemFulfillmentStatus);
        } elseif (!is_null($orderItemFulfillmentStatus)) {
            // If $status is a single value, use where
            $result->where('order_item_fulfillment_status', $orderItemFulfillmentStatus);
        }

        $result->orderBy('is_grp_parent', 'desc');
        
        return $result->get();
    }

    // @todo group
    public function generateOrderItemFulfillmentSummary($item, $extra = [])
    {
        $data = $this->helperService->returnFormat();

        $productTempArray = array_key_exists('product_temp_array', $extra) ? $extra['product_temp_array'] : [];
        $product = $productTempArray['product'];
        $productInterfaceObj = $productTempArray['product_class'];
        
        $generateOrderItemFulfillmentSummaryExtra = [
            'product_temp_array' => $productTempArray,
        ];
        $generateOrderItemFulfillmentSummaryResponse = $productInterfaceObj->generateFulfillmentItemSummary($item, $generateOrderItemFulfillmentSummaryExtra);
        $orderItemFulfillmentSummary = $generateOrderItemFulfillmentSummaryResponse;

        $data['result'] = $orderItemFulfillmentSummary;
        $data['success'] = true;

        return $data;
    }
    
    /**
     * Method generateOrderFulfillmentSummary
     *
     * //@todo group fulfillment summary
     * //sync or async fulfillmet summary
     * // order strategy override
     *
     * @param mixed $order [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function generateOrderFulfillmentSummary($order, $extra = [])
    {
        $data = $this->helperService->returnFormat();

        $productTempArray = [];

        $fulfilledFulfillmentStatus = OrderStatus::FULFILLED->value;
        $getFulfillmentsByOrderIdResponse = $this->getFulfillmentsByOrderId($order->id, $fulfilledFulfillmentStatus);

        $orderFulfillmentSummary = [];

        foreach ($getFulfillmentsByOrderIdResponse as $item) {

            $product = $item->order_item->product;
            if (!array_key_exists($product->id, $productTempArray)) {
                $productInterfaceObj = $this->helperService->makeObject($product->product_class, ['product' => $product]);

                $productTempArray[$product->id] = [
                    'product' => $product,
                    'product_class' => $productInterfaceObj
                ];
            }

            $generateOrderItemFulfillmentSummaryExtra = [
                'product_temp_array' => $productTempArray[$product->id],
            ];
            $generateOrderItemFulfillmentSummaryResponse = $this->generateOrderItemFulfillmentSummary($item, $generateOrderItemFulfillmentSummaryExtra);
            $orderFulfillmentSummary['items'][] = $generateOrderItemFulfillmentSummaryResponse['result'];
        }

        $data['result'] = $orderFulfillmentSummary;
       
        return $data;
    }

    public function getSuccessPaymentForOrderFulfillmentSummary($orderId)
    {
        $result = null;

        $order = $this->orderModel::where('id', $orderId)
            ->whereHas('payments', function ($query) {
                $query->where('payment_status', PaymentStatus::PAID->value);
            })
            ->with([
                'payments' => function ($query) {
                    $query->where('payment_status', PaymentStatus::PAID->value)
                        ->select([
                            'id',
                            'order_id',
                            'gateway_merchant_transaction_no',
                            'paid_at',
                            'card_number',
                            'card_holder',
                            'payment_method_configuration_id',
                        ]);
                },
                'payments.payment_method_configuration:id,payment_method_id,id',
                'payments.payment_method_configuration.payment_method:id,title'
            ])
            ->first();

        $payment = $order->payments->first();
        if ($payment) {
            $result = [
                'gateway_merchant_transaction_no' => $payment->gateway_merchant_transaction_no,
                'paid_at' => $payment->paid_at,
                'title' => $payment->payment_method_configuration?->payment_method?->title,
                'card_number' => $payment->card_number,
                'card_holder' => $payment->card_holder,
            ];
        }

        return $result;
    }
    
    /**
     * Method groupFulfillments
     *
     * @param mixed $order [explicite description]
     * 
     * By default each item is grouped in its own group
     *
     * @return array
     */
    public function groupFulfillments($order)
    {
        $data = $this->helperService->returnFormat();

        DB::beginTransaction();
    
        $orderFulfillments = $this->getFulfillmentsByOrderId($order->id);
        foreach ($orderFulfillments as $aFulfillment) {
            $groupingId = (string) Str::uuid();
            $aFulfillment->order_item_fulfillment_grp_id = $groupingId;
            $aFulfillment->is_grp_parent = true;
            $aFulfillment->save();
        }
    
        DB::commit();
    
        $data['success'] = true;
    
        return $data;
    }

    // @todo
    /**
     * Single item
     */
    public function autoRetryFulfillItem($item, $extra = [])
    {
        $data = $this->helperService->returnFormat();
        $subProcess = $this->errorCatalogueService->getSubProcess('order', 'fulfillment');
        $subProcessKey = $subProcess['key'];

        $isRetry = false;
        $retryCount = null;
        if (array_key_exists('retry_count', $extra)) {
            $retryCount = intval($extra['retry_count']);
            if (intval($retryCount) > 1) {
                $isRetry = true;
            }
        }

        $productInterfaceObj = $this->helperService->makeObject($item->order_item->product->product_class, ['product' => $item->order_item->product]);

        $shouldFullfillItem = $this->shouldFulfillItem($item->order_item_fulfillment_status, $isRetry);
        if ($shouldFullfillItem) {

            $statusProcessing = OrderStatus::PROCESSING->value;
            $this->orderFulfillmentModel::where('id', $item->id)->update(
                ['order_item_fulfillment_status' => $statusProcessing]
            );

            $fulfilledAt = date(config('citronel.db_date_time_db_format'));

            $fulfillProductOrderItemResponse = $productInterfaceObj->fulfillGroupItems($item, $extra);

     
            if ($fulfillProductOrderItemResponse['success']) {
                $orderItemFulfillmentStatus = OrderStatus::FULFILLED->value;
            } else {
                $orderItemFulfillmentStatus = OrderStatus::UNFULFILLED->value;
                $fulfilledAt = null;
            }

            $singleFulfillmentUpdateData = [
                'order_item_fulfillment_status' => $orderItemFulfillmentStatus,
                'fulfilled_at' => $fulfilledAt
            ];

            $fulfillmentUpdateData[$item->id] = $singleFulfillmentUpdateData;


            // for single and group, we are relying on main success for retry!
            if (!$fulfillProductOrderItemResponse['success']) {
                /**
                 * check if request is the last retry for the job
                 * if retry job is active and if last retry, order status is set to unfulfilled, else order status is processing_retry
                 **/

                 $jobPolicyId = 'auto_retry_fulfill_item';
                 $jobPolicy = $this->getJobPolicy($jobPolicyId);
 
                 $isLastRetry = false;
                 if (array_key_exists('is_last_retry', $extra)) {
                     $isLastRetry = $extra['is_last_retry'];
                 }
                 if (!is_null($jobPolicy) && !$isLastRetry) {
                     $orderItemFulfillmentStatus = OrderStatus::PROCESSING_RETRY->value;
                 }

                 $fulfillmentUpdateData[$item->id]['order_item_fulfillment_status'] = $orderItemFulfillmentStatus;
                 $fulfillmentUpdateData[$item->id]['retry_count'] = $retryCount;
            }

            $generateProductOrderFulfillmentItemUpdateExtra = $fulfillProductOrderItemResponse['result'];

            $productOrderFulfillmentItemUpdateData = $productInterfaceObj->generateFulfillmentItemUpdate($item, $generateProductOrderFulfillmentItemUpdateExtra);

            $fulfillmentUpdateData[$item->id] = array_merge_recursive($fulfillmentUpdateData, $productOrderFulfillmentItemUpdateData);

            foreach ($fulfillmentUpdateData as $key => $value) {
                $this->orderFulfillmentModel::where('id', $key)->update($value);
            }

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

    public function getParentItemByFulfillmentGroupId($groupId)
    {
        return $this->orderFulfillmentModel->where('order_item_fulfillment_grp_id', $groupId)
            ->where('is_grp_parent', true)
            ->first();
    }
}
