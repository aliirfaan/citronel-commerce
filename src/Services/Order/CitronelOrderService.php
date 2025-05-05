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
        ];
    
        $newOrder = $this->orderModel::create($orderSaveData);
    
        // Add order number
        $orderBaseCurrencySubtotal = '0'; // Use string to preserve precision
        $orderBaseCurrencyGrandTotal = '0'; // Use string to preserve precision
        $orderSubtotal = $orderBaseCurrencySubtotal;
        $orderGrandTotal = $orderBaseCurrencyGrandTotal;
        $orderSummary = [];
    
        $productTempArray = array_key_exists('product_temp_array', $extra) ? $extra['product_temp_array'] : null;
        $correlationToken = array_key_exists('correlation_token', $saveData) ? $saveData['correlation_token'] : null;
    
        $orderCreatePreProcessExtra = [
            'correlation_token' => $correlationToken
        ];
    
        foreach ($orderItems as $anOrderItem) {
    
            // Order item create pre-process
            $productId = $anOrderItem['product_id'];
            $product = $productTempArray[$productId]['product'];
            $productInterfaceObj = $productTempArray[$productId]['product_class'];
    
            $orderItemCreatePreProcessResponse = $productInterfaceObj->orderItemCreatePreProcess($anOrderItem, $orderCreatePreProcessExtra);
    
            if (!$orderItemCreatePreProcessResponse['success']) {
                $data['errors'] = true;
                $data['message'] = $orderItemCreatePreProcessResponse['message'];
                break;
            }
    
            $preProcessedOrderItem = $orderItemCreatePreProcessResponse['result'];
            $anOrderItem = is_array($preProcessedOrderItem) ? array_merge($anOrderItem, $preProcessedOrderItem) : $anOrderItem;
    
            $saveOrderItemData = $productInterfaceObj->createProductOrderItem($anOrderItem);
            $saveOrderItemData['order_id'] = $newOrder->id;
            $newOrderItem = $this->orderItemModel::create($saveOrderItemData);
    
            $orderItemMeta = json_decode($saveOrderItemData['order_item_meta']);
            $orderItemMetaAmount = $orderItemMeta->amount ?? null;
    
            // Use bcmath for precision when calculating subtotals
            // only populate base currency totals if product price is in base currency
            // for certain orders like bill payment, we do not have a price, we take amount from order item meta
            if ($product->price_currency_code === $this->currencyService->getBaseCurrencyCode()) {
                if(!is_null($orderItemMetaAmount)) {
                    $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $orderItemMetaAmount, (string) $newOrderItem->quantity, config('citronel.decimals')), config('citronel.decimals'));

                } else {
                    $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $newOrderItem->product_price, (string) $newOrderItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
                }
            } else {
                if(!is_null($orderItemMetaAmount)) {
                    $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $orderItemMetaAmount, (string) $newOrderItem->quantity, config('citronel.decimals')), config('citronel.decimals'));

                } else {
                    $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $newOrderItem->product_price, (string) $newOrderItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
                }

                $orderGrandTotal = $orderSubtotal;
            }
    
            // Order summary
            $orderItemSummary[$newOrderItem->id]['quantity'] = $newOrderItem->quantity;
            $orderItemSummary[$newOrderItem->id]['product_price'] = $newOrderItem->product_price;
            $orderItemSummary[$newOrderItem->id]['product_total_price'] = bcmul((string) $newOrderItem->product_price, (string) $newOrderItem->quantity, config('citronel.decimals'));
    
            if (!$newOrder->lock_currency && ($orderCurrencyCode !== $this->currencyService->getBaseCurrencyCode())) {
                $orderItemSummary[$newOrderItem->id]['product_price'] = $this->currencyService->convertAmount($newOrderItem->product_price, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));

                $orderItemSummary[$newOrderItem->id]['product_total_price'] = $this->currencyService->convertAmount($orderItemSummary[$newOrderItem->id]['product_total_price'], $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
            }
    
            $orderItemSummary[$newOrderItem->id]['product_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$newOrderItem->id]['product_price'], $orderCurrencyCode);

            $orderItemSummary[$newOrderItem->id]['product_total_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$newOrderItem->id]['product_total_price'], $orderCurrencyCode);
    
            $generateOrderItemSummaryResponse = $productInterfaceObj->generateOrderItemSummary($newOrderItem, $orderCreatePreProcessExtra);
            $orderItemSummary[$newOrderItem->id] = array_merge($orderItemSummary[$newOrderItem->id], $generateOrderItemSummaryResponse);
            $orderSummary[$newOrderItem->id] = $orderItemSummary[$newOrderItem->id];

            // process sub items
            $subItems = array_key_exists('sub_items', $anOrderItem) ? $anOrderItem['sub_items'] : [];
            foreach ($subItems as $aSubItem) {
                // Order item create pre-process
                $subItemProductId = $aSubItem['product_id'];
                if (array_key_exists($subItemProductId, $productTempArray)) {
                    $subItemProduct = $productTempArray[$subItemProductId]['product'];
                    $subItemProductInterfaceObj = $productTempArray[$subItemProductId]['product_class'];
                } else {
                    $subItemProductReponse = $this->productService->getProductById($subItemProductId);
                    $subItemProduct = $subItemProductReponse['result'];
                    $subItemProductInterfaceObj = $this->helperService->makeObject($subItemProduct->product_class, ['product'=> $subItemProduct]);

                    $productTempArray[$subItemProductId] = [
                        'product' => $subItemProduct,
                        'product_class' => $subItemProductInterfaceObj
                    ];
                }
                
                $subItemCreatePreProcessResponse = $subItemProductInterfaceObj->orderItemCreatePreProcess($aSubItem, $orderCreatePreProcessExtra);
        
                if (!$subItemCreatePreProcessResponse['success']) {
                    $data['errors'] = true;
                    $data['message'] = $subItemCreatePreProcessResponse['message'];
                    break;
                }
        
                $preProcessedSubItem = $subItemCreatePreProcessResponse['result'];
                $aSubItem = is_array($preProcessedSubItem) ? array_merge($aSubItem, $preProcessedSubItem) : $aSubItem;
        
                $saveSubItemData = $subItemProductInterfaceObj->createProductOrderItem($aSubItem);
                $saveSubItemData['order_id'] = $newOrder->id;
                $saveSubItemData['linked_item_id'] = $newOrderItem->id;
                $newSubItem = $this->orderItemModel::create($saveSubItemData);
        
                $subItemMeta = json_decode($saveSubItemData['order_item_meta']);
                $subItemMetaAmount = $subItemMeta->amount ?? null;

                if ($subItemProduct->price_currency_code === $this->currencyService->getBaseCurrencyCode()) {
                    if(!is_null($subItemMetaAmount)) {
                        $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $subItemMetaAmount, (string) $newSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
    
                    } else {
                        $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $newSubItem->product_price, (string) $newSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
                    }
                } else {
                    if(!is_null($subItemMetaAmount)) {
                        $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $subItemMetaAmount, (string) $newSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
    
                    } else {
                        $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $newSubItem->product_price, (string) $newSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
                    }

                    $orderGrandTotal = $orderSubtotal;
                }
        
                // Order summary
                $orderItemSummary[$newSubItem->id]['quantity'] = $newSubItem->quantity;
                $orderItemSummary[$newSubItem->id]['product_price'] = $newSubItem->product_price;
                $orderItemSummary[$newSubItem->id]['product_total_price'] = bcmul((string) $newSubItem->product_price, (string) $newSubItem->quantity, config('citronel.decimals'));
        
                if (!$newOrder->lock_currency && ($orderCurrencyCode !== $this->currencyService->getBaseCurrencyCode())) {
                    $orderItemSummary[$newSubItem->id]['product_price'] = $this->currencyService->convertAmount($newSubItem->product_price, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));

                    $orderItemSummary[$newSubItem->id]['product_total_price'] = $this->currencyService->convertAmount($orderItemSummary[$newSubItem->id]['product_total_price'], $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
                }
        
                $orderItemSummary[$newSubItem->id]['product_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$newSubItem->id]['product_price'], $orderCurrencyCode);

                $orderItemSummary['product_total_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$newSubItem->id]['product_total_price'], $orderCurrencyCode);
        
                $generateOrderItemSummaryResponse = $productInterfaceObj->generateOrderItemSummary($newSubItem, $orderCreatePreProcessExtra);
                $orderItemSummary[$newSubItem->id] = array_merge($orderItemSummary[$newSubItem->id], $generateOrderItemSummaryResponse);
                $orderSummary[$newOrderItem->id]['sub_items'] = $orderItemSummary[$newSubItem->id];

                $subItemProductInterfaceObj = null;
            }
        }

        $productTempArray = null;
    
        // Ensure precision when adding subtotals and grand totals
        if (is_null($data['errors'])) {
            $orderBaseCurrencyGrandTotal = bcadd($orderBaseCurrencyGrandTotal, $orderBaseCurrencySubtotal, config('citronel.decimals'));
    
            if (!$newOrder->lock_currency && ($orderCurrencyCode !== $this->currencyService->getBaseCurrencyCode())) {
                $orderSubtotal = $this->currencyService->convertAmount($orderBaseCurrencySubtotal, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
                $orderGrandTotal = $this->currencyService->convertAmount($orderBaseCurrencyGrandTotal, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
            }
    
            // Update order
            $orderNumber = $this->generateOrderNumber($newOrder->id);
            $updateOrderSaveData = [
                'order_number' => $orderNumber,
                'order_base_currency_subtotal' => (string) $orderBaseCurrencySubtotal,
                'order_base_currency_grand_total' => (string) $orderBaseCurrencyGrandTotal,
                'order_subtotal' => (string) $orderSubtotal,
                'order_grand_total' => (string) $orderGrandTotal
            ];
    
            // Update database
            $this->orderModel::where('id', $newOrder->id)->update($updateOrderSaveData);
    
            DB::commit();
    
            // Fetch and format the final order
            $order = $this->orderModel::with('order_items')->where('id', $newOrder->id)->first();

            $order->subtotal = $this->currencyService->formatCurrencyAmount($order->order_subtotal, $orderCurrencyCode);
            
            $order->grand_total = $this->currencyService->formatCurrencyAmount($order->order_grand_total, $orderCurrencyCode);

            $order->summary = $orderSummary;

            $orderSummary = null;
            $orderItemSummary = null;
    
            $data['result'] = $order;
            $data['success'] = true;
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

        $order->subtotal = $this->currencyService->formatCurrencyAmount($order->order_subtotal, $orderCurrencyCode);
            
        $order->grand_total = $this->currencyService->formatCurrencyAmount($order->order_grand_total, $orderCurrencyCode);

        $data['result']['order'] = $order;
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
    
    /**
     * Method createOrderItem
     *
     * @param mixed $order [explicite description]
     * @param mixed $orderItems [explicite description]
     * @param array $extra [explicite description]
     *
     * //@todo check if product already exists and update quantity instead
     *
     * @return array
     */
    public function createOrderItem($order, $orderItems, $extra = [])
    {
        $data = $this->helperService->returnFormat();
    
        DB::beginTransaction();
    
        // Create order
        $orderCurrencyCode = $order->order_currency_code;
        $currencyRate = array_key_exists('currency_rate', $extra) ? $extra['currency_rate'] : null;
        $lockCurrency = $order->lock_currency;
    
        $orderBaseCurrencySubtotal = '0'; // Use string to preserve precision
        $orderBaseCurrencyGrandTotal = '0'; // Use string to preserve precision
        $orderSubtotal = $orderBaseCurrencySubtotal;
        $orderGrandTotal = $orderBaseCurrencyGrandTotal;
        $orderSummary = [];
    
        $productTempArray = array_key_exists('product_temp_array', $extra) ? $extra['product_temp_array'] : null;
        $correlationToken = array_key_exists('correlation_token', $extra) ? $extra['correlation_token'] : null;
    
        $orderCreatePreProcessExtra = [
            'correlation_token' => $correlationToken
        ];
    
        foreach ($orderItems as $anOrderItem) {
    
            // Order item create pre-process
            $productId = $anOrderItem['product_id'];
            $product = $productTempArray[$productId]['product'];
            $productInterfaceObj = $productTempArray[$productId]['product_class'];
    
            $orderItemCreatePreProcessResponse = $productInterfaceObj->orderItemCreatePreProcess($anOrderItem, $orderCreatePreProcessExtra);
    
            if (!$orderItemCreatePreProcessResponse['success']) {
                $data['errors'] = true;
                $data['message'] = $orderItemCreatePreProcessResponse['message'];
                break;
            }
    
            $preProcessedOrderItem = $orderItemCreatePreProcessResponse['result'];
            $anOrderItem = is_array($preProcessedOrderItem) ? array_merge($anOrderItem, $preProcessedOrderItem) : $anOrderItem;


            $orderItemObj = $this->orderItemModel::where('order_id', $order->id)
            ->where('product_id', $productId)
            ->first();
            if (!is_null($orderItemObj)) {
                $orderItemObj->quantity = $orderItemObj->quantity + $anOrderItem['quantity'];
                $orderItemObj->save();
            } else {
                $saveOrderItemData = $productInterfaceObj->createProductOrderItem($anOrderItem);
                $saveOrderItemData['order_id'] = $order->id;
                $orderItemObj = $this->orderItemModel::create($saveOrderItemData);
            }
    
            $orderItemMeta = json_decode($saveOrderItemData['order_item_meta']);
            $orderItemMetaAmount = $orderItemMeta->amount ?? null;
    
            // Use bcmath for precision when calculating subtotals
            // only populate base currency totals if product price is in base currency
            // for certain orders like bill payment, we do not have a price, we take amount from order item meta
            if ($product->price_currency_code === $this->currencyService->getBaseCurrencyCode()) {
                if(!is_null($orderItemMetaAmount)) {
                    $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $orderItemMetaAmount, (string) $orderItemObj->quantity, config('citronel.decimals')), config('citronel.decimals'));

                } else {
                    $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $orderItemObj->product_price, (string) $orderItemObj->quantity, config('citronel.decimals')), config('citronel.decimals'));
                }
            } else {
                if(!is_null($orderItemMetaAmount)) {
                    $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $orderItemMetaAmount, (string) $orderItemObj->quantity, config('citronel.decimals')), config('citronel.decimals'));

                } else {
                    $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $orderItemObj->product_price, (string) $orderItemObj->quantity, config('citronel.decimals')), config('citronel.decimals'));
                }

                $orderGrandTotal = $orderSubtotal;
            }
    
            // Order summary
            $orderItemSummary[$orderItemObj->id]['quantity'] = $orderItemObj->quantity;
            $orderItemSummary[$orderItemObj->id]['product_price'] = $orderItemObj->product_price;
            $orderItemSummary[$orderItemObj->id]['product_total_price'] = bcmul((string) $orderItemObj->product_price, (string) $orderItemObj->quantity, config('citronel.decimals'));
    
            if (!$order->lock_currency && ($orderCurrencyCode !== $this->currencyService->getBaseCurrencyCode())) {
                $orderItemSummary[$orderItemObj->id]['product_price'] = $this->currencyService->convertAmount($orderItemObj->product_price, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));

                $orderItemSummary[$orderItemObj->id]['product_total_price'] = $this->currencyService->convertAmount($orderItemSummary[$orderItemObj->id]['product_total_price'], $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
            }
    
            $orderItemSummary[$orderItemObj->id]['product_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$orderItemObj->id]['product_price'], $orderCurrencyCode);

            $orderItemSummary[$orderItemObj->id]['product_total_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$orderItemObj->id]['product_total_price'], $orderCurrencyCode);
    
            $generateOrderItemSummaryResponse = $productInterfaceObj->generateOrderItemSummary($orderItemObj, $orderCreatePreProcessExtra);
            $orderItemSummary[$orderItemObj->id] = array_merge($orderItemSummary[$orderItemObj->id], $generateOrderItemSummaryResponse);
            $orderSummary[$orderItemObj->id] = $orderItemSummary[$orderItemObj->id];

            // process sub items
            $subItems = array_key_exists('sub_items', $anOrderItem) ? $anOrderItem['sub_items'] : [];
            foreach ($subItems as $aSubItem) {
                // Order item create pre-process
                $subItemProductId = $aSubItem['product_id'];
                if (array_key_exists($subItemProductId, $productTempArray)) {
                    $subItemProduct = $productTempArray[$subItemProductId]['product'];
                    $subItemProductInterfaceObj = $productTempArray[$subItemProductId]['product_class'];
                } else {
                    $subItemProductReponse = $this->productService->getProductById($subItemProductId);
                    $subItemProduct = $subItemProductReponse['result'];
                    $subItemProductInterfaceObj = $this->helperService->makeObject($subItemProduct->product_class, ['product'=> $subItemProduct]);

                    $productTempArray[$subItemProductId] = [
                        'product' => $subItemProduct,
                        'product_class' => $subItemProductInterfaceObj
                    ];
                }
                
                $subItemCreatePreProcessResponse = $subItemProductInterfaceObj->orderItemCreatePreProcess($aSubItem, $orderCreatePreProcessExtra);
        
                if (!$subItemCreatePreProcessResponse['success']) {
                    $data['errors'] = true;
                    $data['message'] = $subItemCreatePreProcessResponse['message'];
                    break;
                }
        
                $preProcessedSubItem = $subItemCreatePreProcessResponse['result'];
                $aSubItem = is_array($preProcessedSubItem) ? array_merge($aSubItem, $preProcessedSubItem) : $aSubItem;
        
                $saveSubItemData = $subItemProductInterfaceObj->createProductOrderItem($aSubItem);
                $saveSubItemData['order_id'] = $order->id;
                $saveSubItemData['linked_item_id'] = $orderItemObj->id;
                $newSubItem = $this->orderItemModel::create($saveSubItemData);
        
                $subItemMeta = json_decode($saveSubItemData['order_item_meta']);
                $subItemMetaAmount = $subItemMeta->amount ?? null;

                if ($subItemProduct->price_currency_code === $this->currencyService->getBaseCurrencyCode()) {
                    if(!is_null($subItemMetaAmount)) {
                        $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $subItemMetaAmount, (string) $newSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
    
                    } else {
                        $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $newSubItem->product_price, (string) $newSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
                    }
                } else {
                    if(!is_null($subItemMetaAmount)) {
                        $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $subItemMetaAmount, (string) $newSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
    
                    } else {
                        $orderSubtotal = bcadd($orderSubtotal, bcmul((string) $newSubItem->product_price, (string) $newSubItem->quantity, config('citronel.decimals')), config('citronel.decimals'));
                    }

                    $orderGrandTotal = $orderSubtotal;
                }
        
                // Order summary
                $orderItemSummary[$newSubItem->id]['quantity'] = $newSubItem->quantity;
                $orderItemSummary[$newSubItem->id]['product_price'] = $newSubItem->product_price;
                $orderItemSummary[$newSubItem->id]['product_total_price'] = bcmul((string) $newSubItem->product_price, (string) $newSubItem->quantity, config('citronel.decimals'));
        
                if (!$order->lock_currency && ($orderCurrencyCode !== $this->currencyService->getBaseCurrencyCode())) {
                    $orderItemSummary[$newSubItem->id]['product_price'] = $this->currencyService->convertAmount($newSubItem->product_price, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));

                    $orderItemSummary[$newSubItem->id]['product_total_price'] = $this->currencyService->convertAmount($orderItemSummary[$newSubItem->id]['product_total_price'], $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
                }
        
                $orderItemSummary[$newSubItem->id]['product_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$newSubItem->id]['product_price'], $orderCurrencyCode);

                $orderItemSummary['product_total_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$newSubItem->id]['product_total_price'], $orderCurrencyCode);
        
                $generateOrderItemSummaryResponse = $productInterfaceObj->generateOrderItemSummary($newSubItem, $orderCreatePreProcessExtra);
                $orderItemSummary[$newSubItem->id] = array_merge($orderItemSummary[$newSubItem->id], $generateOrderItemSummaryResponse);
                $orderSummary[$orderItemObj->id]['sub_items'] = $orderItemSummary[$newSubItem->id];

                $subItemProductInterfaceObj = null;
            }
        }

        $productTempArray = null;
    
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
    
            DB::commit();
    
            // Fetch and format the final order
            $order = $this->orderModel::with('order_items')->where('id', $order->id)->first();

            $order->subtotal = $this->currencyService->formatCurrencyAmount($order->order_subtotal, $orderCurrencyCode);
            
            $order->grand_total = $this->currencyService->formatCurrencyAmount($order->order_grand_total, $orderCurrencyCode);

            $order->summary = $orderSummary;

            $orderSummary = null;
            $orderItemSummary = null;
    
            $data['result'] = $order;
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

    public function updateOrderItems(string $orderGuid, array $orderData, array $orderItems, array $extra = [])
    {
        $data = $this->helperService->returnFormat();

        DB::beginTransaction();

        $order = $this->orderModel::where('order_guid', $orderGuid)->first();

        if(is_null($order)){
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::order/messages.order_not_found');
        }

        $currentOrderItems = $order->order_items;
        $currentOrderItemsMap = $currentOrderItems->keyBy('product_id')->toArray();
        $newOrderItemsMap = collect($orderItems)->keyBy('product_id')->toArray();

        $orderItemsToCreate = array_diff_key($newOrderItemsMap, $currentOrderItemsMap);
        $orderItemsToDelete = array_diff_key($currentOrderItemsMap, $newOrderItemsMap);
        $orderItemsToUpdate = [];

        $productTempArray = array_key_exists('product_temp_array', $extra) ? $extra['product_temp_array'] : null;
        $correlationToken = array_key_exists('correlation_token', $orderData) ? $orderData['correlation_token'] : null;

        $orderUpdatePreProcessExtra = [
            'correlation_token' => $correlationToken,
        ];

        foreach($currentOrderItemsMap as $productId => $currentOrderItem){
            if (isset($newOrderItemsMap[$productId]) && $currentOrderItem['quantity'] != $newOrderItemsMap[$productId]['quantity']) {
                $orderItemsToUpdate[$productId] = $currentOrderItem;
                $orderItemsToUpdate[$productId]['quantity'] = $newOrderItemsMap[$productId]['quantity'];
            }
        }

         // @TODO: consider sub items
        if(!empty($orderItemsToUpdate)){
            foreach($orderItemsToUpdate as $anOrderItemToUpdate){
                $this->orderItemModel::where('id', $anOrderItemToUpdate['id'])->update([
                    'quantity' => $anOrderItemToUpdate['quantity']
                ]);
            }
        }

        // @TODO: consider sub items
        if(!empty($orderItemsToDelete)){
            foreach($orderItemsToDelete as $anOrderItemToDelete){
                $this->orderItemModel::where('id', $anOrderItemToDelete['id'])->delete();
            }
        }

        if(!empty($orderItemsToCreate)){
            foreach($orderItemsToCreate as $anOrderItem){
                $productId = $anOrderItem['product_id'];
                $getProductResponse = $this->productService->getProductById($productId);
                $product = $getProductResponse['result'];

                $productInterfaceObj = $this->helperService->makeObject($product->product_class, ['product'=> $product]);

                $orderItemCreatePreProcessResponse = $productInterfaceObj->orderItemCreatePreProcess($anOrderItem, $orderUpdatePreProcessExtra);

                if (!$orderItemCreatePreProcessResponse['success']) {
                    $data['errors'] = true;
                    $data['message'] = $orderItemCreatePreProcessResponse['message'];
                    break;
                }
        
                $preProcessedOrderItem = $orderItemCreatePreProcessResponse['result'];
                $anOrderItem = is_array($preProcessedOrderItem) ? array_merge($anOrderItem, $preProcessedOrderItem) : $anOrderItem;
        
                $saveOrderItemData = $productInterfaceObj->createProductOrderItem($anOrderItem);
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
                    
                    $subItemCreatePreProcessResponse = $subItemProductInterfaceObj->orderItemCreatePreProcess($aSubItem, $orderUpdatePreProcessExtra);
            
                    if (!$subItemCreatePreProcessResponse['success']) {
                        $data['errors'] = true;
                        $data['message'] = $subItemCreatePreProcessResponse['message'];
                        break;
                    }

                    $preProcessedSubItem = $subItemCreatePreProcessResponse['result'];
                    $aSubItem = is_array($preProcessedSubItem) ? array_merge($aSubItem, $preProcessedSubItem) : $aSubItem;
            
                    $saveSubItemData = $subItemProductInterfaceObj->createProductOrderItem($aSubItem);
                    $saveSubItemData['order_id'] = $order->id;
                    $saveSubItemData['linked_item_id'] = $newOrderItem->id;
                    $this->orderItemModel::create($saveSubItemData);
                }
            }
        }

        // recalculate order summary
        return $this->generateOrderSummary($orderGuid, $correlationToken);
        
    }

    public function generateOrderSummary(string $orderGuid, ?string $correlationToken = null)
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
        $orderSummary = [];

        $orderItems = $order->order_items;

        // stores sub items ids that have been processed to not repeat
        $subItemsIdsProcessed = [];

        $orderCreatePreProcessExtra = [
            'correlation_token' => $correlationToken
        ];

        foreach($orderItems as $anOrderItem){
            if(in_array($anOrderItem->id, $subItemsIdsProcessed)){
                continue;
            }

            $product = $anOrderItem->product;
            $productInterfaceObj = $this->helperService->makeObject($product->product_class, ['product'=> $product]);
            $orderItemMeta = json_decode($anOrderItem->order_item_meta);
            $orderItemMetaAmount = $orderItemMeta->amount ?? null;
            
            // Use bcmath for precision when calculating subtotals
            // only populate base currency totals if product price is in base currency
            // for certain orders like bill payment, we do not have a price, we take amount from order item meta
            if ($product->price_currency_code === $this->currencyService->getBaseCurrencyCode()) {
                if(!is_null($orderItemMetaAmount)) {
                    $orderBaseCurrencySubtotal = bcadd($orderBaseCurrencySubtotal, bcmul((string) $orderItemMetaAmount, (string) $anOrderItem   ->quantity, config('citronel.decimals')), config('citronel.decimals'));

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

            // Order summary
            $orderItemSummary[$anOrderItem->id]['quantity'] = $anOrderItem->quantity;
            $orderItemSummary[$anOrderItem->id]['product_price'] = $anOrderItem->product_price;
            $orderItemSummary[$anOrderItem->id]['product_total_price'] = bcmul((string) $anOrderItem->product_price, (string) $anOrderItem->quantity, config('citronel.decimals'));

            if (!$order->lock_currency && ($orderCurrencyCode !== $this->currencyService->getBaseCurrencyCode())) {
                $orderItemSummary[$anOrderItem->id]['product_price'] = $this->currencyService->convertAmount($anOrderItem->product_price, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));

                $orderItemSummary[$anOrderItem->id]['product_total_price'] = $this->currencyService->convertAmount($orderItemSummary[$anOrderItem->id]['product_total_price'], $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
            }

            $orderItemSummary[$anOrderItem->id]['product_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$anOrderItem->id]['product_price'], $orderCurrencyCode);

            $orderItemSummary[$anOrderItem->id]['product_total_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$anOrderItem->id]['product_total_price'], $orderCurrencyCode);
    
            $generateOrderItemSummaryResponse = $productInterfaceObj->generateOrderItemSummary($anOrderItem, $orderCreatePreProcessExtra);
            $orderItemSummary[$anOrderItem->id] = array_merge($orderItemSummary[$anOrderItem->id], $generateOrderItemSummaryResponse);
            $orderSummary[$anOrderItem->id] = $orderItemSummary[$anOrderItem->id];

            if(!is_null($anOrderItem->sub_items)){
                foreach($anOrderItem->sub_items as $aSubItem){
                    $subItemsIdsProcessed[] = $aSubItem->id;

                    $subItemProduct = $aSubItem->product;
                    $subItemProductInterfaceObj = $this->helperService->makeObject($subItemProduct->product_class, ['product'=> $subItemProduct]);
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
            
                    // Order summary
                    $orderItemSummary[$aSubItem->id]['quantity'] = $aSubItem->quantity;
                    $orderItemSummary[$aSubItem->id]['product_price'] = $aSubItem->product_price;
                    $orderItemSummary[$aSubItem->id]['product_total_price'] = bcmul((string) $aSubItem->product_price, (string) $aSubItem->quantity, config('citronel.decimals'));
            
                    if (!$order->lock_currency && ($orderCurrencyCode !== $this->currencyService->getBaseCurrencyCode())) {
                        $orderItemSummary[$aSubItem->id]['product_price'] = $this->currencyService->convertAmount($aSubItem->product_price, $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
    
                        $orderItemSummary[$aSubItem->id]['product_total_price'] = $this->currencyService->convertAmount($orderItemSummary[$aSubItem->id]['product_total_price'], $orderCurrencyCode, $currencyRate, config('citronel.decimals'));
                    }
            
                    $orderItemSummary[$aSubItem->id]['product_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$aSubItem->id]['product_price'], $orderCurrencyCode);
    
                    $orderItemSummary['product_total_price'] = $this->currencyService->formatCurrencyAmount($orderItemSummary[$aSubItem->id]['product_total_price'], $orderCurrencyCode);
            
                    $generateOrderItemSummaryResponse = $productInterfaceObj->generateOrderItemSummary($aSubItem, $orderCreatePreProcessExtra);
                    $orderItemSummary[$aSubItem->id] = array_merge($orderItemSummary[$aSubItem->id], $generateOrderItemSummaryResponse);
                    $orderSummary[$aSubItem->id]['sub_items'] = $orderItemSummary[$aSubItem->id];
                    
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
    
            DB::commit();
    
            // Fetch and format the final order
            $order = $this->orderModel::with('order_items')->where('id', $order->id)->first();

            $order->subtotal = $this->currencyService->formatCurrencyAmount($order->order_subtotal, $orderCurrencyCode);
            
            $order->grand_total = $this->currencyService->formatCurrencyAmount($order->order_grand_total, $orderCurrencyCode);

            $order->summary = $orderSummary;

            $orderSummary = null;
            $orderItemSummary = null;
    
            $data['result'] = $order;
            $data['success'] = true;
        }

        return $data;
    }
}
