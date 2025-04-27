<?php

use Illuminate\Support\Facades\Route;
use aliirfaan\CitronelCommerce\Http\Controllers\Payment\ManualPaymentConfirmationController;

Route::group([
    'prefix' => config('citronel-commerce.back_office_api_route_prefix') ?? '',
    'middleware' => config('citronel-commerce.middleware.back_office_api')
], function () {
    Route::post('/manual-confirm/{gateway_merchant_transaction_no}', [ManualPaymentConfirmationController::class, 'confirmPayment']);
});
