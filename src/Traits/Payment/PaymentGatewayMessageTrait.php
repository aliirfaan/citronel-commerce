<?php

namespace aliirfaan\CitronelCommerce\Traits\Payment;

trait PaymentGatewayMessageTrait
{
        /**
     * Method successPaymentMessage
     *
     * @param array $extra [explicite description]
     *
     * @return string
     */
    public function successPaymentMessage($extra = [])
    {
        $amount = array_key_exists('amount', $extra) ? $extra['amount'] : null;
        $paymentReference = array_key_exists('payment_reference', $extra) ? $extra['payment_reference'] : null;
        $replace = [
            'amount' => $amount,
            'payment_reference' => $paymentReference
        ];

        return __('citronel-commerce::payment/messages.payment_process_success', $replace);
    }
    
    /**
     * Method cancelPaymentMessage
     *
     * @param array $extra [explicite description]
     *
     * @return string
     */
    public function cancelPaymentMessage($extra = [])
    {
        $amount = array_key_exists('amount', $extra) ? $extra['amount'] : null;
        $replace = [
            'amount' => $amount
        ];

        return  __('citronel-commerce::payment/messages.payment_process_cancelled', $replace);
    }
    
    /**
     * Method failedPaymentMessage
     *
     * @param array $extra [explicite description]
     *
     * @return string
     */
    public function failedPaymentMessage($extra = [])
    {
        $cause = null;
        if (\array_key_exists('gateway_response_message', $extra) && !is_null($extra['gateway_response_message'])) {
            $cause = 'Cause: '.$extra['gateway_response_message'];
        }
        return __('citronel-commerce::payment/messages.payment_process_failed', ['cause' => $cause]);
    }

    /**
     * Method expiredPaymentMessage
     *
     * @param $replacementVars $replacementVars [explicite description]
     *
     * @return string
     */
    public function expiredPaymentMessage($replacementVars = null)
    {
        return __('citronel-commerce::payment/messages.payment_process_expired');
    }
}
