<?php

use Illuminate\Support\Facades\Route;
use aliirfaan\CitronelCommerce\Controllers\Order\ManualFulfillmentController;

Route::group([
    'prefix' => config('citronel-commerce.back_office_api_route_prefix', ''),
    'middleware' => config('citronel-commerce.middleware.back_office_api')
], function () {
    Route::post('/manual-fulfillment/{order_fulfillment_id}', [ManualFulfillmentController::class, 'fulfillItem']);
});
