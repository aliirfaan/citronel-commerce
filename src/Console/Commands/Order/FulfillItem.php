<?php

namespace aliirfaan\CitronelCommerce\Console\Commands\Order;

use Illuminate\Console\Command;
use aliirfaan\CitronelCommerce\Jobs\Order\FulfillItem as FulfillItemJob;
use aliirfaan\CitronelCommerce\Models\Order\OrderFulfillment;

class FulfillItem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:fulfill-item {order_fulfillment_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fullfill order item.';

    /**
     * Execute the console command.
     */
    public function handle(OrderFulfillment $orderFulfillmentApiQuery)
    {
        $jobPolicyId = 'fulfill_item';

        $orderFulfillmentId = $this->argument('order_fulfillment_id');
        $orderFulfillment = OrderFulfillment::where('id', $orderFulfillmentId)->first();

        FulfillItemJob::dispatch($jobPolicyId, $orderFulfillment);
    }
}
