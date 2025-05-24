<?php

namespace aliirfaan\CitronelCommerce\Jobs\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use aliirfaan\CitronelJob\Jobs\CitronelJob;
use aliirfaan\CitronelCommerce\Services\Order\CitronelFulfillmentService;
use aliirfaan\CitronelCommerce\Exceptions\Order\ItemFulfillmentException;

class FulfillItem extends CitronelJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public $fulfillmentService;

    public $item;

    public $fulfillItemExtra = [];

    /**
     * Indicates if the job is being processed the first time. For async fulfillment, we use this so that auto_retry_count is not incremented on the first attempt.
     *
     * @var int
     */
    public bool $isFirstAttempt;

    /**
     * Create a new job instance.
     */
    public function __construct($jobPolicyId, $item, $fulfillItemExtra, $isFirstAttempt = false)
    {
        parent::__construct($jobPolicyId);

        $this->item = $item;
        $this->fulfillItemExtra = $fulfillItemExtra;
        $this->fulfillmentService = new CitronelFulfillmentService();

        // job should be tried based on product max retry count
        $fulfillmentItemMaxRetryCount = intval($item->order_item->product->max_auto_retry);
        if ($fulfillmentItemMaxRetryCount !== 0) {
            $this->tries = $fulfillmentItemMaxRetryCount;
        }

        $this->isFirstAttempt = $isFirstAttempt;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        parent::handle();

        $itemFulfillmentExtra = [];
        if (!$this->isFirstAttempt) {
            // this is a retry
            $itemFulfillmentExtra = [
                'retry_count' => $this->attempts(),
                'is_last_retry' => $this->isLastAttempt
            ];
        }

        $this->fulfillItemExtra = array_merge($this->fulfillItemExtra, $itemFulfillmentExtra);

        $itemFulfillmentResponse = $this->fulfillmentService->fulfillItem($this->item, $this->fulfillItemExtra);
        if (!$itemFulfillmentResponse['success']) {
            // fail job
            $itemFulfillmentResponseMessagesString = implode(' ', array_map('trim', $itemFulfillmentResponse['message']));

            throw new ItemFulfillmentException($itemFulfillmentResponseMessagesString);
        }
    }
}
