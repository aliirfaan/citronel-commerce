<?php

namespace aliirfaan\CitronelCommerce\Contracts\Order;

interface OrderFulfillmentStrategyInterface
{
    public function groupProductOrderItems($order);
}
