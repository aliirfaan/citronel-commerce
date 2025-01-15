<?php

namespace aliirfaan\CitronelCommerce\Contracts\Traits\Payment;

trait PaymentGatewayCurrencyTrait
{
    /**
     * defaultCurrency
     *
     * @var string
     */
    public $defaultCurrency = 'MUR';

    /**
     * currencylistType
     *
     * @var string
     */
    public $currencylistType = 'whitelist';

    /**
     * currencylist
     * This is the list of currencies. Use as either whitelist or blacklist as on @property $currencylistType
     *
     * @var array
     */
    protected $currencylist = ['MUR'];
}
