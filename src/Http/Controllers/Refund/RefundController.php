<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Refund;

use aliirfaan\CitronelCore\Http\Controllers\CitronelController;
use aliirfaan\CitronelCore\Traits\CitronelApiControllerTrait;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;

class RefundController extends CitronelController
{
    use CitronelApiControllerTrait;

    public function __construct(CitronelOrderService $orderService)
    {
        parent::__construct();

        $this->namespace = 'refund';
        $this->mainProcess = $this->errorCatalogueService->getMainProcess('refund');

        $this->modelApiCommand = $orderService->orderModel;
        $this->modelApiQuery = $orderService->orderModel;

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);
    }
}
