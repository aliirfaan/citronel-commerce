<?php

use Illuminate\Support\Facades\Route;
use aliirfaan\CitronelCommerce\Http\Controllers\Order\ManualFulfillmentController;
use aliirfaan\CitronelCommerce\Http\Controllers\Order\OrderPaymentsController;

Route::group([
    'prefix' => config('citronel-commerce.back_office_api_route_prefix') ?? '',
    'middleware' => config('citronel-commerce.middleware.back_office_api')
], function () {
    Route::post('/manual-fulfillment/{order_fulfillment_id}', [ManualFulfillmentController::class, 'fulfillItem']);
    Route::get('/{order_guid}/payments', [OrderPaymentsController::class, 'orderPayments']);
});
