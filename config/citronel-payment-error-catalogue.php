<?php

/*
| Main processes are under process key.
| A main process may have many sub processes.
| A sub process may have many events.
| Format: [main-process code]-[sub-process code]-[event code]
| Example: 101-3-1
|
|--------------------------------------------------------------------------
| Format
|--------------------------------------------------------------------------
| see package citronel-error-catalogue format
*/

return [
    'process' => [
        'payment' => [
            'code' => '301',
            'key' => 'payment',
            'sub_process' => [
                'create' => [
                    'key' => 'create',
                    'name' => 'create',
                    'code' => '1',
                    'events' => [
                        'invalid_order' => [
                            'key' => 'invalid_order',
                            'name' => 'invalid_order',
                            'code' => '1',
                            'code_status' => 'invalid_order',
                        ],
                        'expired_order' => [
                            'key' => 'expired_order',
                            'name' => 'expired_order',
                            'code' => '2',
                            'code_status' => 'expired_order',
                        ],
                        'invalid_payment_method' => [
                            'key' => 'invalid_payment_method',
                            'name' => 'invalid_payment_method',
                            'code' => '3',
                            'code_status' => 'invalid_payment_method',
                        ],
                        'invalid_order_for_payment' => [
                            'key' => 'invalid_order_for_payment',
                            'name' => 'invalid_order_for_payment',
                            'code' => '4',
                            'code_status' => 'invalid_order_for_payment',
                        ],
                        'invalid_currency' => [
                            'key' => 'invalid_currency',
                            'name' => 'invalid_currency',
                            'code' => '5',
                            'code_status' => 'invalid_currency',
                        ],
                        'invalid_amount' => [
                            'key' => 'invalid_amount',
                            'name' => 'invalid_amount',
                            'code' => '6',
                            'code_status' => 'invalid_amount',
                        ],
                        'register_gateway_order_failure' => [
                            'key' => 'register_gateway_order_failure',
                            'name' => 'register_gateway_order_failure',
                            'code' => '7',
                            'code_status' => 'register_gateway_order_failure',
                        ],
                        'invalid_pre_process' => [
                            'key' => 'invalid_pre_process',
                            'name' => 'invalid_pre_process',
                            'code' => '8',
                            'code_status' => 'invalid_pre_process',
                        ],
                    ]
                ],
                'update' => [
                    'key' => 'update',
                    'name' => 'update',
                    'code' => '2',
                    'events' => [
                        'invalid_order_for_payment' => [
                            'key' => 'invalid_order_for_payment',
                            'name' => 'invalid_order_for_payment',
                            'code' => '1',
                            'code_status' => 'invalid_order_for_payment',
                        ],
                        'invalid_payment' => [
                            'key' => 'invalid_payment',
                            'name' => 'invalid_payment',
                            'code' => '2',
                            'code_status' => 'invalid_payment',
                        ],
                        'invalid_payment_method' => [
                            'key' => 'invalid_payment_method',
                            'name' => 'invalid_payment_method',
                            'code' => '3',
                            'code_status' => 'invalid_payment_method',
                        ],
                        'invalid_payment_channel' => [
                            'key' => 'invalid_payment_channel',
                            'name' => 'invalid_payment_channel',
                            'code' => '4',
                            'code_status' => 'invalid_payment_channel',
                        ],
                        'gateway_process_error' => [
                            'key' => 'gateway_process_error',
                            'name' => 'gateway_process_error',
                            'code' => '5',
                            'code_status' => 'gateway_process_error',
                        ],
                        'invalid_currency' => [
                            'key' => 'invalid_currency',
                            'name' => 'invalid_currency',
                            'code' => '6',
                            'code_status' => 'invalid_currency',
                        ],
                        'payment_timeout' => [
                            'key' => 'payment_timeout',
                            'name' => 'payment_timeout',
                            'code' => '7',
                            'code_status' => 'payment_timeout',
                        ],
                    ]
                ],
                'process' => [
                    'key' => 'process',
                    'name' => 'process',
                    'code' => '3',
                    'events' => []
                ],
                'get_actor_payments_with_order_items' => [
                    'key' => 'get_actor_payments_with_order_items',
                    'name' => 'get_actor_payments_with_order_items',
                    'code' => '5',
                    'events' => []
                ],
                'manual_payment_update' => [
                    'key' => 'manual_payment_update',
                    'name' => 'manual_payment_update',
                    'code' => '6',
                    'events' => [
                        'invalid_item' => [
                            'key' => 'invalid_item',
                            'name' => 'invalid_item',
                            'code' => '1',
                            'code_status' => 'invalid_item',
                        ],
                        'invalid_payment_method' => [
                            'key' => 'invalid_payment_method',
                            'name' => 'invalid_payment_method',
                            'code' => '2',
                            'code_status' => 'invalid_payment_method',
                        ],
                    ]
                ],
            ]
        ],
    ],
];
