<?php

namespace aliirfaan\CitronelCommerce\Services\Product;

use aliirfaan\CitronelErrorCatalogue\Traits\ErrorCatalogue;
use aliirfaan\CitronelCommerce\Models\Product\Product;

class CitronelProductService
{
    use ErrorCatalogue;

    /**
     * queryModel
     *
     * @var mixed
     */
    public $productModel;

    /**
     * helperService
     *
     * @var mixed
     */
    public $helperService;

    /**
     * mainProcess
     *
     * @var string
     */
    public $mainProcess;

    /**
     * Method __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->productModel = new Product();
        
        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);

        $this->mainProcess = 'product';
    }

    /**
     * Method getProductById
     *
     * @param string $id [explicite description]
     * @param int $active [explicite description]
     *
     * @return array
     */
    public function getProductById($id, $active = 1)
    {
        $data = $this->helperService->returnFormat();
  
        $result = $this->productModel->where('id', $id)
        ->where('active', $active)
        ->first();
        if (is_null($result)) {
          $data['errors'] = true;
        }

        if (is_null($data['errors'])) {
          $data['result'] = $result;
          $data['success'] = true;
        }
        
        return $data;
    }

    /**
     * Method validateProductForManualFulfillment
     * Validate if manual retry is allowed for this product
     *
     * @param mixed $product [explicite description]
     *
     * @return array
     */
    public function validateProductForManualFulfillment($product)
    {
        $data = $this->helperService->returnFormat();

        if (intval($product->allow_manual_retry) == 0) {
            $data['errors'] = true;
            $data['message'] = __('product/messages.product_fulfillment_manual_retry_disabled');
        } else {
            $data['success'] = true;
        }

        return $data;
    }
}
