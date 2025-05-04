<?php

namespace aliirfaan\CitronelCommerce\Models\PaymentRefund;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use aliirfaan\CitronelCore\Models\CitronelBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use aliirfaan\CitronelCommerce\Models\Payment\Payment;
use Illuminate\Database\Eloquent\Relations\HasMany;
use aliirfaan\CitronelCommerce\Models\OrderFulfillmentRefund\OrderFulfillmentRefund;

class PaymentRefund extends CitronelBaseModel
{
    use HasFactory, HasUuids;

    protected $table = 'payment_refunds';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'id', 'order_id', 'ticket_number', 'refund_status', 'refund_reason', 'refund_grand_total', 'create_actor_id', 'refund_created_at', 'update_actor_id', 'refunded_at', 'refund_transaction_no'
    ];

    protected $timezoneAwareAttributes = [
        'refunded_at',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function OrderFulfillmentRefunds(): HasMany
    {
        return $this->hasMany(OrderFulfillmentRefund::class);
    }
}
