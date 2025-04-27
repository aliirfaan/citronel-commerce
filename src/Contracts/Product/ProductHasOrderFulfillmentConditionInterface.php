<?php

namespace aliirfaan\CitronelCommerce\Contracts\Product;

/**
 * ProductHasOrderFulfillmentConditionInterface
 *
 * Implements this interface if the product has order fulfillment conditions
 */
interface ProductHasOrderFulfillmentConditionInterface
{
    /**
     * Method checkFulfillmentConditions
     *
     * Get all fulfillment conditions
     *
     * @param mixed $item [explicite description]
     *
     * @return bool
     */
    public function checkFulfillmentConditions($item): bool;

    public function fulfillmentConditionResolver(string $conditionName): ?string;
}
