<?php

namespace aliirfaan\CitronelCommerce\Jobs\Order;

use aliirfaan\CitronelJob\Jobs\CitronelJob;
use aliirfaan\CitronelCommerce\Services\Order\FulfillmentService;
use aliirfaan\CitronelCommerce\Exceptions\Order\ItemFulfillmentException;

class FulfillItem extends CitronelJob
{
    public $fulfillmentService;

    public $item;

    /**
     * Create a new job instance.
     */
    public function __construct($jobPolicyId, $item)
    {
        parent::__construct($jobPolicyId);

        $this->item = $item;
        $this->fulfillmentService = new FulfillmentService();
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

        $itemFulfillmentResponse = $this->fulfillmentService->fulfillItem($this->item, $itemFulfillmentExtra);
        if (!$itemFulfillmentResponse['success']) {
            // fail job
            throw new ItemFulfillmentException($itemFulfillmentResponse['message']);
        }
    }
}
