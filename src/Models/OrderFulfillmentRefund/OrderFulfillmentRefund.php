<?php

namespace aliirfaan\CitronelCommerce\Models\OrderFulfillmentRefund;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use aliirfaan\CitronelCommerce\Models\PaymentRefund\PaymentRefund;

class OrderFulfillmentRefund extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_fulfillment_refunds';

    protected $casts = [
        'refund_amount' => 'float',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'id',
        'order_fulfillment_id',
        'payment_refund_id',
        'return_actor_id',
        'return_status',
        'returned_at',
        'refund_amount'
    ];

    public function paymentRefund(): BelongsTo
    {
        return $this->belongsTo(PaymentRefund::class);
    }
}
