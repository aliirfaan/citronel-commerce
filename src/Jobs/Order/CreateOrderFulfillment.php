<?php

namespace aliirfaan\CitronelCommerce\Jobs\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use aliirfaan\CitronelCommerce\Services\Order\CitronelFulfillmentService;
use aliirfaan\CitronelCommerce\Jobs\Order\FulfillItem;
use aliirfaan\CitronelCommerce\Services\Helper\CitronelCommerceHelperService;

/**
 * Create order fulfillment items and then dispatches jobs to fulfill each item.
 */
class CreateOrderFulfillment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order;

    public $fulfillmentService;
    
    /**
     * whether to dispatch job to fulfill items
     *
     * @var bool
     */
    public $shouldFullfillItems = true;

    /**
     * helperService
     *
     * @var mixed
     */
    public $helperService;

    /**
     * Create a new job instance.
     */
    public function __construct($order, $shouldFullfillItems = true)
    {
        $this->order = $order;
        $this->shouldFullfillItems = $shouldFullfillItems;
        $this->fulfillmentService = new CitronelFulfillmentService();
        $this->helperService = new CitronelCommerceHelperService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $createOrderFulfillmentResponse = $this->fulfillmentService->createOrderFulfillment($this->order);
        if (!$createOrderFulfillmentResponse['success']) {
            // fail job
            throw new \Exception($createOrderFulfillmentResponse['message']);
        }

        // dispatch job to full each items
        if ($this->shouldFullfillItems) {
            $getFulfillmentsByOrderIdResponse = $this->fulfillmentService->getFulfillmentsByOrderId($this->order->id);

            $jobPolicyId = 'fulfill_item';
            foreach ($getFulfillmentsByOrderIdResponse as $item) {
                $productInterfaceObj = $this->helperService->makeObject($item->order_item->product->product_class, ['product'=> $item->order_item->product]);
    
                $fulfillmentTypeResponse = $productInterfaceObj->getProductOrderFulfillmentItemType();
                if ($fulfillmentTypeResponse === 'sync') {
                    FulfillItem::dispatchSync($jobPolicyId, $item);
                } else {
                    FulfillItem::dispatch($jobPolicyId, $item);
                }
            }
        }
    }
}
