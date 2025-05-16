<?php

namespace aliirfaan\CitronelCommerce\Contracts\Product;

interface ProductOrderFulfillmentSummaryInterface
{
    /**
     * generate order summary for item after fulfillment
     *
     * @param $extra array of validated extra details
     *
     * @return array
     */
    public function generateFulfillmentItemSummary($item = null, $extra = []);
}
