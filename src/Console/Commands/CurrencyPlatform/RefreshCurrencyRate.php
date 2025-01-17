<?php

namespace aliirfaan\CitronelCommerce\Console\Commands\CurrencyPlatform;

use Illuminate\Console\Command;
use aliirfaan\CitronelCommerce\Jobs\CurrencyPlatform\RefreshCurrencyRate as RefreshCurrencyRateJob;

class RefreshCurrencyRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency-platform:refresh-currency-rate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get latest currency rate from platform and update local currency table.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $jobPolicyId = 'currency_platform_refresh_currency_rate';

        RefreshCurrencyRateJob::dispatch($jobPolicyId);
    }
}
