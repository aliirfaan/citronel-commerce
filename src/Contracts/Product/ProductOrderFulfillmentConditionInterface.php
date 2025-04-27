<?php

namespace aliirfaan\CitronelCommerce\Contracts\Product;

interface ProductOrderFulfillmentConditionInterface
{
    
    /**
     * Method isMet
     *
     * @param mixed $item [explicite description]
     * @param array $params [explicite description]
     *
     * @return bool
     */
    public function isMet($item, array $params = []): bool;
}
