<?php

namespace aliirfaan\CitronelCommerce\Models\Payment;

use aliirfaan\CitronelCore\Models\CitronelBaseModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use DB;

class PaymentMethodConfiguration extends CitronelBaseModel
{
    use HasUuids;

    /**
     * table
     *
     * @var string
     */
    protected $table = 'payment_method_configurations';

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['created_at', 'updated_at'];

    /**
     * Get the payment method that owns the configuration.
     */
    public function payment_method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function getPaymentMethodConfigurations($active = 1)
    {
        return DB::table($this->table . ' as payment_method_configuration')
        ->leftJoin(
            'payment_methods as payment_method','payment_method.id', '=', 'payment_method_configuration.payment_method_id'
        )
        ->where('payment_method.active', '=', $active)
        ->select(
            'payment_method.id as payment_method_id',
            'payment_method.title',
            'payment_method.description',
            'payment_method.logo',
            'payment_method_configuration.id as payment_method_configuration_id'
            )
        ->get();
    }

    public function getPaymentMethodConfigurationById($paymentMethodConfigurationId, $active = 1)
    {
        return DB::table($this->table . ' as payment_method_configuration')
        ->leftJoin(
            'payment_methods as payment_method','payment_method.id', '=', 'payment_method_configuration.payment_method_id'
        )
        ->where('payment_method_configuration.id', '=', $paymentMethodConfigurationId)
        ->where('payment_method.active', '=', $active)
        ->select(
            'payment_method.id as payment_method_id',
            'payment_method.title',
            'payment_method.description',
            'payment_method.logo',
            'payment_method.active',
            'payment_method_configuration.id',
            'payment_method_configuration.payment_class',
            'payment_method_configuration.min_amount',
            'payment_method_configuration.max_amount',
            'payment_method_configuration.client_callback_url',
            'payment_method_configuration.server_callback_url',
            'payment_method_configuration.debug',
            'payment_method_configuration.debugReplaceKeys',
            'payment_method_configuration.allowed_channels',
            'payment_method_configuration.custom_value_1',
            'payment_method_configuration.custom_value_2',
            'payment_method_configuration.custom_value_3',
            'payment_method_configuration.custom_value_4',
            'payment_method_configuration.custom_value_5',
            'payment_method_configuration.custom_value_6',
            'payment_method_configuration.custom_value_7',
            'payment_method_configuration.custom_value_8',
            'payment_method_configuration.custom_value_9',
            'payment_method_configuration.custom_value_10',
            )
        ->first();
    }
}
