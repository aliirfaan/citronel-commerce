<?php

namespace aliirfaan\CitronelCommerce\Enums\Refund;

enum ReturnStatus: string
{
    case CREATED = 'created';
    case PROCESSING = 'processing';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
}
