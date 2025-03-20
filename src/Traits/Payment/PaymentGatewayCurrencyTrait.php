<?php

namespace aliirfaan\CitronelCommerce\Traits\Payment;

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

    /**
     * Check if a currency is allowed.
     *
     * @param string $currency The currency to check.
     *
     * @return array True if the currency is allowed, false otherwise.
     */
    public function isCurrencyAllowed($currency)
    {
        $data = $this->helperService->returnFormat();
        $allowed = false;

        if ($this->currencylistType === 'whitelist') {
            $allowed = in_array($currency, $this->currencylist);
        } elseif ($this->currencylistType === 'blacklist') {
            $allowed = !in_array($currency, $this->currencylist);
        }

        $data['success'] = $allowed;

        if (!$allowed) {
            $data['message'] = __('payment/messages.invalid_currency');
        }

        return $data;
    }
}
