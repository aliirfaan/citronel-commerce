<?php

namespace aliirfaan\CitronelCommerce\Contracts\Product;

interface ProductOrderManualFulfillmentInterface
{
    /**
     * Get order from supplier
     * return success only if order exists if we will use it for manual fulfillment
     *
     * @param mixed $item [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function getSupplierOrderForManualFulfillment($order = null, $extra = []);
    
    /**
     * Method processSupplierOrderForManualFulfillment
     *
     * @param mixed $order [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function processSupplierOrderForManualFulfillment($order = null, $extra = []);
}
