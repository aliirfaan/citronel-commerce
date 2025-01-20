<?php

use Illuminate\Support\Facades\Route;

use App\Http\Middleware\Api\v1\Customer\CustomerJwtIsValid;
use App\Http\Middleware\Api\v1\Customer\EnsureCustomerIsActive;
use App\Http\Middleware\Api\v1\Customer\EnsureCustomerIsVerified;
use App\Http\Controllers\Api\v1\Payment\PaymentCreateController;
use App\Http\Controllers\Api\v1\Payment\PaymentUpdateController;

Route::middleware([CustomerJwtIsValid::class, EnsureCustomerIsActive::class, EnsureCustomerIsVerified::class])->group(function () {
    Route::post('/{order_guid}', [PaymentCreateController::class, 'create']);
});

Route::middleware([CorrelationToken::class])->group(function () {
    Route::put('/{gateway_merchant_transaction_no}', [PaymentUpdateController::class, 'update']);
});
