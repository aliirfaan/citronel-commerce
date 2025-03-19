<?php

namespace aliirfaan\CitronelCommerce\Controllers\ProductCategoryController;

use aliirfaan\CitronelCore\Http\Controllers\CitronelController;
use aliirfaan\CitronelCore\Traits\CitronelApiControllerTrait;
use aliirfaan\CitronelCommerce\Models\ProductCategory\ProductCategory;

class ProductCategoryController extends CitronelController
{
    use CitronelApiControllerTrait;

    public function __construct(ProductCategory $modelApiCommand, ProductCategory $modelApiQuery)
    {
        parent::__construct();

        $this->namespace = 'product-category';
        $this->mainProcess = $this->errorCatalogueService->getMainProcess('product-category');

        $this->modelApiCommand = $modelApiCommand;
        $this->modelApiQuery = $modelApiQuery;

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);
    }
}
