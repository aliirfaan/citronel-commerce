<?php

use Illuminate\Support\Facades\Route;
use aliirfaan\CitronelCommerce\Http\Controllers\Order\OrderCreateController;
use aliirfaan\CitronelCommerce\Http\Controllers\Order\OrderReviewController;
use aliirfaan\CitronelCommerce\Http\Controllers\Order\OrderItemReviewController;
use aliirfaan\CitronelCommerce\Http\Controllers\Order\OrderItemCreateController;
use aliirfaan\CitronelCommerce\Http\Controllers\Order\OrderFulfillController;
use aliirfaan\CitronelCommerce\Http\Controllers\Order\OrderItemsUpdateController;

Route::group([
    'prefix' => config('citronel-commerce.api_route_prefix') ?? '',
    'middleware' => config('citronel-commerce.middleware.api')
], function () {
    Route::post('/', [OrderCreateController::class, 'create']);
    Route::put('/{order_guid}/review', [OrderReviewController::class, 'review']);
    Route::put('/{order_guid}/items', [OrderItemsUpdateController::class, 'update']);
    Route::put('/{order_guid}/items/{order_item_id}/review', [OrderItemReviewController::class, 'review']);
    Route::post('/{order_guid}/fulfill', [OrderFulfillController::class, 'fulfill']);
});
