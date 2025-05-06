<?php

namespace aliirfaan\CitronelCommerce\Models\Payment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use aliirfaan\CitronelCore\Models\CitronelBaseModel;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentMethod extends CitronelBaseModel
{
    use HasFactory;

    protected $table = 'payment_methods';

    protected $keyType = 'string';

    protected $hidden = ['active', 'sort_order', 'created_at', 'updated_at'];

    /**
     * Get the configuration assoicated with payment method
     */
    public function payment_method_configuration(): HasOne
    {
        return $this->hasOne(PaymentMethodConfiguration::class, 'payment_method_id');
    }
}
