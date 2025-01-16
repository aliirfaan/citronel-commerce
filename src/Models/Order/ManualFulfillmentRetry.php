<?php

namespace aliirfaan\CitronelCommerce\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\OrderFulfillment\OrderFulfillment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ManualFulfillmentRetry extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'manual_fulfillment_retries';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'id', 'order_fulfillment_id', 'retry_user_id', 'retry_fulfillment_status', 'retried_at'
    ];

    public function order_fulfillment(): BelongsTo
    {
        return $this->belongsTo(OrderFulfillment::class);
    }
}
