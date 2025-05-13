<?php

namespace aliirfaan\CitronelCommerce\Contracts\Product;

interface ProductOrderItemInterface
{
    /**
     * validate details to correclty create/process the order
     *
     * @return array
     */
    public function orderItemCreateValidationRules($item = null, $extra = []);

    /**
     * Processing before order create
     *
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function orderItemCreatePreProcess($item = null, $extra = []);

    /**
     * Processing after order create
     *
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function orderItemCreatePostProcess($item = null, $extra = []);

    /**
     * validate details to correclty update order item
     *
     * @return array
     */
    public function orderItemUpdateValidationRules($item = null, $extra = []);
    
    /**
     * Method orderUpdatePostProcess
     *
     * @param $extra $extra [explicite description]
     *
     * @return array
     */
    public function orderItemUpdatePostProcess($item = null, $extra = []);
    
    /**
     * create/update order meta for the product
     *
     * @param $extra array of validated extra details
     *
     * @return array
     */
    public function generateOrderItemMeta($item = null, $extra = []);

    /**
     *
     * @param mixed fulfillment item
     * @param array $extra
     *
     * @return array
     */
    public function fulfillGroupItems($groupItems, $extra = []);
    
    /**
     * A message to display after successful order processing
     *
     * @return string
     */
    public function successItemFulfillmentMessage($item, $extra = []);

    
    /**
     * Method failedItemFulfillmentMessage
     *
     * @param mixed $item [explicite description]
     * @param array $extra [explicite description]
     *
     * @return void
     */
    public function failedItemFulfillmentMessage($item, $extra = []);
    
    /**
     * An id that the external service requires
     *
     * @param $order
     *
     * @return string
     */
    public function generateExternalId($item, $extra = []);
    
    /**
     * Notification message to send for fulfilled orders for this product
     *
     * @param $order $order
     * @param array $extra
     *
     * @return string
     */
    public function generateProductOrderItemNotificationMessage($item, $extra = []);
    
    /**
     * Get order amount for product
     * Order amount can be from:
     * - product price
     * - request array
     *
     * @param mixed $product [explicite description]
     * @param array $extra [explicite description]
     *
     * @return mixed
     */
    public function generateOrderItemAmount($item = null, $extra = []);
    
    /**
     * Specify columns for the product
     *
     * @param mixed $product [explicite description]
     * @param array $extra [explicite description]
     *
     * @return mixed
     */
    public function createOrderItem($item = null, $extra = []);
    
    /**
     * Specify columns for the product for order fulfillment
     *
     * @param mixed $item [explicite description]
     * @param array $extra [explicite description]
     *
     * @return mixed

     */
    public function createFulfillmentItem($item = null, $extra = []);

    /**
     * If we want to pass outside information when generating the order fulfillment item like counter, reference generation based on quantity, etc.
     *
     * @param mixed $item [explicite description]
     * @param array $extra [explicite description]
     *
     * @return mixed

     */
    public function createFulfillmentItemExtra($item = null, $extra = []);
    
    /**
     * Method getProductOrderFulfillmentItemType
     *
     * Process the item to know what type of fulfillment to execute: sync or async
     *
     * @param mixed $item [explicite description]
     * @param array $extra [explicite description]
     *
     * @return mixed
     */
    public function getFulfillmentItemType($item = null, $extra = []);
    
    /**
     * Method generateProductOrderFulfillmentItemUpdate
     *
     * Generate the update array for the order fulfillment item
     *
     * @param mixed $item [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function generateFulfillmentItemUpdate($item = null, $extra = []);

    /**
     * Call before fulfilling the order item
     * You can call other services to get additional data needed for the fulfillment
     * You can also generate pre process fulfillment info and send in response
     *
     * @param mixed fulfillment item
     * @param array $extra
     *
     * @return array
     */
    public function fulfillItemPreProcess($item, $extra = []);

    /**
     * A message to display when fulfillment type is async and that processing will be done in the background
     *
     * @return string
     */
    public function asyncItemFulfillmentMessage($item, $extra = []);
}
