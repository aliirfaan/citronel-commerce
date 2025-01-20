<?php

use Illuminate\Support\Facades\Route;
use aliirfaan\CitronelCommerce\Controllers\Order\OrderCreateController;
use aliirfaan\CitronelCommerce\Controllers\Order\OrderReviewController;
use aliirfaan\CitronelCommerce\Controllers\Order\OrderItemReviewController;

Route::group([
    'prefix' => config('citronel-commerce.api_route_prefix', ''),
    'middleware' => config('citronel-commerce.middleware.api')
], function () {
    Route::post('/', [OrderCreateController::class, 'create']);
    Route::put('/{order_guid}/review', [OrderReviewController::class, 'review']);
    Route::put('/{order_guid}/items/{order_item_id}/review', [OrderItemReviewController::class, 'review']);
});
