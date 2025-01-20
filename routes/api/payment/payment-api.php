<?php

use Illuminate\Support\Facades\Route;
use aliirfaan\CitronelCommerce\Controllers\Payment\PaymentCreateController;
use aliirfaan\CitronelCommerce\Controllers\Payment\PaymentUpdateController;

Route::group([
    'prefix' => config('citronel-commerce.api_route_prefix', ''),
    'middleware' => config('citronel-commerce.middleware.api')
], function () {
    Route::post('/{order_guid}', [PaymentCreateController::class, 'create']);
});

Route::middleware([CorrelationToken::class])->group(function () {
    Route::put('/{gateway_merchant_transaction_no}', [PaymentUpdateController::class, 'update']);
});
