<?php

namespace aliirfaan\CitronelCommerce\Services\Order;

use aliirfaan\CitronelCommerce\Enums\Order\OrderStatus;
use aliirfaan\CitronelCommerce\Enums\Payment\PaymentStatus;

class OrderMediatorService
{
    public $orderStatus;

    public function __construct()
    {
        $this->orderStatus = OrderStatus::class;
    }

    /**
     * set order status based on payment status
     *
     * @param string $paymentStatus
     *
     * @return string
     */
    public function mapOrderStatusFromPaymentStatus($paymentStatus)
    {
        switch ($paymentStatus) {
            case PaymentStatus::PAID->value:
                $mappedOrderStatus = $this->orderStatus::PAID->value;
                break;
            case PaymentStatus::CANCELLED->value:
                $mappedOrderStatus = $this->orderStatus::CANCELLED->value;
                break;
            case PaymentStatus::FAILED->value:
                $mappedOrderStatus = $this->orderStatus::FAILED->value;
                break;
            default:
                $mappedOrderStatus = $this->orderStatus::FAILED->value;
                break;
        }

        return $mappedOrderStatus;
    }
}
