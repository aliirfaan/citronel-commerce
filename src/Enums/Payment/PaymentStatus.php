<?php

namespace aliirfaan\CitronelCommerce\Enums\Payment;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case CANCELLED = 'cancelled';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case MARKED_FOR_REFUND = 'marked_for_refund';
    case PROCESSING_REFUND = 'processing_refund';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
}
