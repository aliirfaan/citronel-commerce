<?php

namespace aliirfaan\CitronelCommerce\Controllers\Order;

use aliirfaan\CitronelCore\Http\Controllers\CitronelController;
use aliirfaan\CitronelCore\Traits\CitronelApiControllerTrait;
use aliirfaan\CitronelCommerce\Models\Order\Order;

class OrderController extends CitronelController
{
    use CitronelApiControllerTrait;

    public function __construct(Order $modelApiCommand, Order $modelApiQuery)
    {
        parent::__construct();

        $this->namespace = 'order';
        $this->mainProcess = $this->errorCatalogueService->getMainProcess('order');

        $this->modelApiCommand = $modelApiCommand;
        $this->modelApiQuery = $modelApiQuery;

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);
    }
}
