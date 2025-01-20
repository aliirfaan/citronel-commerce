<?php

namespace aliirfaan\CitronelCommerce\Controllers\Payment;

use aliirfaan\CitronelCore\Http\Controllers\CitronelController;
use aliirfaan\CitronelCore\Traits\CitronelApiControllerTrait;
use aliirfaan\CitronelCommerce\Models\Payment\Payment;

class PaymentController extends CitronelController
{
    use CitronelApiControllerTrait;

    public function __construct(Payment $modelApiCommand, Payment $modelApiQuery)
    {
        parent::__construct();

        $this->namespace = 'payment';
        $this->mainProcess = config('error-catalogue.process.payment.key');

        $this->modelApiCommand = $modelApiCommand;
        $this->modelApiQuery = $modelApiQuery;
    }
}
