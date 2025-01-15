<?php

return [
    'transaction_number_prefix' => env('TRANSACTION_NUMBER_PREFIX', 'esrp'),
    'payment_status' => [
        'unpaid' => [
            'event' => 'PaymentCreated',
            'text' => 'Payment created',
            'status' => 'unpaid'
        ],
        'cancelled' => [
            'event' => 'PaymentCancelled',
            'text' => 'Payment cancelled',
            'status' => 'cancelled'
        ],
        'paid' => [
            'event' => 'PaymentSuccessful',
            'text' => 'Payment successful',
            'status' => 'paid'
        ],
        'failed' => [
            'event' => 'PaymentFailed',
            'text' => 'Payment failed',
            'status' => 'failed'
        ],
        'expired' => [
            'event' => 'PaymentExpired',
            'text' => 'Payment expired',
            'status' => 'expired'
        ],
        'marked_for_refund' => [
            'event' => 'PaymentMarkedForRefund',
            'text' => 'Payment marked for refund',
            'status' => 'marked_for_refund'
        ],
        'processing_refund' => [
            'event' => 'PaymentProcessingRefund',
            'text' => 'Payment processing refund',
            'status' => 'processing_refund'
        ],
        'refunded' => [
            'event' => 'PaymentRefunded',
            'text' => 'Payment refunded',
            'status' => 'refunded'
        ],
    ],
    'payment_channels' => [
        'client_callback' => 'client_callback',
        'server_callback' => 'server_callback',
        'mobile_app' => 'mobile_app',
        'admin_app' => 'admin_app',
        'manual' => 'manual',
    ],
    /**
     * Number of seconds allowed between payment creation and payment update
     */
    'payment_update_time_gap_seconds' => env('PAYMENT_UPDATE_TIME_GAP_SECONDS', 2400),
    'payment_method_logo_path' => env('PAYMENT_METHOD_LOGO_PATH', 'storage/payment_methods/'),
    'vat_percentage' => 15,
    'payment_method_key' => [
        'myt_money' => 'myt_money',
        'credit_card_mpgs' => 'credit_card'
    ],
    'payment_myt_money_should_log' => env('PAYMENT_MYT_MONEY_SHOULD_LOG', false),
    // before creating a new payment, check if the last payment is paid at gateway in case we did not receive callback
    'verify_last_payment_before_create' => env('VERIFY_LAST_PAYMENT_BEFORE_CREATE', false)
];
