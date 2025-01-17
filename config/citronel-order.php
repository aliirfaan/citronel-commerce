<?php

return [
    'order_number_prefix' => env('ORDER_NUMBER_PREFIX'),
    'order_history_range_month' => env('ORDER_HISTORY_RANGE_MONTH', 6),
    'order_status_transitions' => [
        'processing' => ['fulfilled', 'failed', 'on_hold', 'refunded'],
        'unfulfilled' => ['fulfilled', 'failed', 'on_hold', 'refunded'],
        'fulfilled' => ['on_hold', 'refunded'],
    ],
    'order_expiry_seconds' => env('ORDER_EXPIRY_SECONDS', 1800),
    'order_pending_fulfillment_check_timeframe_seconds' => env('ORDER_PENDING_FULFILLMENT_CHECK_TIMEFRAME_SECONDS', 300), // time interval to check for pending fulfillments
    'order_create_resume_time_seconds' => env('ORDER_CREATE_RESUME_TIME_SECONDS', 300), // time to wait before resuming order creation,
    // before creating a new order, check if the last payment of previous order is paid at gateway in case we did not receive callback
    'verify_last_order_before_create' => env('VERIFY_LAST_ORDER_BEFORE_CREATE', false),
    'last_order_verification_timeframe_seconds' => env('LAST_ORDER_VERIFICATION_TIMEFRAME_SECONDS', 1800)
];
