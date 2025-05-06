<?php

namespace aliirfaan\CitronelCommerce\Listeners\Order;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use aliirfaan\CitronelCommerce\Services\Order\CitronelFulfillmentService;
use Illuminate\Support\Facades\Notification;
use aliirfaan\CitronelCommerce\Notifications\Order\OrderReceipt;

use aliirfaan\CitronelCommerce\Events\Payment\PaymentProcessed;
use aliirfaan\CitronelCommerce\Enums\Payment\PaymentStatus;
use aliirfaan\CitronelCommerce\Services\Receipt\CitronelReceiptService;

class SendOrderReceipt implements ShouldQueue
{
    public $fulfillmentService;

    public $receiptService;

    /**
     * Create the event listener.
     */
    public function __construct(CitronelFulfillmentService $fulfillmentService)
    {
        $this->fulfillmentService = $fulfillmentService;

        $this->receiptService = new CitronelReceiptService();
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

        $items = $this->fulfillmentService->getFulfillmentsByOrderId($order->order_id);

        $actor = $order->actor;
        $notificationVars = [
            'actor' => $actor,
            'payment' => $payment,
            'items' => $items,
        ];

        $receiptNotificationClass = $this->receiptService->getReceiptNotificationClass($order);
        if (!is_null($receiptNotificationClass)) {
            Notification::send($actor, $receiptNotificationClass($notificationVars));
        } else {
            Notification::send($actor, new OrderReceipt($notificationVars));
        }

        // Mark the notification as sent
        $order->receipt_sent = true;
        $order->save();
    }
}
