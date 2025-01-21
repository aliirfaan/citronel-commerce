<?php

namespace aliirfaan\CitronelCommerce\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use aliirfaan\CitronelCore\Models\Actor\CitronelActor;

class OrderFulfillment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_fulfillments';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'id', 'order_item_id', 'actor_id', 'order_id', 'product_id', 'order_item_meta', 'order_item_fulfillment_status'
    ];

    public function order_item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(CitronelActor::class);
    }
}
