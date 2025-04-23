<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Order;

use aliirfaan\CitronelCore\Http\Controllers\CitronelController;
use aliirfaan\CitronelCore\Traits\CitronelApiControllerTrait;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;

class OrderController extends CitronelController
{
    use CitronelApiControllerTrait;
    
    /**
     * lockCurrency
     *
     * Whether to convert the order currency if currency is not the same as default currency
     * This can be useful if we want to force a currency for some orders
     *
     * @var bool
     */
    public $lockCurrency = false;

    public function __construct(CitronelOrderService $orderService)
    {
        parent::__construct();

        $this->namespace = 'order';
        $this->mainProcess = $this->errorCatalogueService->getMainProcess('order');

        $this->modelApiCommand = $orderService->orderModel;
        $this->modelApiQuery = $orderService->orderModel;

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);
    }
}
