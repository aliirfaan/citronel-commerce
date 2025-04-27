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
}
