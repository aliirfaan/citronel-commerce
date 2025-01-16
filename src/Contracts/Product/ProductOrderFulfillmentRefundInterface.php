<?php

namespace aliirfaan\CitronelCommerce\Contracts\Product;

interface ProductOrderFulfillmentRefundInterface
{
    /**
     * Mark the fulfillment item as returned/refunded by supplier if it was fulfilled
     *
     * Example: check return/refund status, etc
     *
     * @param array $extra
     *
     * @return string
     */
    public function processSupplierOrderFulfillmentItemReturn($item = null, $extra = []);
    
    /**
     * Process refund for the fulfillment item with respect to the product supplier
     * Example: calculate refund amount, etc
     *
     * @param array $extra
     *
     * @return null|array
     */
    public function processOrderFulfillmentItemRefund($item = null, $extra = []);
}
