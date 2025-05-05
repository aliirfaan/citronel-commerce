<?php

namespace aliirfaan\CitronelCommerce\Listeners\Order;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use aliirfaan\CitronelCommerce\Services\Order\CitronelFulfillmentService;
use Illuminate\Support\Facades\Notification;
use aliirfaan\CitronelCommerce\Notifications\Order\OrderReceipt;

use aliirfaan\CitronelCommerce\Events\Payment\PaymentProcessed;
use aliirfaan\CitronelCommerce\Enums\Payment\PaymentStatus;

class SendOrderReceipt implements ShouldQueue
{
    public $fulfillmentService;

    /**
     * Create the event listener.
     */
    public function __construct(CitronelFulfillmentService $fulfillmentService)
    {
        $this->fulfillmentService = $fulfillmentService;
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentProcessed $event): void
    {
        $order = $event->payment->order;

        // Check if the notification has already been sent
        if ((intval($order->should_send_receipt) == 0) || (intval($order->receipt_sent) == 1)) {
            return;
        }

        // check payment status
        if ($event->payment->status !== PaymentStatus::PAID->value) {
            // payment not successful
            return;
        }

        $payment = $event->payment;

        $actor = $order->actor;
        $notificationVars = [
            'actor' => $actor,
            'payment' => $payment,
            'items' => [], // @todo
        ];

        // notification
        Notification::send($actor, new OrderReceipt($notificationVars));

        // Mark the notification as sent
        $order->receipt_sent = true;
        $order->save();
    }
}
