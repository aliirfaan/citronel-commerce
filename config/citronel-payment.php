<?php

return [
    'payment_method_default' => env('PAYMENT_METHOD_DEFAULT', 'credit_card'),
    'transaction_number_prefix' => env('TRANSACTION_NUMBER_PREFIX', 'citrcom'),
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
    // before creating a new payment, check if the last payment is paid at gateway in case we did not receive callback
    'verify_last_payment_before_create' => env('VERIFY_LAST_PAYMENT_BEFORE_CREATE', false)
];
