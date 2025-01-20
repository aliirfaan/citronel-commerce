<?php

namespace aliirfaan\CitronelCommerce\Models\Order;

use App\Models\Customer\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderFulfillment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_fulfillments';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'id', 'order_item_id', 'customer_id', 'order_id', 'product_id', 'order_item_meta', 'order_item_fulfillment_status'
    ];

    public function order_item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
