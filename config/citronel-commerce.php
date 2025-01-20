<?php

/*
| api_route_prefix | String
| Prefix for routes
|
| middleware.api | Array
| Middleware to be applied to all front API routes
|
| Normally you must authenticate the customer before these 2 middlewares
| EnsureCustomerIsVerified::class
| EnsureCustomerIsActive::class
|
| middleware.back_office_api | Array
| Middleware to be applied to all back office API routes
|
*/
return [
    'api_route_prefix' => env('CITRONEL_COMMERCE_API_ROUTE_PREFIX', null),
    'middleware' => [
        'api' => [
            \aliirfaan\CitronelCore\Http\Middleware\CorrelationToken::class,
            \aliirfaan\CitronelCore\Http\Middleware\Customer\EnsureCustomerIsVerified::class,
            \aliirfaan\CitronelCore\Http\Middleware\Customer\EnsureCustomerIsActive::class
        ],
        'back_office_api'=> [
            \aliirfaan\CitronelCore\Http\Middleware\CorrelationToken::class,
            \aliirfaan\CitronelCore\Http\Middleware\CheckBackOfficeApiKey::class
        ],
    ],
];
