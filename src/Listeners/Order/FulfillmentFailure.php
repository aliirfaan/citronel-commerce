<?php

namespace aliirfaan\CitronelCommerce\Listeners\Order;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use aliirfaan\CitronelCommerce\Events\Order\FulfillmentFailed;
use aliirfaan\CitronelCommerce\Services\Order\CitronelFulfillmentService;
use aliirfaan\CitronelCommerce\Mail\Order\FulfillmentFailure as FulfillmentFailureMail;
use aliirfaan\CitronelCommerce\Mail\Order\CustomerFulfillmentFailure;
use Illuminate\Support\Facades\Mail;
use aliirfaan\CitronelCommerce\Enums\Order\OrderStatus;

class FulfillmentFailure implements ShouldQueue
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
    public function handle(FulfillmentFailed $event): void
    {
        $order = $event->item->order_item->order;

        // Check if the notification has already been sent
        if ($order->fulfillment_fail_notif_sent) {
            return;
        }

        $unprocessedFulfillmentCountForOrder = $this->fulfillmentService->getUnprocessedFulfillmentCountForOrder($event->item->order_id);

        if ($unprocessedFulfillmentCountForOrder > 0) {
            // some items still needs processing
            return;
        }

        $unfulfilledStatus = OrderStatus::UNFULFILLED->value;
        $unfulfilledItems = $this->fulfillmentService->getFulfillmentsByOrderId($event->item->order_id, $unfulfilledStatus);

        $payment = $this->fulfillmentService->getSuccessPaymentForOrder($event->item->order_id);

        $customer = $unfulfilledItems[0]->customer;
        $mailVars = [
            'customer' => $customer,
            'payment' => $payment,
            'items' => $unfulfilledItems,
        ];

        // notification to customer
        if (intval(config('citronel-order.features.fulfillment_failure_customer_notification_enabled'))) {
            Mail::to($customer->email)->send(new CustomerFulfillmentFailure($mailVars));

            // Mark the notification as sent
            $order->fulfillment_fail_notif_sent = true;
            $order->save();
        }

        // notification to support
        if (intval(config('citronel-order.features.fulfillment_failure_support_notification_enabled'))) {
            $supportEmailToAddress = config('citronel-order.fulfillment_failure_support_to_address');
            Mail::to($supportEmailToAddress)->send(new FulfillmentFailureMail($mailVars));
        }
    }
}
