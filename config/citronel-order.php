<?php

/*
| order_number_prefix | String
| Prefix for order number generation
|
| order_history_range_month | Numeric
| Number of past months to fetch order history
|
| order_expiry_seconds | Numeric
| Number of seconds before order expires
|
| order_pending_fulfillment_check_timeframe_seconds | Numeric
| time interval to check for pending fulfillments before creating a new order
|
| order_create_resume_time_seconds | Numeric
| time to wait before resuming order creation
|
| verify_last_order_before_create | Bool
| before creating a new order, check if the last payment of previous order is paid at gateway in case we did not receive callback
|
| last_order_verification_timeframe_seconds | Numeric
| time interval to check for last order verification
|
| features.fulfillment_failure_support_notification_enabled | Bool
| enable support notification on fulfillment failure
|
| fulfillment_failure_support_to_address | String
| email address to send support notification
|
| order_model | String
| Order model to be used
*/
return [
    'order_number_prefix' => env('ORDER_NUMBER_PREFIX'),
    'order_history_range_month' => env('ORDER_HISTORY_RANGE_MONTH', 6),
    'order_expiry_seconds' => env('ORDER_EXPIRY_SECONDS', 1800),
    'order_pending_fulfillment_check_timeframe_seconds' => env('ORDER_PENDING_FULFILLMENT_CHECK_TIMEFRAME_SECONDS', 300),
    'order_create_resume_time_seconds' => env('ORDER_CREATE_RESUME_TIME_SECONDS', 300),
    'verify_last_order_before_create' => env('VERIFY_LAST_ORDER_BEFORE_CREATE', false),
    'verify_pending_fulfillments_before_create' => env('VERIFY_PENDING_FULFILLMENTS_BEFORE_CREATE', false),
    'last_order_verification_timeframe_seconds' => env('LAST_ORDER_VERIFICATION_TIMEFRAME_SECONDS', 1800),
    'features' => [
        'fulfillment_failure_customer_notification_enabled' => env('FULFILLMENT_FAILURE_CUSTOMER_NOTIFICATION_ENABLED', false),
        'fulfillment_failure_support_notification_enabled' => env('FULFILLMENT_FAILURE_SUPPORT_NOTIFICATION_ENABLED', false),
    ],
    'fulfillment_failure_support_to_address' => env('FULFILLMENT_FAILURE_SUPPORT_TO_ADDRESS', null),
    'order_model' => aliirfaan\CitronelCommerce\Models\Order\Order::class,
];
