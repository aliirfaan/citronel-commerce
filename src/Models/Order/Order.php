<?php

namespace aliirfaan\CitronelCommerce\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use aliirfaan\CitronelCommerce\Models\Payment\Payment;
use aliirfaan\CitronelCommerce\Rules\CurrencyCode;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $hidden = ['id', 'currency_rate_id', 'correlation_token',
    'order_base_currency_subtotal', 'order_base_currency_tax_amount',
    'order_base_currency_discount_amount', 'order_base_currency_grand_total', 'expires_at', 'created_at', 'updated_at', 'fulfillment_fail_notif_sent'];

    protected $fillable = [
        'order_guid', 'actor_id', 'order_number', 'order_status', 'currency_rate_id', 'order_currency_code', 'order_subtotal', 'order_tax_amount', 'order_discount_amount', 'order_grand_total',
        'order_base_currency_subtotal', 'order_base_currency_tax_amount',
        'order_base_currency_discount_amount', 'order_base_currency_grand_total', 'correlation_token', 'expires_at', 'order_payment_method_configuration_id', 'fulfillment_fail_notif_sent', 'lock_currency'
    ];

    public function order_items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    /**
     * We are limiting  order items to only 1
     */
    public function createValidationRules()
    {
        return [
            'actor_id' => ['nullable'],
            'order_currency_code' => ['nullable', new CurrencyCode],
            'order_items' => ['bail', 'required', 'array', 'size:1'],
        ];
    }

    public function reviewValidationRules()
    {
        return [
            'order_currency_code' => ['nullable', new CurrencyCode],
            'order_payment_method_configuration_id' => ['bail', 'required', 'uuid'],
        ];
    }

    public function initiateRefundValidationRules($extra = [])
    {
        $rules = [
            'order_fulfillments' => ['bail'],
            'ticket_number' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
            'return_actor_id' => ['bail', 'required'],
        ];

        // if is_full_order_refund_allowed is not provided, then order_fulfillments is required
        if (array_key_exists('is_full_order_refund_allowed', $extra) && $extra['is_full_order_refund_allowed']) {
            $rules['order_fulfillments'][] = 'nullable';
        } else {
            $rules['order_fulfillments'][] = 'required';
        }
        $rules['order_fulfillments'] = array_merge($rules['order_fulfillments'], ['array', 'max:10']);

        return $rules;
    }

    public function updateRefundValidationRules()
    {
        return [
            'refund_transaction_no' => ['nullable', 'string', 'max:255'],
            'update_actor_id' => ['bail', 'required'],
        ];
    }
}
