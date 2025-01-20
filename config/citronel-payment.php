<?php

/*
| payment_method_default | String
| Default payment method
|
| transaction_number_prefix | String
| Prefix for transaction number generation
|
| payment_update_time_gap_seconds | Numeric
| Check if payment is updated within a reasonable time frame. Number of seconds allowed between payment creation and payment update
|
| payment_method_logo_path | String
| Path to payment method logos
|
| vat_percentage | Numeric
| VAT percentage
|
| verify_last_payment_before_create | Bool
| before creating a new payment, check if the last payment is paid at gateway in case we did not receive callback
|
| last_payment_verification_timeframe_seconds | Numeric
| time interval to check for last payment verification
|
*/
return [
    'payment_method_default' => env('PAYMENT_METHOD_DEFAULT', 'credit_card'),
    'transaction_number_prefix' => env('TRANSACTION_NUMBER_PREFIX', 'citrcom'),
    'payment_update_time_gap_seconds' => env('PAYMENT_UPDATE_TIME_GAP_SECONDS', 2400),
    'payment_method_logo_path' => env('PAYMENT_METHOD_LOGO_PATH', 'storage/payment_methods/'),
    'vat_percentage' => env('VAT_PERCENTAGE', 15),
    'verify_last_payment_before_create' => env('VERIFY_LAST_PAYMENT_BEFORE_CREATE', false)
];
