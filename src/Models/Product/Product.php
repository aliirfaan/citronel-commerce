<?php

namespace aliirfaan\CitronelCommerce\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use aliirfaan\CitronelCore\Models\CitronelBaseModel;
use aliirfaan\CitronelCommerce\Models\ProductCategory\ProductCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends CitronelBaseModel
{
    use HasFactory;

    protected $table = 'products';

    protected $keyType = 'string';

    protected $hidden = ['active', 'product_class', 'allow_transaction', 'send_order_notif', 'fulfillment_type', 'fulfillment_conditions', 'custom_value_1', 'custom_value_2', 'custom_value_3', 'custom_value_4', 'custom_value_5', 'created_at', 'updated_at', 'allow_auto_retry', 'max_auto_retry', 'allow_manual_retry', 'max_manual_retry'];

    public function product_category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
