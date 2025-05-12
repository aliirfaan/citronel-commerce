<?php

namespace aliirfaan\CitronelCommerce\Jobs\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use aliirfaan\CitronelJob\Jobs\CitronelJob;
use aliirfaan\CitronelCommerce\Services\Order\CitronelFulfillmentService;
use aliirfaan\CitronelCommerce\Exceptions\Order\ItemFulfillmentException;

class AutoRetryFulfillItem extends CitronelJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public $fulfillmentService;

    public $item;

    public $fulfillItemExtra = [];

    /**
     * Create a new job instance.
     */
    public function __construct($jobPolicyId, $item, $fulfillItemExtra)
    {
        parent::__construct($jobPolicyId);

        $this->item = $item;
        $this->fulfillItemExtra = $fulfillItemExtra;
        $this->fulfillmentService = new CitronelFulfillmentService();

        // job should be tried based on product max retry count
        $fulfillmentItemMaxRetryCount = intval($item->order_item->product->max_retry_count);
        if ($fulfillmentItemMaxRetryCount !== 0) {
            $this->tries = $fulfillmentItemMaxRetryCount;
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        parent::handle();

        $itemFulfillmentExtra = [
            'retry_count' => $this->attempts(),
            'is_last_retry' => $this->isLastAttempt
        ];

        $this->fulfillItemExtra = array_merge($this->fulfillItemExtra, $itemFulfillmentExtra);

        $itemFulfillmentResponse = $this->fulfillmentService->autoRetryFulfillItem($this->item, $this->fulfillItemExtra);
        if (!$itemFulfillmentResponse['success']) {
            // fail job
            throw new ItemFulfillmentException($itemFulfillmentResponse['message']);
        }
    }
}
