<?php

use Illuminate\Support\Facades\Route;

use aliirfaan\CitronelCommerce\Http\Controllers\ProductCategory\ProductCategoryProductsController;

Route::group([
    'prefix' => config('citronel-commerce.api_route_prefix') ?? '',
    'middleware' => config('citronel-commerce.middleware.api')
], function () {
    Route::get('/{category}/products', [ProductCategoryProductsController::class, 'categoryProducts']);
});
