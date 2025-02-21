<?php

/*
| api_route_prefix | String
| Prefix for routes
|
| back_office_api_route_prefix | String
| Prefix for back office routes
|
| middleware.api | Array
| Middleware to be applied to all front API routes
|
| Normally you must authenticate the Actor before these 2 middlewares
| EnsureActorIsVerified::class
| EnsureActorIsActive::class
|
| middleware.back_office_api | Array
| Middleware to be applied to all back office API routes
|
| helper_service | String
| Helper service class to be used
|
*/
return [
    'api_route_prefix' => env('CITRONEL_COMMERCE_API_ROUTE_PREFIX', null),
    'back_office_api_route_prefix' => env('CITRONEL_COMMERCE_BACK_OFFICE_API_ROUTE_PREFIX', null),
    'middleware' => [
        'api' => [
            \aliirfaan\CitronelCore\Http\Middleware\CorrelationToken::class
        ],
        'back_office_api'=> [
            \aliirfaan\CitronelCore\Http\Middleware\CorrelationToken::class,
            \aliirfaan\CitronelCore\Http\Middleware\CheckBackOfficeApiKey::class
        ],
    ],
    'helper_service' => env('CITRONEL_COMMERCE_HELPER_SERVICE', aliirfaan\CitronelCommerce\Services\Helper\CitronelCommerceHelperService::class),
];
