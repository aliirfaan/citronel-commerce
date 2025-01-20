<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\Order\ManualFulfillmentController;

Route::middleware(config('citronel-commerce.middleware.back_office_api'))->group(function () {
    Route::post('/manual-fulfillment/{order_fulfillment_id}', [ManualFulfillmentController::class, 'fulfillItem']);
});
