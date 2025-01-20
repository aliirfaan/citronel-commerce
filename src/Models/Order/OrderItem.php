<?php

namespace aliirfaan\CitronelCommerce\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use aliirfaan\CitronelCommerce\Models\Product\Product;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_items';

    protected $hidden = ['created_at', 'updated_at'];

    protected $casts = [
        'product_price' => 'float',
        'quantity' => 'integer',
    ];

    protected $fillable = [
        'id', 'order_id', 'product_id', 'product_price', 'quantity', 'order_item_meta'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order_fulfillments(): HasMany
    {
        return $this->hasMany(OrderFulfillment::class, 'order_item_id');
    }
}
