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

    public function __construct(CitronelCurrencyService $currencyService)
    {
        $this->loadOrderModel();
        $this->orderItemModel = new OrderItem();
        $this->auditService = new AuditLogService();

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);

        $this->currencyService = $currencyService;
        $this->errorCatalogueService = new CitronelErrorCatalogueService();
        $this->mainProcess = 'order';
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

        // create order
        $orderCurrencyCode = $saveData['order_currency_code'];
        $currencyRate = $saveData['currency_rate'];
        $orderSaveData = [
            'order_guid' => array_key_exists('order_guid', $saveData) ? $saveData['order_guid'] : $this->generateOrderGuid(),
            'actor_id' => array_key_exists('actor_id', $saveData) ? $saveData['actor_id'] : null,
            'order_status' => OrderStatus::CREATED->value,
            'currency_rate_id' => $currencyRate ? $currencyRate->id : null,
            'order_currency_code' => $orderCurrencyCode,
            'expires_at' => $this->calculateOrderExpiry(),
            'correlation_token' => array_key_exists('correlation_token', $saveData) ? $saveData['correlation_token'] : null,
        ];
        $newOrder = $this->orderModel::create($orderSaveData);

        // add order number
        $orderBaseCurrencySubtotal = 0;
        $orderBaseCurrencyGrandTotal = 0;

        $productTempArray =  array_key_exists('product_temp_array', $extra) ? $extra['product_temp_array'] : null;
        $correlationToken = array_key_exists('correlation_token', $saveData) ? $saveData['correlation_token'] : null;

        $orderCreatePreProcessExtra = [
            'correlation_token' => $correlationToken
        ];
        foreach ($orderItems as $anOrderItem) {

            // order item create pre process
            $productId = $anOrderItem['product_id'];
            $productInterfaceObj = $productTempArray[$productId]['product_class'];

            $orderItemCreatePreProcessResponse = $productInterfaceObj->orderItemCreatePreProcess($anOrderItem, $orderCreatePreProcessExtra);
            if (!$orderItemCreatePreProcessResponse['success']) {
                $data['errors'] = true;
                $data['message'] = $orderItemCreatePreProcessResponse['message'];
                break;
            }

            $preProcessedOrderItem = $orderItemCreatePreProcessResponse['result'];
            $anOrderItem = is_array($preProcessedOrderItem) ? array_merge($anOrderItem, $preProcessedOrderItem) : $anOrderItem;

            $productId = $anOrderItem['product_id'];
            $productInterfaceObj = $productTempArray[$productId]['product_class'];
            $saveOrderItemData = $productInterfaceObj->createProductOrderItem($anOrderItem);
            $saveOrderItemData['order_id'] = $newOrder->id;
            $newOrderItem = $this->orderItemModel::create($saveOrderItemData);

            $orderBaseCurrencySubtotal = floatval($orderBaseCurrencySubtotal) + floatval(($newOrderItem->product_price * $newOrderItem->quantity));
        }

        if (is_null($data['errors'])) {
            $orderBaseCurrencyGrandTotal = floatval($orderBaseCurrencyGrandTotal) + floatval($orderBaseCurrencySubtotal);

            $orderSubtotal = $orderBaseCurrencySubtotal;
            $orderGrandTotal = $orderBaseCurrencyGrandTotal;
            if ($orderCurrencyCode !== $this->currencyService->getBaseCurrencyCode()) {
                $orderSubtotal = $this->currencyService->convertAmount($orderBaseCurrencySubtotal, $orderCurrencyCode, $currencyRate);

                $orderGrandTotal = $this->currencyService->convertAmount($orderBaseCurrencyGrandTotal, $orderCurrencyCode, $currencyRate);
            }

            // update order
            $orderNumber = $this->generateOrderNumber($newOrder->id);
            $updateOrderSaveData = [
                'order_number' => $orderNumber,
                'order_base_currency_subtotal' => $orderBaseCurrencySubtotal,
                'order_base_currency_grand_total' => $orderBaseCurrencyGrandTotal,
                'order_subtotal' => $orderSubtotal,
                'order_grand_total' => $orderGrandTotal,
            ];
            $this->orderModel::where('id', $newOrder->id)->update($updateOrderSaveData);

            DB::commit();

            $order = $this->orderModel::with('order_items')->where('id', $newOrder->id)->first();

            $order->subtotal = $this->currencyService->formatCurrencyAmount($order->order_subtotal, $orderCurrencyCode);

            $order->total = $this->currencyService->formatCurrencyAmount($order->order_grand_total, $orderCurrencyCode);

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

        $order->total = $this->currencyService->formatCurrencyAmount($order->order_grand_total, $orderCurrencyCode);

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
            $data['message'] = __('citronel-commerce::citronel-commerce::order/messages.order_expired');
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

        $this->orderModel::where('id', $orderItem->id)->update($updateOrderItemSaveData);
        $order = $orderItem->order;
        $orderCurrencyCode = $order->order_currency_code;

        if ($quantity !== $orderItem->quantity) {

            $orderBaseCurrencySubtotal = 0;
            $orderBaseCurrencyGrandTotal = 0;
            $currencyRate = $this->currencyService->getCurrencyRateById($order->currency_rate_id);

            $orderItems = $order->order_items;
            foreach ($orderItems as $anOrderItem) {
                $orderBaseCurrencySubtotal = floatval($orderBaseCurrencySubtotal) + floatval(($anOrderItem->product_price * $anOrderItem->quantity));
            }

            $orderBaseCurrencyGrandTotal = floatval($orderBaseCurrencyGrandTotal) + floatval($orderBaseCurrencySubtotal);

            $orderSubtotal = $this->currencyService->convertAmount($orderBaseCurrencySubtotal, $orderCurrencyCode, $currencyRate);

            $orderGrandTotal = $this->currencyService->convertAmount($orderBaseCurrencyGrandTotal, $orderCurrencyCode, $currencyRate);

            $updateOrderSaveData = [
                'order_base_currency_subtotal' => $orderBaseCurrencySubtotal,
                'order_base_currency_grand_total' => $orderBaseCurrencyGrandTotal,
                'order_subtotal' => $orderSubtotal,
                'order_grand_total' => $orderGrandTotal,
            ];

            $this->orderModel::where('id', $order->id)->update($updateOrderSaveData);
        }

        DB::commit();

        $updatedOrder = $this->orderModel::with('order_items')->where('id', $order->id)->first();

        $updatedOrder->subtotal = $this->currencyService->formatCurrencyAmount($updatedOrder->order_subtotal, $orderCurrencyCode);

        $updatedOrder->total = $this->currencyService->formatCurrencyAmount($updatedOrder->order_grand_total, $orderCurrencyCode);

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
}
