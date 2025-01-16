<?php

return [
    'order_number_prefix' => env('ORDER_NUMBER_PREFIX'),
    // @tod to remove as we are using enums
    'order_status' => [
        'created' => [
            'event' => 'OrderCreated',
            'text' => 'Order created',
            'status' => 'created'
        ],
        'pending_payment' => [
            'event' => 'PendingPayment',
            'text' => 'Pending payment',
            'status' => 'pending_payment'
        ],
        'paid' => [
            'event' => 'OrderPaid',
            'text' => 'Order paid',
            'status' => 'paid'
        ],
        'processing' => [
            'event' => 'OrderProcessing',
            'text' => 'Order processing',
            'status' => 'processing'
        ],
        'processing_retry' => [
            'event' => 'OrderProcessingRetry',
            'text' => 'Order processing retry',
            'status' => 'processing_retry'
        ],
        'fulfilled' => [
            'event' => 'OrderFulfilled',
            'text' => 'Order fulfilled',
            'status' => 'fulfilled'
        ],
        'unfulfilled' => [
            'event' => 'OrderNotFulfilled',
            'text' => 'Order not fulfilled',
            'status' => 'unfulfilled'
        ],
        'cancelled' => [
            'event' => 'OrderCancelled',
            'text' => 'Order cancelled',
            'status' => 'cancelled'
        ],
        'failed' => [
            'event' => 'OrderFailed',
            'text' => 'Order failed',
            'status' => 'failed'
        ],
        'on_hold' => [
            'event' => 'OrderOnHold',
            'text' => 'Order on hold',
            'status' => 'on_hold'
        ],
        'marked_for_refund' => [
            'event' => 'OrderMarkedForRefund',
            'text' => 'Order marked for refund',
            'status' => 'marked_for_refund'
        ],
        'processing_refund' => [
            'event' => 'OrderProcessingRefund',
            'text' => 'Order processing refund',
            'status' => 'processing_refund'
        ],
        'refunded' => [
            'event' => 'OrderRefunded',
            'text' => 'Order refunded',
            'status' => 'refunded'
        ],
    ],
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
