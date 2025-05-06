<?php

namespace aliirfaan\CitronelCommerce\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use aliirfaan\CitronelCore\Models\CitronelBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ManualPaymentConfirmation extends CitronelBaseModel
{
    use HasFactory, HasUuids;

    protected $table = 'manual_payment_confirmations';

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'id', 'payment_id', 'update_actor_id', 'update_payment_status', 'update_gateway_transaction_no', 'update_gateway_response_code', 'update_gateway_response_status', 'update_gateway_response_message', 'update_paid_at', 'manually_updated_at', 'update_remarks'
    ];

    protected $timezoneAwareAttributes = [
        'update_paid_at',
        'manually_updated_at',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createValidationRules()
    {
        return [
            'update_actor_id' => ['bail', 'required'],
        ];
    }
}
