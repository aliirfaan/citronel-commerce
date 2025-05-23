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
        'order' => [
            'code' => '201',
            'key' => 'order',
            'sub_process' => [
                'create' => [
                    'key' => 'create',
                    'name' => 'create',
                    'code' => '1',
                    'events' => [
                        'invalid_product' => [
                            'key' => 'invalid_product',
                            'name' => 'invalid_product',
                            'code' => '1',
                            'code_status' => 'invalid_product',
                        ],
                        'invalid_pre_process' => [
                            'key' => 'invalid_pre_process',
                            'name' => 'invalid_pre_process',
                            'code' => '2',
                            'code_status' => 'invalid_pre_process',
                        ],
                        'invalid_currency' => [
                            'key' => 'key',
                            'name' => 'invalid_currency',
                            'code' => '3',
                            'code_status' => null,
                        ],
                        'create_failure' => [
                            'key' => 'key',
                            'name' => 'create_failure',
                            'code' => '4',
                            'code_status' => null,
                        ],
                        'created' => [
                            'key' => 'key',
                            'name' => 'created',
                            'code' => '5',
                            'code_status' => null,
                        ],
                        'pending_fulfillment_block' => [
                            'key' => 'pending_fulfillment_block',
                            'name' => 'pending_fulfillment_block',
                            'code' => '6',
                            'code_status' => null,
                        ],
                        'max_items_validation_failed' => [
                            'key' => 'max_items_validation_failed',
                            'name' => 'max_items_validation_failed',
                            'code' => '7',
                            'code_status' => null,
                        ],
                    ],
                ],
                'review' => [
                    'key' => 'review',
                    'name' => 'review',
                    'code' => '2',
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
                        'invalid_currency' => [
                            'key' => 'invalid_currency',
                            'name' => 'invalid_currency',
                            'code' => '4',
                            'code_status' => 'invalid_currency',
                        ],
                    ]
                ],
                'item_review' => [
                    'key' => 'item_review',
                    'name' => 'order.item_review',
                    'code' => '3',
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
                        'invalid_order_item' => [
                            'key' => 'invalid_order_item',
                            'name' => 'invalid_order_item',
                            'code' => '3',
                            'code_status' => 'invalid_order_item',
                        ],
                        'invalid_product' => [
                            'key' => 'invalid_product',
                            'name' => 'invalid_product',
                            'code' => '4',
                            'code_status' => 'invalid_product',
                        ],
                        'invalid_pre_process' => [
                            'key' => 'invalid_pre_process',
                            'name' => 'invalid_pre_process',
                            'code' => '4',
                            'code_status' => 'invalid_pre_process',
                        ],
                    ]
                ],
                'fulfillment' => [
                    'key' => 'fulfillment',
                    'name' => 'fulfillment',
                    'code' => '4',
                    'events' => [
                        'item_fulfillment_processed' => [
                            'key' => 'item_fulfillment_processed',
                            'name' => 'item_fulfillment_processed',
                            'code' => '1',
                            'code_status' => 'item_fulfillment_processed',
                        ],
                    ]
                ],
                'manual_fulfillment' => [
                    'key' => 'manual_fulfillment',
                    'name' => 'manual_fulfillment',
                    'code' => '5',
                    'events' => [
                        'invalid_item' => [
                            'key' => 'invalid_item',
                            'name' => 'invalid_item',
                            'code' => '1',
                            'code_status' => 'invalid_item',
                        ],
                    ]
                ],
                'item_create' => [
                    'key' => 'item_create',
                    'name' => 'item_create',
                    'code' => '6',
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
                        'invalid_order_item' => [
                            'key' => 'invalid_order_item',
                            'name' => 'invalid_order_item',
                            'code' => '3',
                            'code_status' => 'invalid_order_item',
                        ],
                    ]
                ],
                'fulfill' => [
                    'key' => 'fulfill',
                    'name' => 'fulfill',
                    'code' => '7',
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
                    ]
                ],
                'order_summary' => [
                    'key' => 'order_summary',
                    'name' => 'order_summary',
                    'code' => '8',
                    'events' => [
                        'invalid_order' => [
                            'key' => 'invalid_order',
                            'name' => 'invalid_order',
                            'code' => '1',
                            'code_status' => 'invalid_order',
                        ],
                        'order_summary_generation_failed' => [
                            'key' => 'order_summary_generation_failed',
                            'name' => 'order_summary_generation_failed',
                            'code' => '2',
                            'code_status' => 'order_summary_generation_failed',
                        ],
                        'expired_order' => [
                            'key' => 'expired_order',
                            'name' => 'expired_order',
                            'code' => '3',
                            'code_status' => 'expired_order',
                        ],
                    ]
                ],
                'order_items_update' => [
                    'key' => 'create',
                    'name' => 'create',
                    'code' => '1',
                    'events' => [
                        'invalid_product' => [
                            'key' => 'invalid_product',
                            'name' => 'invalid_product',
                            'code' => '1',
                            'code_status' => 'invalid_product',
                        ],
                        'invalid_pre_process' => [
                            'key' => 'invalid_pre_process',
                            'name' => 'invalid_pre_process',
                            'code' => '2',
                            'code_status' => 'invalid_pre_process',
                        ],
                        'invalid_currency' => [
                            'key' => 'key',
                            'name' => 'invalid_currency',
                            'code' => '3',
                            'code_status' => null,
                        ],
                        'create_failure' => [
                            'key' => 'key',
                            'name' => 'create_failure',
                            'code' => '4',
                            'code_status' => null,
                        ],
                        'created' => [
                            'key' => 'key',
                            'name' => 'created',
                            'code' => '5',
                            'code_status' => null,
                        ],
                        'pending_fulfillment_block' => [
                            'key' => 'pending_fulfillment_block',
                            'name' => 'pending_fulfillment_block',
                            'code' => '6',
                            'code_status' => null,
                        ],
                        'max_items_validation_failed' => [
                            'key' => 'max_items_validation_failed',
                            'name' => 'max_items_validation_failed',
                            'code' => '7',
                            'code_status' => null,
                        ],
                    ],
                ],
            ]
        ],
    ],
];
