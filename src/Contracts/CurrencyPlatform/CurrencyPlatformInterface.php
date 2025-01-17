<?php

namespace aliirfaan\CitronelCommerce\Contracts\CurrencyPlatform;

interface CurrencyPlatformInterface
{
    /**
     * Method refreshExchangeRate
     *
     * @param string $correlationToken [explicite description]
     *
     * @return mixed
     */
    public function refreshExchangeRate($correlationToken = null);
}
