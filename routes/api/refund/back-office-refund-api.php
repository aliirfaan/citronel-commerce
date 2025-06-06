<?php

use Illuminate\Support\Facades\Route;
use aliirfaan\CitronelCommerce\Http\Controllers\Refund\InitiateOrderRefundController;
use aliirfaan\CitronelCommerce\Http\Controllers\Refund\UpdateOrderRefundController;
use aliirfaan\CitronelCommerce\Http\Controllers\Refund\GetOrderRefundController;

Route::group([
    'prefix' => config('citronel-commerce.back_office_api_route_prefix') ?? '',
    'middleware' => config('citronel-commerce.middleware.back_office_api')
], function () {
    Route::post('/{order_guid}', [InitiateOrderRefundController::class, 'initiateOrderRefund']);
    Route::put('/{payment_refund_id}', [UpdateOrderRefundController::class, 'updateOrderRefund']);
    Route::get('/{order_guid}', [GetOrderRefundController::class, 'getOrderRefunds']);
});
