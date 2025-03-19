<?php

namespace aliirfaan\CitronelCommerce\Models\ProductCategory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use aliirfaan\CitronelCommerce\Models\Product\Product;

class ProductCategory extends Model
{
    use HasFactory;

    protected $table = 'product_categories';

    protected $keyType = 'string';

    protected $hidden = ['active', 'sort_order', 'created_at', 'updated_at'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }
}
