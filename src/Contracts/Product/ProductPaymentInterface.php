<?php

namespace aliirfaan\CitronelCommerce\Contracts\Product;

interface ProductPaymentInterface
{
    /**
     * Generates a description to send to payment gateway
     * Useful if payment payment has remarks field
     *
     * @param object $product
     * @param array $extra
     *
     * @return string
     */
    public function generateProductPaymentDescription($extra = []);
    
    /**
     * Generates additional headers to send to payment gateway
     *
     * @param array $extra
     *
     * @return null|array
     */
    public function generateProductPaymentAdditionalInfo($extra = []);
}
