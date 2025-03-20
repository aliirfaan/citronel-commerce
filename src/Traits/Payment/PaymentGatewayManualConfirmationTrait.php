<?php

namespace aliirfaan\CitronelCommerce\Traits\Payment;

trait PaymentGatewayManualConfirmationTrait
{
    /**
     * Method manualPaymentSuccessConfirmationValidationRules
     * Validation rules for manual update of payment
     *
     * @return array
     */
    public function manualPaymentConfirmationValidationRules()
    {
        return [];
    }
    
    /**
     * Method manuallyConfirmPayment
     *
     * @param mixed $payment [explicite description]
     * @param array $extra [explicite description]
     * @param string $channel [explicite description]
     *
     * @return array
     */
    public function manuallyConfirmPayment($payment, $extra = [], $channel = 'manual')
    {
        return $this->helperService->returnFormat();
    }
}
