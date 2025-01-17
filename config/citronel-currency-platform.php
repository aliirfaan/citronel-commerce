<?php

/*
| currency_platform | String
| The class path of the currency platform that implements aliirfaan\CitronelCommerce\Contracts\CurrencyPlatform\CurrencyPlatformInterface interface.
| Example: App\Services\CurrencyPlatform\CurrencyPlatformService::class
*/

return [
    'currency_platform' => env('CITRONEL_CURRENCY_PLATFORM', null)
];
