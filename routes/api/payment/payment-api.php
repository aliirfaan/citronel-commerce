<?php

use Illuminate\Support\Facades\Route;
use aliirfaan\CitronelCommerce\Http\Controllers\Payment\PaymentCreateController;
use aliirfaan\CitronelCommerce\Http\Controllers\Payment\PaymentUpdateController;
use aliirfaan\CitronelCommerce\Http\Controllers\Payment\ActorPaymentController;
use aliirfaan\CitronelCore\Http\Middleware\CitronelCorrelationToken;

Route::group([
    'prefix' => config('citronel-commerce.api_route_prefix', ''),
    'middleware' => config('citronel-commerce.middleware.api')
], function () {
    Route::post('/{order_guid}', [PaymentCreateController::class, 'create']);
});

Route::middleware([CitronelCorrelationToken::class])->group(function () {
    Route::put('/{gateway_merchant_transaction_no}', [PaymentUpdateController::class, 'update']);

    Route::get('/{actor_id}/payments', [ActorPaymentController::class, 'actorPaymentsWithOrderItems']);
});
