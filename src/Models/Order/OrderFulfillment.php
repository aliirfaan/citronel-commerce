<?php

namespace aliirfaan\CitronelCommerce\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use aliirfaan\CitronelCore\Models\CitronelBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderFulfillment extends CitronelBaseModel
{
    use HasFactory, HasUuids;

    protected $table = 'order_fulfillments';

    protected $hidden = ['created_at', 'updated_at', 'order_item_fulfillment_grp_id', 'is_grp_parent'];

    protected $fillable = [
        'id', 'order_item_id', 'actor_id', 'order_id', 'product_id', 'order_item_meta', 'order_item_fulfillment_status','reseller_order_reference', 'supplier_order_id', 'correlation_token', 'fulfilled_at', 'auto_retry_count', 'manual_retry_count', 'result_code', 'result_message', 'previous_reseller_order_reference', 'product_code', 'order_item_fulfillment_grp_id', 'is_grp_parent', 'custom_value_1', 'custom_value_2', 'custom_value_3', 'custom_value_4', 'custom_value_5'
    ];

    protected $timezoneAwareAttributes = [
        'fulfilled_at',
    ];

    public function order_item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
