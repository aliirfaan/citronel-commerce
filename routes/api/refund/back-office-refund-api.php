<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\Refund\InitiateOrderRefundController;
use App\Http\Controllers\Api\v1\Refund\UpdateOrderRefundController;

Route::middleware(config('citronel-commerce.middleware.back_office_api'))->group(function () {

    Route::post('/{order_guid}', [InitiateOrderRefundController::class, 'initiateOrderRefund']);
    Route::put('/{payment_refund_id}', [UpdateOrderRefundController::class, 'updateOrderRefund']);
});
