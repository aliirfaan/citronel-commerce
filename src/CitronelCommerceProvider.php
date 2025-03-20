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

        $this->registerRoutes();
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

    protected function registerRoutes()
    {
        // Load default routes if user hasn't published custom versions
        $published = base_path('routes/vendor/citronel-commerce/api/order/back-office-order-api.php');
        $default = __DIR__.'/../routes/api/order/back-office-order-api.php';
        $this->loadRoutesFrom(file_exists($published) ? $published : $default);

        $published = base_path('routes/vendor/citronel-commerce/api/order/order-api.php');
        $default = __DIR__.'/../routes/api/order/order-api.php';
        $this->loadRoutesFrom(file_exists($published) ? $published : $default);

        $published = base_path('routes/vendor/citronel-commerce/api/payment/back-office-payment-api.php');
        $default = __DIR__.'/../routes/api/payment/back-office-payment-api.php';
        $this->loadRoutesFrom(file_exists($published) ? $published : $default);

        $published = base_path('routes/vendor/citronel-commerce/api/payment/payment-api.php');
        $default = __DIR__.'/../routes/api/payment/payment-api.php';
        $this->loadRoutesFrom(file_exists($published) ? $published : $default);

        $published = base_path('routes/vendor/citronel-commerce/api/refund/back-office-refund-api.php');
        $default = __DIR__.'/../routes/api/refund/back-office-refund-api.php';
        $this->loadRoutesFrom(file_exists($published) ? $published : $default);
        
        // Publish all route files
        $this->publishes([
            __DIR__.'/../routes/api/order/back-office-order-api.php' => base_path('routes/vendor/citronel-commerce/api/order/back-office-order-api.php'),

            __DIR__.'/../routes/api/order/order-api.php' => base_path('routes/vendor/citronel-commerce/api/order/order-api.php'),

            __DIR__.'/../routes/api/payment/back-office-payment-api.php' => base_path('routes/vendor/citronel-commerce/api/payment/back-office-payment-api.php'),

            __DIR__.'/../routes/api/payment/payment-api.php' => base_path('routes/vendor/citronel-commerce/api/payment/payment-api.php'),

            __DIR__.'/../routes/api/refund/back-office-refund-api.php' => base_path('routes/vendor/citronel-commerce/api/refund/back-office-refund-api.php'),

        ], 'citronel-commerce-routes');
    }
}
