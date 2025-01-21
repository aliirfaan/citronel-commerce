<?php

namespace aliirfaan\CitronelCommerce\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $keyType = 'string';

    protected $hidden = ['active', 'product_class', 'allow_transaction', 'send_order_notif', 'fulfillment_type', 'custom_value_1', 'custom_value_2', 'custom_value_3', 'custom_value_4', 'custom_value_5', 'created_at', 'updated_at'];

    public function createValidationRules()
    {
        return [
            'actor_id' => ['bail', 'required', 'uuid'],
            'order_items' => ['bail', 'required', 'array'],
        ];
    }
}
