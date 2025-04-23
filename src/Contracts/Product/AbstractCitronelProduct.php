<?php

namespace aliirfaan\CitronelCommerce\Contracts\Product;

use aliirfaan\CitronelCommerce\Models\Product\Product;
use aliirfaan\CitronelCommerce\Services\Product\CitronelProductService;
use aliirfaan\CitronelCommerce\Services\Helper\CitronelCommerceHelperService;

abstract class AbstractCitronelProduct
{
    /**
     * productModel
     *
     * @var mixed
     */
    protected $productModel;
    
    /**
     * productService
     *
     * @var mixed
     */
    protected $productService;
    
    /**
     * product
     *
     * @var mixed
     */
    public $product;

    /**
     * helperService
     *
     * @var mixed
     */
    public $helperService;

    public function __construct($product = null)
    {
        $this->productModel = new Product();
        $this->product = $product;
        $this->productService = new CitronelProductService();

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);
    }
}
