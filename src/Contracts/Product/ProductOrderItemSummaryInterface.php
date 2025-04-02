<?php

namespace aliirfaan\CitronelCommerce\Contracts\Product;

interface ProductOrderItemSummaryInterface
{
    /**
     * generate order summary for item
     *
     * @param $extra array of validated extra details
     *
     * @return array
     */
    public function generateOrderItemSummary($item = null, $extra = []);
}
