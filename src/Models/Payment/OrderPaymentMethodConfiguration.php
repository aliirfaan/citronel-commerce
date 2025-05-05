<?php

namespace aliirfaan\CitronelCommerce\Models\Payment;

use aliirfaan\CitronelCore\Models\CitronelBaseModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use DB;

class OrderPaymentMethodConfiguration extends CitronelBaseModel
{
    use HasUuids;

    /**
     * table
     *
     * @var string
     */
    protected $table = 'order_payment_method_configurations';

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['created_at', 'updated_at'];

    public function getPaymentMethodConfigurationsByOrderStrategyName($orderStrategyName, $active = 1)
    {
        return DB::table($this->table . ' as order_payment_method_configuration')
        ->leftJoin(
            'payment_method_configurations as payment_method_configuration','payment_method_configuration.id', '=', 'order_payment_method_configuration.payment_method_configuration_id'
        )
        ->leftJoin(
            'payment_methods as payment_method','payment_method.id', '=', 'payment_method_configuration.payment_method_id'
        )
        ->where('order_payment_method_configuration.order_processing_strategy_name', '=', $orderStrategyName)
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
}
