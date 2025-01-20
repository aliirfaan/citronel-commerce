<?php

namespace aliirfaan\CitronelCommerce\Enums\Refund;

enum RefundStatus: string
{
    case CREATED = 'created';
    case PROCESSING = 'processing';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
}
