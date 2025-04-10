<?php

namespace aliirfaan\CitronelCommerce\Models\CurrencyRate;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $table = 'currency_rates';

    protected $hidden = ['id', 'created_at', 'updated_at', 'buying_rate'];

    protected $fillable = [
        'from_code', 'to_code', 'buying_rate', 'selling_rate', 'source_updated_at_local', 'refreshed_at'
    ];
}
