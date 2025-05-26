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
    public function successPaymentMessage($payment, $extra = [])
    {
        $amount = array_key_exists('amount', $extra) ? $extra['amount'] : null;
        $replace = [
            'amount' => $amount,
            'payment_reference' => $payment->gateway_merchant_transaction_no
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
    public function cancelPaymentMessage($payment, $extra = [])
    {
        $amount = array_key_exists('amount', $extra) ? $extra['amount'] : null;
        $replace = [
            'amount' => $amount,
            'payment_reference' => $payment->gateway_merchant_transaction_no
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
    public function failedPaymentMessage($payment, $extra = [])
    {
        $cause = $cause ?? '';
        if (\array_key_exists('gateway_response_message', $extra) && !is_null($extra['gateway_response_message'])) {
            $cause = ' Cause: '.$extra['gateway_response_message'];
        }

        return __('citronel-commerce::payment/messages.payment_process_failed', [
            'cause' => $cause,
            'payment_reference' => $payment->gateway_merchant_transaction_no
        ]);
    }

    /**
     * Method expiredPaymentMessage
     *
     * @param $replacementVars $replacementVars [explicite description]
     *
     * @return string
     */
    public function expiredPaymentMessage($payment, $replacementVars = null)
    {
        return __('citronel-commerce::payment/messages.payment_process_expired', [
            'payment_reference' => $payment->gateway_merchant_transaction_no
        ]);
    }

    public function waitingPaymentMessage($payment, $replacementVars = null)
    {
        return __('citronel-commerce::payment/messages.waiting_payment');
    }
}
