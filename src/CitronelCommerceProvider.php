<?php

namespace aliirfaan\CitronelCommerce;

use aliirfaan\CitronelCommerce\Services\Currency\CitronelCurrencyService;

class CitronelCommerceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/citronel-order.php' => config_path('citronel-order.php'),
            __DIR__.'/../config/citronel-payment.php' => config_path('citronel-payment.php'),
            __DIR__.'/../config/citronel-currency-platform.php' => config_path('citronel-currency-platform.php'),
        ]);
    }

    public function register()
    {
        $this->app->singleton(CitronelCurrencyService::class, function ($app) {
            $parameter = config('citronel-currency-platform.currency_platform'); // or any other way to get the parameter
            return new CitronelCurrencyService($parameter);
        });
    }
}
