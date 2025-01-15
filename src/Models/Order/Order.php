<?php

namespace aliirfaan\CitronelCommerce\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use aliirfaan\CitronelCommerce\Models\Payment\Payment;
use App\Models\Customer\Customer;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $casts = [
        'order_subtotal' => 'float',
        'order_tax_amount' => 'float',
        'order_discount_amount' => 'float',
        'order_grand_total' => 'float',
        'order_base_currency_subtotal' => 'float',
        'order_base_currency_tax_amount' => 'float',
        'order_base_currency_discount_amount' => 'float',
        'order_base_currency_grand_total' => 'float',
    ];

    protected $hidden = ['id', 'currency_rate_id', 'correlation_token',
    'order_base_currency_subtotal', 'order_base_currency_tax_amount',
    'order_base_currency_discount_amount', 'order_base_currency_grand_total', 'expires_at', 'created_at', 'updated_at', 'fulfillment_fail_notif_sent'];

    protected $fillable = [
        'order_guid', 'customer_id', 'order_number', 'order_status', 'currency_rate_id', 'order_currency_code', 'order_subtotal', 'order_tax_amount', 'order_discount_amount', 'order_grand_total',
        'order_base_currency_subtotal', 'order_base_currency_tax_amount',
        'order_base_currency_discount_amount', 'order_base_currency_grand_total', 'correlation_token', 'expires_at', 'order_payment_method_configuration_id', 'fulfillment_fail_notif_sent'
    ];

    protected function casts(): array
    {
        return [
            'order_subtotal' => 'float',
            'order_tax_amount' => 'float',
            'order_discount_amount' => 'float',
            'order_grand_total' => 'float',
            'order_base_currency_subtotal' => 'float',
            'order_base_currency_tax_amount' => 'float',
            'order_base_currency_discount_amount' => 'float',
            'order_base_currency_grand_total' => 'float',
        ];
    }

    public function order_items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
