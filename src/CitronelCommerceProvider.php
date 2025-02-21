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
            __DIR__.'/../config/citronel-commerce.php' => config_path('citronel-commerce.php'),

            __DIR__.'/../config/citronel-currency-platform.php' => config_path('citronel-currency-platform.php'),

            __DIR__.'/../config/citronel-order-error-catalogue.php' => config_path('citronel-order-error-catalogue.php'),

            __DIR__.'/../config/citronel-order.php' => config_path('citronel-order.php'),

            __DIR__.'/../config/citronel-payment-error-catalogue.php' => config_path('citronel-payment-error-catalogue.php'),

            __DIR__.'/../config/citronel-payment.php' => config_path('citronel-payment.php'),

            __DIR__.'/../config/citronel-refund-error-catalogue.php' => config_path('citronel-refund-error-catalogue.php'),

            __DIR__.'/../config/citronel-refund.php' => config_path('citronel-refund.php'),
        ]);

        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'citronel-commerce');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/citronel-commerce.php', 'citronel-commerce');

        $this->mergeConfigFrom(__DIR__.'/../config/citronel-currency-platform.php', 'citronel-currency-platform');

        $this->mergeConfigFrom(__DIR__.'/../config/citronel-order-error-catalogue.php', 'citronel-order-error-catalogue');

        $this->mergeConfigFrom(__DIR__.'/../config/citronel-order.php', 'citronel-order');

        $this->mergeConfigFrom(__DIR__.'/../config/citronel-payment-error-catalogue.php', 'citronel-payment-error-catalogue');

        $this->mergeConfigFrom(__DIR__.'/../config/citronel-payment.php', 'citronel-payment');

        $this->mergeConfigFrom(__DIR__.'/../config/citronel-refund-error-catalogue.php', 'citronel-refund-error-catalogue');

        $this->mergeConfigFrom(__DIR__.'/../config/citronel-refund.php', 'citronel-refund');

        $this->app->singleton(CitronelCurrencyService::class, function ($app) {
            $parameter = config('citronel-currency-platform.currency_platform'); // or any other way to get the parameter
            return new CitronelCurrencyService($parameter);
        });
    }
}
