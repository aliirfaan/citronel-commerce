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

    protected $fillable = [
        'id', 'order_id', 'product_id', 'product_price', 'quantity', 'order_item_meta', 'linked_item_id'
    ];

    public function order(): BelongsTo
    {
        $orderModel = config('citronel-order.order_model');
        
        return $this->belongsTo($orderModel);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order_fulfillments(): HasMany
    {
        return $this->hasMany(OrderFulfillment::class, 'order_item_id');
    }

    public function createValidationRules()
    {
        return [
            'product_id' => ['bail', 'required', 'max:20'],
            'product_price' => ['bail', 'nullable', 'numeric'],
            'quantity' => ['bail', 'nullable', 'numeric', 'min:1', 'max:10'],
            'order_item_meta' => ['bail', 'nullable', 'array'],
            'sub_items' => ['bail', 'nullable', 'array', 'size:1']
        ];
    }

    public function reviewValidationRules()
    {
        return [
            'quantity' => ['bail', 'required', 'numeric'],
        ];
    }

    public function createValidationRulesMessages()
    {
        return [
            'quantity.max' => __('citronel-commerce::order/messages.order_item_quantity_max')
        ];
    }

    // Linked parent item (the one this item is linked to)
    public function linkedItem()
    {
        return $this->belongsTo(OrderItem::class, 'linked_item_id');
    }

    // Items that are linked to this item (children)
    public function linkedItems()
    {
        return $this->hasMany(OrderItem::class, 'linked_item_id');
    }
}
