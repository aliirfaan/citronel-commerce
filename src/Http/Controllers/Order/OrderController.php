<?php

namespace aliirfaan\CitronelCommerce\Controllers\Order;

use aliirfaan\CitronelCore\Http\Controllers\CitronelController;
use aliirfaan\CitronelCore\Traits\CitronelApiControllerTrait;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;

class OrderController extends CitronelController
{
    use CitronelApiControllerTrait;

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
