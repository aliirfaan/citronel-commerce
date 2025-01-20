<?php

namespace aliirfaan\CitronelCommerce\Controllers\Refund;

use aliirfaan\CitronelCore\Http\Controllers\CitronelController;
use aliirfaan\CitronelCore\Traits\CitronelApiControllerTrait;
use aliirfaan\CitronelCommerce\Models\Order\Order;

class RefundController extends CitronelController
{
    use CitronelApiControllerTrait;

    public function __construct(Order $modelApiCommand, Order $modelApiQuery)
    {
        parent::__construct();

        $this->namespace = 'refund';
        $this->mainProcess = config('error-catalogue.process.refund.key');

        $this->modelApiCommand = $modelApiCommand;
        $this->modelApiQuery = $modelApiQuery;
    }
}
