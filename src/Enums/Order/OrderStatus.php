<?php

namespace aliirfaan\CitronelCommerce\Enums\Order;

enum OrderStatus: string
{
    case CREATED = 'created';
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case PROCESSING_RETRY = 'processing_retry';
    case FULFILLED = 'fulfilled';
    case UNFULFILLED = 'unfulfilled';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';
    case ON_HOLD = 'on_hold';
    case MARKED_FOR_REFUND = 'marked_for_refund';
    case PROCESSING_REFUND = 'processing_refund';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
}
