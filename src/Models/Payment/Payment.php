<?php

namespace aliirfaan\CitronelCommerce\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use aliirfaan\CitronelCore\Models\CitronelBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Payment extends CitronelBaseModel
{
    use HasFactory;

    protected $table = 'payments';

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['formatted_amount'];

    protected $hidden = ['id', 'order_id', 'payment_remarks',
    'gateway_first_leg_response_code', 'gateway_first_leg_response_message',
    'gateway_first_leg_transaction_no_1', 'gateway_first_leg_transaction_no_2', 'created_at', 'updated_at'];

    protected $fillable = [
        'payment_guid', 'order_id', 'payment_method_configuration_id', 'payment_status', 'gateway_merchant_transaction_no', 'currency_code', 'subtotal', 'tax_amount', 'discount_amount', 'grand_total',
        'gateway_transaction_no', 'gateway_response_code',
        'gateway_response_status', 'gateway_response_message', 'payment_channel', 'payment_remarks', 'gateway_first_leg_response_code', 'gateway_first_leg_response_message', 'gateway_first_leg_transaction_no_1', 'gateway_first_leg_transaction_no_2', 'paid_at', 'cancelled_at', 'expired_at', 'card_holder', 'card_number', 'card_expiry'
    ];

    protected $timezoneAwareAttributes = [
        'paid_at',
        'cancelled_at',
        'expired_at',
    ];

    public function order(): BelongsTo
    {
        $orderModel = config('citronel-order.order_model');

        return $this->belongsTo($orderModel);
    }

    public function payment_method_configuration(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodConfiguration::class);
    }

    /**
     * Add formatted grand_total to the model
     * Issue with decimal number with trailing zero
     */
    protected function formattedAmount(): Attribute
    {
        $formattedAmount = [
            'grand_total' => (string) $this->attributes['grand_total'],
        ];

        return new Attribute(
            get: fn () => $formattedAmount,
        );
    }
}
