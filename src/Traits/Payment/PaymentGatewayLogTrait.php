<?php

namespace aliirfaan\CitronelCommerce\Contracts\Traits\Payment;

use Illuminate\Support\Facades\Log;

trait PaymentGatewayLogTrait
{
    /**
     * log channel as defined in /config/logging.php
     *
     * @var string
     */
    protected $logChannel;

    /**
     * logCallback
     *
     * @param  array $logData
     * @param  string $logChannel log channel as defined in /config/logging.php
     * @param  string $message
     * @return void
     *
     */
    public function logCallback($logData, $logChannel = null, $message = 'callback')
    {
        if (is_null($logChannel)) {
            $logChannel = $this->logChannel;
        }

        if ($this->isActive() && intval($this->paymentMethod->debug) == 1) {
            Log::channel($logChannel)->info($message, $logData);
        }
    }
}
