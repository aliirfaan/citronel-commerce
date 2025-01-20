<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\Payment\ManualPaymentConfirmationController;

Route::middleware(config('citronel-commerce.middleware.back_office_api'))->group(function () {
    Route::post('/manual-confirm/{gateway_merchant_transaction_no}', [ManualPaymentConfirmationController::class, 'confirmPayment']);
});
