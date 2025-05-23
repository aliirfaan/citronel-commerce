<?php

namespace aliirfaan\CitronelCommerce\Services\Order;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use aliirfaan\CitronelErrorCatalogue\Traits\ErrorCatalogue;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\CitronelCommerce\Models\Order\OrderItem;
use aliirfaan\CitronelCommerce\Services\Currency\CitronelCurrencyService;
use aliirfaan\CitronelCommerce\Enums\Order\OrderStatus;
use aliirfaan\CitronelCommerce\Models\Order\Order;
use aliirfaan\CitronelErrorCatalogue\Services\CitronelErrorCatalogueService;
use aliirfaan\CitronelCommerce\Services\Product\CitronelProductService;

class CitronelOrderService
{
    use ErrorCatalogue;

    /**
     * order
     *
     * @var mixed
     */
    public $orderModel;
    
    /**
     * orderItemModel
     *
     * @var mixed
     */
    protected $orderItemModel;

    
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
     * mainProcess
     *
     * @var string
     */
    public $mainProcess;
    
    /**
     * currencyService
     *
     * @var mixed
     */
    private $currencyService;

    protected $errorCatalogueService;

    private $productService;

    public function __construct(CitronelCurrencyService $currencyService)
    {
        $this->loadOrderModel();
        $this->orderItemModel = new OrderItem();
        $this->auditService = new AuditLogService();

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);

        $this->currencyService = $currencyService;
        $this->errorCatalogueService = new CitronelErrorCatalogueService();

        $this->mainProcess = $this->errorCatalogueService->getMainProcess('order');

        $this->productService = new CitronelProductService();
    }
    
    /**
     * Method calculateOrderExpiry
     *
     * @param $seconds $seconds [explicite description]
     *
     * @return string
     */
    public function calculateOrderExpiry($seconds = null)
    {
        if (is_null($seconds)) {
            $seconds = config('citronel-order.order_expiry_seconds');
        }

        return date('Y-m-d H:i:s', strtotime('+' . $seconds . ' seconds'));
    }
    
    /**
     * Method generateOrderGuid
     *
     * @return string
     */
    public function generateOrderGuid()
    {
        return (string) Str::uuid();
    }

    /**
     * generateOrderNumber
     *
     * @param  mixed $orderIdentifier
     * @param  mixed $prefix
     * @param  mixed $scopeIdentifier - add scope to order number. For example you can add product or service name/id
     * @param  int $maxLength maximum string length - some payment gateways may have restrictions
     * @return string
     */
    public function generateOrderNumber($orderIdentifier, $prefix = null, $scopeIdentifier = null, $maxLength = 64)
    {
        if (is_null($prefix)) {
            $prefix = config('citronel-order.order_number_prefix');
        }

        $prefix = $prefix . $scopeIdentifier;

        $suffix = random_int(1000, 9999) . date('d');
        $orderNumber = $prefix . $orderIdentifier . $suffix;

        if (\strlen($orderNumber) > $maxLength) {
            $orderNumber = (string) Str::uuid();
            $orderNumber = \str_replace('-', '', $orderNumber);
        }

        return $orderNumber;
    }
    
    /**
     * Method createOrder
     *
     * Create order
     * Create order items
     * Calculate order subtotal, total
     * Convert to currency if order_currency_code is not base currency
     * Some services do not have a product price. If an 'amount' field is present in the order item, use that to calculate order total
     *
     * @param array $saveData [explicite description]
     * @param array $orderItems [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function createOrder($saveData, $orderItems, $extra = [])
    {
        $data = $this->helperService->returnFormat();
        $subProcess = $this->errorCatalogueService->getSubProcess('order', 'create');
    
        DB::beginTransaction();
    
        // Create order
        $orderCurrencyCode = $saveData['order_currency_code'];
        $currencyRate = $saveData['currency_rate'];
        $lockCurrency = array_key_exists('lock_currency', $extra) ? $extra['lock_currency'] : false;

        $acceptedTermsAt = null;
        if (array_key_exists('terms_accepted', $saveData)) {
            $acceptedTermsAt = now();
        }

        $shouldSendReceipt = array_key_exists('should_send_receipt', $saveData) ? $saveData['should_send_receipt'] : null;
        $receiptChannels = null;
        if (!is_null($shouldSendReceipt) && intval($shouldSendReceipt) !== 0) {
            $receiptChannels = array_key_exists('receipt_channels', $saveData) ? $saveData['receipt_channels'] : null;
            if (is_array($receiptChannels)) {
                $receiptChannels = implode(',', $receiptChannels);
            }
        }

        $orderSaveData = [
            'order_guid' => array_key_exists('order_guid', $saveData) ? $saveData['order_guid'] : $this->generateOrderGuid(),
            'actor_id' => array_key_exists('actor_id', $saveData) ? $saveData['actor_id'] : null,
            'order_status' => OrderStatus::CREATED->value,
            'currency_rate_id' => $currencyRate ? $currencyRate->id : null,
            'order_currency_code' => $orderCurrencyCode,
            'expires_at' => $this->calculateOrderExpiry(),
            'correlation_token' => array_key_exists('correlation_token', $saveData) ? $saveData['correlation_token'] : null,
            'lock_currency' => $lockCurrency,
            'fulfillment_strategy_class' => array_key_exists('fulfillment_strategy_class', $saveData) ? $saveData['fulfillment_strategy_class'] : null,
            'should_send_receipt' => array_key_exists('should_send_receipt', $saveData) ? $saveData['should_send_receipt'] : null,
            'receipt_channels' => $receiptChannels,
            'order_payment_method_configuration_id' => array_key_exists('order_payment_method_configuration_id', $saveData) ? $saveData['order_payment_method_configuration_id'] : null,
            'terms_accepted_at' => $acceptedTermsAt,
            'order_meta' => array_key_exists('order_meta', $saveData) ? json_encode($saveData['order_meta']) : null,
        ];
    
        $newOrder = $this->orderModel::create($orderSaveData);

        // order number
        $orderNumber = $this->generateOrderNumber($newOrder->id);
        $updateOrderSaveData = [
            'order_number' => $orderNumber
        ];

        // Update database
        $this->orderModel::where('id', $newOrder->id)->update($updateOrderSaveData);
    
        $createItemResponse = $this->createOrderItems($newOrder, $orderItems, $extra);

        if(!$createItemResponse['success']){
            $data['errors'] = true;
        }

        if(is_null($data['errors'])){
            $orderTotalsResponse = $this->calculateOrderTotals($newOrder->order_guid);

            if(!$orderTotalsResponse['success']){
                $data['errors'] = true;
            }
            else{
                DB::commit();
                $data['success'] = true;
                $data['result']['order'] = $orderTotalsResponse['result'];
            }
        }

        return $data;
    }
    
    /**
     * Method reviewOrder
     *
     * @param array $saveData [explicite description]
     * @param mixed $order [explicite description]
     * @param array $extra [explicite description]
     *
     * @return void
     */
    public function reviewOrder($saveData, $order, $extra = [])
    {
        $data = $this->helperService->returnFormat();

        $orderCurrencyCode = $saveData['order_currency_code'];
        $updateOrderSaveData = [
            'order_payment_method_configuration_id' => $saveData['order_payment_method_configuration_id'],
        ];

        if ($orderCurrencyCode !== $order->order_currency_code) {
            if ($orderCurrencyCode === $this->currencyService->getBaseCurrencyCode()) {
                $orderSubtotal = $order->order_base_currency_subtotal;
                $orderGrandTotal = $order->order_base_currency_grand_total;
            } else {
                $currencyRate = $this->currencyService->getCurrencyRateById($order->currency_rate_id);

                $orderSubtotal = $order->order_subtotal;
                $orderGrandTotal = $order->order_grand_total;

                $orderSubtotal = $this->currencyService->convertAmount($orderSubtotal, $orderCurrencyCode, $currencyRate);
                $orderGrandTotal = $this->currencyService->convertAmount($orderGrandTotal, $orderCurrencyCode, $currencyRate);
            }

            $updateOrderSaveData['order_currency_code'] = $orderCurrencyCode;
            $updateOrderSaveData['order_subtotal'] = $orderSubtotal;
            $updateOrderSaveData['order_grand_total'] = $orderGrandTotal;
        }

        $this->orderModel::where('id', $order->id)->update($updateOrderSaveData);

        $order = $this->orderModel::with('order_items')->where('id', $order->id)->first();

        $data['result'] = $order;
        $data['success'] = true;

        return $data;
    }
    
    /**
     * Method getOrderById
     *
     * @param $id $id [explicite description]
     *
     * @return array
     */
    public function getOrderById($id)
    {
        $data = $this->helperService->returnFormat();
  
        $result = $this->orderModel::where('id', $id)->first();
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
     * Method getOrderByGuid
     *
     * @param $id $id [explicite description]
     *
     * @return mixed
     */
    public function getOrderByGuid($id)
    {
        $data = $this->helperService->returnFormat();
  
        $result = $this->orderModel::where('order_guid', $id)->first();
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
     * Method checkOrderExpiry
     *
     * @param mixed $order [explicite description]
     *
     * @return array
     */
    public function checkOrderExpiry($order)
    {
        $data = $this->helperService->returnFormat();

        $orderExpiry = $order->expires_at;
        $now = now();
        if ($now > $orderExpiry) {
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::order/messages.order_expired');
        }

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }
    
    /**
     * Method getOrderItemById
     *
     * @param $orderId $orderId [explicite description]
     * @param $id $id [explicite description]
     *
     * @return mixed
     */
    public function getOrderItemById($orderId, $id)
    {
        $data = $this->helperService->returnFormat();
  
        $result = $this->$this->orderItemApiQuery::where('id', $id)
            ->where('order_id', $orderId)
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
    
    /**
     * Method reviewOrderItem
     *
     * @param array $saveData [explicite description]
     * @param mixed $orderItem [explicite description]
     * @param array $extra [explicite description]
     *
     * @return void
     */
    public function reviewOrderItem($saveData, $orderItem, $extra = [])
    {
        $data = $this->helperService->returnFormat();
    
        DB::beginTransaction();
    
        $quantity = $saveData['quantity'];
        $updateOrderItemSaveData = [
            'quantity' => $saveData['quantity'],
        ];
    
        // Update the order item quantity
        $this->orderItemModel::where('id', $orderItem->id)->update($updateOrderItemSaveData);
        $order = $orderItem->order;
        $orderCurrencyCode = $order->order_currency_code;
    
        if ($quantity !== $orderItem->quantity) {
            $orderBaseCurrencySubtotal = '0'; // Use string for bcmath operations
            $orderBaseCurrencyGrandTotal = '0'; // Use string for bcmath operations
            $orderSubtotal = $orderBaseCurrencySubtotal;
            $orderGrandTotal = $orderBaseCurrencyGrandTotal;

            $currencyRate = $this->currencyService->getCurrencyRateById($order->currency_rate_id);
    
            $orderItems = $order->order_items;
            foreach ($orderItems as $anOrderItem) {

                $product = $orderItem->product;
                if ($product->price_currency_code === $this->currencyService->getBaseCurrencyCode()) {
                    $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $anOrderItem->product_price, (string) $anOrderItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
                } else {
                    $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $anOrderItem->product_price, (string) $anOrderItem->quantity, config('citronel.decimals')), config('citronel.decimals'));

                    $orderGrandTotal = $orderSubtotal;
                }
            }
    
            // Calculate the grand total using bcmath to handle precision
            $orderBaseCurrencyGrandTotal = bcadd($orderBaseCurrencyGrandTotal, $orderBaseCurrencySubtotal, config('citronel.decimals'));
    
            // Convert the subtotal and grand total to the required currency with precision
            if (!$order->lock_currency) {
                $orderSubtotal = $this->currencyService->convertAmount($orderBaseCurrencySubtotal, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
                $orderGrandTotal = $this->currencyService->convertAmount($orderBaseCurrencyGrandTotal, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
            }
    
            $updateOrderSaveData = [
                'order_base_currency_subtotal' => (string) $orderBaseCurrencySubtotal,
                'order_base_currency_grand_total' => (string) $orderBaseCurrencyGrandTotal,
                'order_subtotal' => (string) $orderSubtotal,
                'order_grand_total' => (string) $orderGrandTotal,
            ];
    
            // Update the order in the database with the new values
            $this->orderModel::where('id', $order->id)->update($updateOrderSaveData);
        }
    
        DB::commit();
    
        // Fetch the updated order
        $updatedOrder = $this->orderModel::with('order_items')->where('id', $order->id)->first();
    
        // Format the order amounts using the correct currency format
        $updatedOrder->subtotal = $this->currencyService->formatCurrencyAmount($updatedOrder->order_subtotal, $orderCurrencyCode)['formatted'];
        $updatedOrder->total = $this->currencyService->formatCurrencyAmount($updatedOrder->order_grand_total, $orderCurrencyCode)['formatted'];
    
        $data['result']['order'] = $updatedOrder;
        $data['success'] = true;
    
        return $data;
    }
    
    /**
     * Method shouldCreatePaymentOrder
     *
     * @param string $orderStatus [explicite description]
     *
     * @return bool
     */
    public function shouldCreatePaymentOrder($orderStatus)
    {
        return $orderStatus === OrderStatus::CREATED->value;
    }
    
    /**
     * Method validateOrderForPayment
     *
     * @param mixed $order [explicite description]
     *
     * @return array
     */
    public function validateOrderForPayment($order)
    {
        $data = $this->helperService->returnFormat();

        $orderStatus = $order->order_status;
        if (!$this->shouldCreatePaymentOrder($orderStatus)) {
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::order/messages.order_already_paid');
        }

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }
    
    /**
     * Method generateOrderPaymentDescription
     *
     * @param mixed $order [explicite description]
     * @param array $extra [explicite description]
     *
     * @return void
     */
    public function generateOrderPaymentDescription($order, $extra = [])
    {
        return __('citronel-commerce::order/messages.order_remarks') . '-' . $order->order_number;
    }
        
    /**
     * Method updateOrder
     *
     * @param $orderId $orderId [explicite description]
     * @param $saveData $saveData [explicite description]
     *
     * @return array
     */
    public function updateOrder($orderId, $saveData)
    {
        $data = $this->helperService->returnFormat();

        $updateOrder = $this->orderModel::where('id', $orderId)->update($saveData);
        if (!$updateOrder) {
            $data['errors'] = true;
        }

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }

    public function shouldVerifyLastOrderBeforeCreate()
    {
        return intval(config('citronel-order.verify_last_order_before_create'));
    }

    public function getLastOrderToVerifyForActor($actor, $seconds)
    {
        $data = $this->helperService->returnFormat();
        $timeLimit = Carbon::now()->subSeconds(intval($seconds));

        $order = $this->orderModel::where('actor_id', $actor->id)
            ->where('updated_at', '>=', $timeLimit)
            ->orderBy('id', 'desc')
            ->first();

        if (!is_null($order) && $order->order_status === OrderStatus::CREATED->value) {
            $data['result'] = $order;
        } else {
            $data['errors'] = true;
        }
    
        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }

    public function shouldVerifyPendingFulfillmentsBeforeCreate()
    {
        return intval(config('citronel-order.verify_pending_fulfillments_before_create'));
    }

    public function loadOrderModel()
    {
        $this->orderModel = app(config('citronel-order.order_model'));
    }
    
    /**
     * Method orderCreatePreprocess
     *
     * @param $saveData $saveData [explicite description]
     * @param $orderItems $orderItems [explicite description]
     * @param $extra $extra [explicite description]
     *
     * @return array
     */
    public function orderCreatePreprocess($saveData, $orderItems, $extra = [])
    {
        $data = $this->helperService->returnFormat();

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }
          
        return $data;
    }

    public function validateOrderForFulfillment($order)
    {
        $data = $this->helperService->returnFormat();

        $orderStatus = $order->order_status;
        if ($orderStatus !== OrderStatus::PAID->value) {
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::order/messages.order_not_paid');
        }

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }
    
    /**
     * Method updateOrderMeta
     *
     * @param mixed $order [explicite description]
     * @param array $data [explicite description]
     *
     * @return void
     */
    public function updateOrderMeta($order, array $data)
    {
        $meta = json_decode($order->order_meta, true) ?? [];
    
        // Merge the new data into the existing meta
        $updatedMeta = array_merge($meta, $data);

        $this->orderModel::where('id', $order->id)
        ->update(['order_meta' => json_encode($updatedMeta)]);
    }

    public function createOrderItems(Order $order, array $orderItems, array $extra = []): array
    {
        $data = $this->helperService->returnFormat();

        $productTempArray = array_key_exists('product_temp_array', $extra) ? $extra['product_temp_array'] : null;
        $correlationToken = $order->correlation_token;

        $createOrderItemsPreProcessExtra = [
            'correlation_token' => $correlationToken,
        ];

        foreach($orderItems as $anOrderItem){
            $productId = $anOrderItem['product_id'];
            $getProductResponse = $this->productService->getProductById($productId);
            $product = $getProductResponse['result'];

            $productInterfaceObj = $this->helperService->makeObject($product->product_class, ['product'=> $product]);

            $orderItemCreatePreProcessResponse = $productInterfaceObj->orderItemCreatePreProcess($anOrderItem, $createOrderItemsPreProcessExtra);

                if (!$orderItemCreatePreProcessResponse['success']) {
                    $data['errors'] = true;
                    $data['message'] = $orderItemCreatePreProcessResponse['message'];
                    break;
                }
        
                $preProcessedOrderItem = $orderItemCreatePreProcessResponse['result'];
                $anOrderItem = is_array($preProcessedOrderItem) ? array_merge($anOrderItem, $preProcessedOrderItem) : $anOrderItem;
        
                $saveOrderItemData = $productInterfaceObj->createOrderItem($anOrderItem);
                $saveOrderItemData['order_id'] = $order->id;
                $newOrderItem = $this->orderItemModel::create($saveOrderItemData);

            $subItems = array_key_exists('sub_items', $anOrderItem) ? $anOrderItem['sub_items'] : [];
            foreach($subItems as $aSubItem){
                // Order item create pre-process
                $subItemProductId = $aSubItem['product_id'];

                $subItemProductReponse = $this->productService->getProductById($subItemProductId);
                $subItemProduct = $subItemProductReponse['result'];
                $subItemProductInterfaceObj = $this->helperService->makeObject($subItemProduct->product_class, ['product'=> $subItemProduct]);

                $productTempArray[$subItemProductId] = [
                    'product' => $subItemProduct,
                    'product_class' => $subItemProductInterfaceObj
                ];
                
                $subItemCreatePreProcessResponse = $subItemProductInterfaceObj->orderItemCreatePreProcess($aSubItem, $createOrderItemsPreProcessExtra);
        
                if (!$subItemCreatePreProcessResponse['success']) {
                    $data['errors'] = true;
                    $data['message'] = $subItemCreatePreProcessResponse['message'];
                    break;
                }

                $preProcessedSubItem = $subItemCreatePreProcessResponse['result'];
                $aSubItem = is_array($preProcessedSubItem) ? array_merge($aSubItem, $preProcessedSubItem) : $aSubItem;
        
                $saveSubItemData = $subItemProductInterfaceObj->createOrderItem($aSubItem);
                $saveSubItemData['order_id'] = $order->id;
                $saveSubItemData['linked_item_id'] = $newOrderItem->id;
                $this->orderItemModel::create($saveSubItemData);
            }
        }
        
        if(is_null($data['errors'])){
            $data['success'] = true;
        }
        
        return $data;
    }
    
    public function updateOrderItems(string $orderGuid, array $orderItems, array $extra = []): array
    {
        $data = $this->helperService->returnFormat();

        DB::beginTransaction();

        $order = $this->orderModel::where('order_guid', $orderGuid)->first();

        if(is_null($order)){
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::order/messages.order_not_found');
        }

        if(is_null($data['errors'])){
            // delete existing order items
            $this->orderItemModel::where('order_id', $order->id)->delete();

            // create new order items
            $createItemResponse = $this->createOrderItems($order, $orderItems, $extra);

            if(!$createItemResponse['success']){
                $data['errors'] = true;
            }
        }

        if(is_null($data['errors'])){
            $orderTotalsResponse = $this->calculateOrderTotals($order->order_guid);

            if(!$orderTotalsResponse['success']){
                $data['errors'] = true;
            }
            else{
                DB::commit();
                $data['success'] = true;
                $data['result']['order'] = $orderTotalsResponse['result'];
            }
        }

        return $data;
        
    }
    
    /**
     * calculateOrderTotals
     * calculates grand total and subtotal for an order
     * updates the order with the new totals
     *
     * @param  mixed $orderGuid
     * @return array returnFormat <result: OrderModel>
     */
    public function calculateOrderTotals(string $orderGuid): array
    {
        $data = $this->helperService->returnFormat();

        $order = $this->orderModel::where('order_guid', $orderGuid)
            ->with('order_items')
            ->first();

        if(is_null($order)){
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::order/messages.order_not_found');
        }

        $orderCurrencyCode = $order->order_currency_code;
        $currencyRate = $this->currencyService->getCurrencyRateById($order->currency_rate_id);

        $orderBaseCurrencySubtotal = '0'; // Use string to preserve precision
        $orderBaseCurrencyGrandTotal = '0'; // Use string to preserve precision
        $orderSubtotal = $orderBaseCurrencySubtotal;
        $orderGrandTotal = $orderBaseCurrencyGrandTotal;

        $mainOrderItems = $order->main_order_items;

        foreach($mainOrderItems as $anOrderItem){

            $product = $anOrderItem->product;
            $orderItemMeta = json_decode($anOrderItem->order_item_meta);
            $orderItemMetaAmount = $orderItemMeta->amount ?? null;
            
            // Use bcmath for precision when calculating subtotals
            // only populate base currency totals if product price is in base currency
            // for certain orders like bill payment, we do not have a price, we take amount from order item meta
            if ($product->price_currency_code === $this->currencyService->getBaseCurrencyCode()) {
                if(!is_null($orderItemMetaAmount)) {
                    $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $orderItemMetaAmount, (string) $anOrderItem->quantity, config('citronel.decimals')), config('citronel.decimals'));

                } else {
                    $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $anOrderItem->product_price, (string) $anOrderItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
                }
            } else {
                if(!is_null($orderItemMetaAmount)) {
                    $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $orderItemMetaAmount, (string) $anOrderItem->quantity, config('citronel.decimals')), config('citronel.decimals'));

                } else {
                    $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $anOrderItem->product_price, (string) $anOrderItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
                }

                $orderGrandTotal = $orderSubtotal;
            }

            if(!is_null($anOrderItem->sub_items)){
                foreach($anOrderItem->sub_items as $aSubItem){
                    $subItemsIdsProcessed[] = $aSubItem->id;

                    $subItemProduct = $aSubItem->product;
                    $subItemMeta = json_decode($aSubItem->order_item_meta);
                    $subItemMetaAmount = $subItemMeta->amount ?? null;

                    if ($subItemProduct->price_currency_code === $this->currencyService->getBaseCurrencyCode()) {
                        if(!is_null($subItemMetaAmount)) {
                            $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $subItemMetaAmount, (string) $aSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
        
                        } else {
                            $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $aSubItem->product_price, (string) $aSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
                        }
                    } else {
                        if(!is_null($subItemMetaAmount)) {
                            $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $subItemMetaAmount, (string) $aSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
        
                        } else {
                            $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $aSubItem->product_price, (string) $aSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
                        }
    
                        $orderGrandTotal = $orderSubtotal;
                    }
                }
            }
        }

        // Ensure precision when adding subtotals and grand totals
        if (is_null($data['errors'])) {
            $orderBaseCurrencyGrandTotal = bcadd($orderBaseCurrencyGrandTotal, $orderBaseCurrencySubtotal, config('citronel.decimals'));
    
            if (!$order->lock_currency && ($orderCurrencyCode !== $this->currencyService->getBaseCurrencyCode())) {
                $orderSubtotal = $this->currencyService->convertAmount($orderBaseCurrencySubtotal, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
                $orderGrandTotal = $this->currencyService->convertAmount($orderBaseCurrencyGrandTotal, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
            }
    
            // Update order
            $updateOrderSaveData = [
                'order_base_currency_subtotal' => (string) $orderBaseCurrencySubtotal,
                'order_base_currency_grand_total' => (string) $orderBaseCurrencyGrandTotal,
                'order_subtotal' => (string) $orderSubtotal,
                'order_grand_total' => (string) $orderGrandTotal
            ];
    
            // Update database
            $this->orderModel::where('id', $order->id)->update($updateOrderSaveData);
    
            // Fetch the final order
            $order = $this->orderModel::with('order_items')->where('id', $order->id)->first();

            $data['result'] = $order;
            $data['success'] = true;
        }

        return $data;
    }
    
    /**
     * Method generateOrderItemSummary
     *
     * @param mixed $item [explicite description]
     * @param array $extra [explicite description]
     * // @todo sub items
     * @return array
     */
    public function generateOrderItemSummary($item, $extra = [])
    {
        $data = $this->helperService->returnFormat();

        $orderItemSummary = [];

        $productTempArray = array_key_exists('product_temp_array', $extra) ? $extra['product_temp_array'] : [];
        $currencyRate = array_key_exists('currency_rate', $extra) ? $extra['currency_rate'] : null;

        $product = $productTempArray['product'];
        $productInterfaceObj = $productTempArray['product_class'];
        
        $orderItemMeta = json_decode($item->order_item_meta);
        $orderItemMetaAmount = $orderItemMeta->amount ?? null;
        if (!is_null($orderItemMetaAmount)) {
            $item->product_price = (string) $orderItemMetaAmount;
        }

        $order = $item->order;
        
        if (!$order->lock_currency && ($order->order_currency_code  !== $product->price_currency_code)) {
            $item->product_price = $this->currencyService->convertAmount($item->product_price, $order->order_currency_code, $currencyRate, config('citronel.decimals'));
        }

        $orderItemSubtotal = bcmul((string) $item->product_price, (string) $item->quantity, config('citronel.decimals'));

        // Order summary
        $orderItemSummary[$item->id]['product_id'] = $item->product_id;
        $orderItemSummary[$item->id]['quantity'] = $item->quantity;
        $orderItemSummary[$item->id]['product_price'] = $this->currencyService->formatCurrencyAmount($item->product_price, $order->order_currency_code);
        $orderItemSummary[$item->id]['product_total_price'] = $this->currencyService->formatCurrencyAmount($orderItemSubtotal, $order->order_currency_code);

        $generateOrderItemSummaryResponse = $productInterfaceObj->generateOrderItemSummary($item);
        $orderItemSummary[$item->id] = array_merge($orderItemSummary[$item->id], $generateOrderItemSummaryResponse);

        $data['result'] = $orderItemSummary;
        $data['success'] = true;

        return $data;
    }

    public function generateOrderSummaryBeforeConfirmation($order, $extra = [])
    {
        $data = $this->helperService->returnFormat();

        // Add order number
        $orderSubtotal = '0';
        $orderGrandTotal = '0';
        $orderSummary = [];
        $productTempArray = [];
        $currencyRate = $extra['currency_rate'] ?? null;

        $orderItems = $order->order_items;
        foreach ($orderItems as $anOrderItem) {

            $itemProductId = $anOrderItem['product_id'];
            if (array_key_exists($itemProductId, $productTempArray)) {
                $itemProduct = $productTempArray[$itemProductId]['product'];
                $itemProductInterfaceObj = $productTempArray[$itemProductId]['product_class'];
            } else {
                $getProductReponse = $this->productService->getProductById($itemProductId);
                $subItemProduct = $getProductReponse['result'];
                $itemProductInterfaceObj = $this->helperService->makeObject($subItemProduct->product_class, ['product'=> $subItemProduct]);

                $productTempArray[$itemProductId] = [
                    'product' => $itemProductId,
                    'product_class' => $itemProductInterfaceObj
                ];
            }

            $generateOrderItemSummaryExtra = [
                'currency_rate' => $extra['currency_rate'] ?? null,
                'product_temp_array' => $productTempArray[$itemProductId],
            ];
            $generateOrderItemSummaryResponse = $this->generateOrderItemSummary($anOrderItem, $generateOrderItemSummaryExtra);
            $orderSummary['items'][] = $generateOrderItemSummaryResponse['result'];

            $productTotalPrice = $generateOrderItemSummaryResponse['result'][$anOrderItem->id]['product_total_price']['raw'];

            $orderSubtotal = bcadd($orderSubtotal, (string) $productTotalPrice, config('citronel.decimals'));
        }

        $orderGrandTotal = $orderSubtotal;

        if (!$order->lock_currency && ($order->order_currency_code !== $this->currencyService->getBaseCurrencyCode())) {
            $orderSubtotal = $this->currencyService->convertAmount($orderSubtotal, $order->order_currency_code, $currencyRate, config('citronel.decimals'));

            $orderGrandTotal = $this->currencyService->convertAmount($orderGrandTotal, $order->order_currency_code, $currencyRate, config('citronel.decimals'));
        }

        $orderSummary['totals']['sub_total'] = $this->currencyService->formatCurrencyAmount($orderSubtotal, $order->order_currency_code);
        $orderSummary['totals']['grand_total'] = $this->currencyService->formatCurrencyAmount($orderGrandTotal, $order->order_currency_code);

        $order->setRelations([]);
        $orderSummary['order'] = $order;

        $data['result'] = $orderSummary;
       
        return $data;
    }

    public function validateMaxItemsPerOrder($requestArray, $maxItemsPerOrder)
    {
        $data = $this->helperService->returnFormat();

        /**
         * Get order items in request array, add quantity for each and compare with max items
         */
        $orderItems = array_key_exists('order_items', $requestArray) ? $requestArray['order_items'] : null;

        if (is_array($orderItems)) {
            $totalItems = 0;
            foreach ($orderItems as $anOrderItem) {
                $quantity = array_key_exists('quantity', $anOrderItem) ? $anOrderItem['quantity'] : 0;
                $totalItems += intval($quantity);
            }

            if ($totalItems > $maxItemsPerOrder) {
                $data['errors'] = true;
                $data['message'] = __('citronel-commerce::order/messages.max_items_per_order_exceeded');
            }
        }

        if (is_null($data['errors'])) {
            $data['success'] = true;
        }

        return $data;
    }

    public function getMaxItemsPerOrder()
    {
        return intval(config('citronel-order.max_items_per_order'));
    }
}
