<?php

namespace aliirfaan\CitronelCommerce\Contracts\Order;

interface OrderProcessingStrategyInterface
{
    /**
     * Method groupFulfillments
     *
     * Set group id and group parent for order fulfillment items when creating fuflillments
     *
     *
     * @param mixed $order [explicite description]
     *
     * @return array
     */
    public function groupFulfillments($order);
    
    /**
     * Method orderCreatePreProcessValidationRules
     *
     * Set validation rules specific for this order strategy
     *
     * @return null | array
     */
    public function orderStrategyCreatePreCreateValidationRules();
    
    /**
     * Method shouldSendReceipt
     *
     * @return bool
     */
    public function shouldSendReceipt();
    
    /**
     * Method getReceiptChannels
     *
     * @return array
     */
    public function allowedReceiptChannels();
    
    /**
     * Method receiptNotificationClass
     *
     * @return null | string
     */
    public function receiptNotificationClass();
    
    /**
     * Method generateOrderFulfillmentSummary
     *
     * Generate order fulfillment summary for strategy 
     * @param mixed $order [explicite description]
     *
     * @return array
     */
    public function generateOrderFulfillmentSummary($order);
}
