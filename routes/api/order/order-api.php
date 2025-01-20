<?php

use Illuminate\Support\Facades\Route;

use App\Http\Middleware\Api\v1\Customer\CustomerJwtIsValid;
use App\Http\Middleware\Api\v1\Customer\EnsureCustomerIsActive;
use App\Http\Middleware\Api\v1\Customer\EnsureCustomerIsVerified;
use App\Http\Controllers\Api\v1\Order\OrderCreateController;
use App\Http\Controllers\Api\v1\Order\OrderReviewController;
use App\Http\Controllers\Api\v1\Order\OrderItemReviewController;
use App\Http\Controllers\Api\v1\Order\BundleConsumptionController;
use App\Http\Controllers\Api\v1\Order\OrderResendMailController;
use App\Http\Controllers\Api\v1\Order\BundleActivationController;
use App\Http\Controllers\Api\v1\Order\OrderTopUpController;

Route::middleware([CustomerJwtIsValid::class, EnsureCustomerIsActive::class, EnsureCustomerIsVerified::class])->group(function () {
    Route::post('/', [OrderCreateController::class, 'create']);
    Route::put('/{order_guid}/review', [OrderReviewController::class, 'review']);
    Route::put('/{order_guid}/items/{order_item_id}/review', [OrderItemReviewController::class, 'review']);
    Route::get('/{order_fulfillment_id}/consumption', [BundleConsumptionController::class, 'bundleConsumption']);
    Route::post('/{order_fulfillment_id}/email/resend', [OrderResendMailController::class, 'resendEmail']);
    Route::get('/{order_fulfillment_id}/activation', [BundleActivationController::class, 'bundleActivation']);
    Route::get('/{order_fulfillment_id}/top-ups', [OrderTopUpController::class, 'orderTopUps']);
});
