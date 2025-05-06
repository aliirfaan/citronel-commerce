<?php

namespace aliirfaan\CitronelCommerce\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use aliirfaan\CitronelCommerce\Listeners\Order\SendOrderReceipt;
use aliirfaan\CitronelCommerce\Listeners\Order\FulfillmentFailure;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        Event::listen(
            FulfillmentFailure::class,
            SendOrderReceipt::class,
        );
    }
}
