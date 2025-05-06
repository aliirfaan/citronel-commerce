<?php

namespace aliirfaan\CitronelCommerce\Contracts\Order;

interface OrderProcessingStrategyInterface
{
    /**
     * Method groupProductOrderItems
     *
     * Set group id and group parent for order fulfillment items when creating fuflillments
     *
     *
     * @param mixed $order [explicite description]
     *
     * @return array
     */
    public function groupProductOrderItems($order);
    
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
}
