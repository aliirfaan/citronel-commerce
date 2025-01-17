<?php

namespace aliirfaan\CitronelCommerce\Jobs\CurrencyPlatform;

use aliirfaan\CitronelJob\Jobs\CitronelJob;
use aliirfaan\CitronelCommerce\Services\Currency\CitronelCurrencyService;
use aliirfaan\CitronelCommerce\Services\Helper\CitronelCommerceHelperService;

class RefreshCurrencyRate extends CitronelJob
{
    public $currencyService;

    /**
     * helperService
     *
     * @var mixed
     */
    public $helperService;

    /**
     * Create a new job instance.
     */
    public function __construct($jobPolicyId, CitronelCurrencyService $currencyService)
    {
        parent::__construct($jobPolicyId);

        $this->helperService = new CitronelCommerceHelperService();
        $this->currencyService = $currencyService;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        parent::handle();

        $correlationToken = $this->helperService->generateCorrelationToken();
        $refreshExchangeRateRespponse = $this->currencyService->refreshExchangeRate($correlationToken);
        if (!$refreshExchangeRateRespponse['success']) {
            // fail job
            throw new \Exception($refreshExchangeRateRespponse['message']);
        }
    }
}
