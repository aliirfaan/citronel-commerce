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
        'refund' => [
            'code' => '401',
            'key' => 'refund',
            'sub_process' => [
                'refund_order' => [
                    'key' => 'refund_order',
                    'name' => 'refund_order',
                    'code' => '1',
                    'events' => [
                        'invalid_order' => [
                            'key' => 'invalid_order',
                            'name' => 'invalid_order',
                            'code' => '1',
                            'code_status' => 'invalid_order',
                        ],
                        'refund_initiation_failed' => [
                            'key' => 'refund_initiation_failed',
                            'name' => 'refund_initiation_failed',
                            'code' => '2',
                            'code_status' => 'refund_initiation_failed',
                        ],
                    ]
                ],
                'update_refund_order' => [
                    'key' => 'update_refund_order',
                    'name' => 'update_refund_order',
                    'code' => '2',
                    'events' => [
                        'invalid_payment_refund' => [
                            'key' => 'invalid_payment_refund',
                            'name' => 'invalid_payment_refund',
                            'code' => '1',
                            'code_status' => 'invalid_payment_refund',
                        ],
                        'refund_update_failed' => [
                            'key' => 'refund_update_failed',
                            'name' => 'refund_update_failed',
                            'code' => '2',
                            'code_status' => 'refund_update_failed',
                        ],
                    ]
                ],
                'get_order_refunds' => [
                    'key' => 'get_order_refunds',
                    'name' => 'get_order_refunds',
                    'code' => '3',
                    'events' => []
                ],
            ]
        ],
    ],
];
