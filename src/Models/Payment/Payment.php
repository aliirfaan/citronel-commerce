<?php

namespace aliirfaan\CitronelCommerce\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use aliirfaan\CitronelCore\Models\CitronelBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use DB;

class Payment extends CitronelBaseModel
{
    use HasFactory;

    protected $table = 'payments';

    protected $hidden = ['id', 'order_id', 'payment_remarks',
    'gateway_first_leg_response_code', 'gateway_first_leg_response_message',
    'gateway_first_leg_transaction_no_1', 'gateway_first_leg_transaction_no_2', 'created_at', 'updated_at', 'timed_out_at'];

    protected $fillable = [
        'payment_guid', 'order_id', 'payment_method_configuration_id', 'payment_status', 'gateway_merchant_transaction_no', 'currency_code', 'subtotal', 'tax_amount', 'discount_amount', 'grand_total',
        'gateway_transaction_no', 'gateway_response_code',
        'gateway_response_status', 'gateway_response_message', 'payment_channel', 'payment_remarks', 'gateway_first_leg_response_code', 'gateway_first_leg_response_message', 'gateway_first_leg_transaction_no_1', 'gateway_first_leg_transaction_no_2', 'paid_at', 'cancelled_at', 'expired_at', 'card_holder', 'card_number', 'card_expiry', 'gateway_approval_code', 'gateway_receipt_no', 'timed_out_at', 'gateway_payment_mode'
    ];

    protected $timezoneAwareAttributes = [
        'paid_at',
        'cancelled_at',
        'expired_at',
        'timed_out_at'
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

    public function getPaymentsByOrderGuid($orderGuid)
    {
        return $this->whereHas('order', function ($query) use ($orderGuid) {
                $query->where('order_guid', $orderGuid);
            })
            ->with([
                'order:id,order_number',
                'payment_method_configuration:id,payment_method_id',
                'payment_method_configuration.payment_method:id,title'
            ])
            ->select([
                'id',
                'order_id',
                'payment_method_configuration_id',
                'payment_status',
                'gateway_merchant_transaction_no',
                'currency_code',
                'subtotal',
                'tax_amount',
                'discount_amount',
                'grand_total',
                'gateway_transaction_no',
                'gateway_response_code',
                'gateway_response_status',
                'gateway_response_message',
                'payment_channel',
                'paid_at',
                'cancelled_at',
                'expired_at',
                'card_holder',
                'card_number',
                'card_expiry',
                'gateway_receipt_no',
                'timed_out_at',
                'gateway_payment_mode',
            ])->orderBy('created_at', 'desc');
    }



}
