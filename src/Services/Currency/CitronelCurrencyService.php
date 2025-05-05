<?php

namespace aliirfaan\CitronelCommerce\Services\Currency;

use aliirfaan\CitronelCommerce\Models\CurrencyRate\CurrencyRate;
use aliirfaan\CitronelCommerce\Contracts\CurrencyPlatform\CurrencyPlatformInterface;
use aliirfaan\CitronelErrorCatalogue\Services\CitronelErrorCatalogueService;

class CitronelCurrencyService
{
    /**
     * currencyPlatformService
     *
     * @var mixed
     */
    private $currencyPlatformService;
    
    /**
     * currencyModel
     *
     * @var mixed
     */
    private $currencyRateModel;
    
    /**
     * helperService
     *
     * @var mixed
     */
    private $helperService;

    /**
     * mainProcess
     *
     * @var string
     */
    public $mainProcess;

    public $errorCatalogueService;

    public function __construct(?CurrencyPlatformInterface $currencyPlatformService = null)
    {
        $this->errorCatalogueService = new CitronelErrorCatalogueService();

        $this->mainProcess = $this->errorCatalogueService->getMainProcess('order');
        
        $this->currencyRateModel = new CurrencyRate();

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);

        $this->currencyPlatformService = $currencyPlatformService;
    }
    
    /**
     * Fetch rates from external platform and update local currency table
     *
     * @param $correlationToken $correlationToken [explicite description]
     *
     * @return array
     */
    public function refreshExchangeRate($correlationToken = null)
    {
        $data = $this->currencyPlatformService->refreshExchangeRate($correlationToken);
        if ($data['success']) {
            $exchangeRateResult = $data['result'];
            $buyingRate = $this->helperService->formatAmount($exchangeRateResult['buying_rate']);
            $sellingRate = $this->helperService->formatAmount($exchangeRateResult['selling_rate']);
            $saveData = [
                'from_code' => array_key_exists('from_code', $exchangeRateResult) ? $exchangeRateResult['from_code'] : null,
                'to_code' => array_key_exists('to_code', $exchangeRateResult) ? $exchangeRateResult['to_code'] : null,
                'buying_rate' => $buyingRate,
                'selling_rate' => $sellingRate,
                'source_updated_at_local' => array_key_exists('date', $exchangeRateResult) ? $exchangeRateResult['date'] : null,
                'refreshed_at' => now(),
            ];

            $this->currencyRateModel::create($saveData);
        }

        return $data;
    }
    
    /**
     * Get latest currency rate from local currency table
     *
     * @return array
     */
    public function getLatestCurrencyRate($toCode = 'MUR')
    {
        $fromCode = $this->getBaseCurrencyCode();

        return $this->currencyRateModel::select('id', 'from_code', 'to_code', 'buying_rate', 'selling_rate', 'refreshed_at')
        ->where('from_code', $fromCode)
        ->where('to_code', $toCode)
        ->orderByDesc('refreshed_at')
        ->first();
    }
    
    /**
     * Method getBaseCurrencyCode
     *
     * @return string
     */
    public function getBaseCurrencyCode()
    {
        return $this->helperService->getCitronelBaseCurrencyCode();
    }

    public function getDefaultCurrencyCode()
    {
        return $this->helperService->getCitronelDefaultCurrency();
    }
    
    /**
     * Method getSupportedCurrencies
     *
     * @return array
     */
    public function getSupportedCurrencies()
    {
        return config('citronel.currency.supported');
    }

    public function generateCurrencyExtra($extra = [])
    {
        $supportedCurrencies = $this->getSupportedCurrencies();

        if (array_key_exists('order', $extra)) {
            $order = $extra['order'];
            if (intval($order->lock_currency)) {
                $orderSupportedCurrencies[] = $order->order_currency_code;
                $supportedCurrencies = array_filter($supportedCurrencies, fn($key) => in_array($key, $orderSupportedCurrencies), ARRAY_FILTER_USE_KEY);
            }
        }

        foreach ($supportedCurrencies as $key => $currency) {
            $currencyRate = $this->getLatestCurrencyRate($currency['code']);
            $supportedCurrencies[$key]['exchange_rate'] = $currencyRate;
        }

        return [
            'currency' => [
                'supported' => $supportedCurrencies,
            ]
        ];
    }
    
    /**
     * Method convertAmount
     *
     * @param mixed $amount [explicite description]
     * @param string $toCode [explicite description]
     * @param mixed $currencyRate [explicite description]
     * @param int $decimals [explicite description]
     *
     * @return mixed
     */
    public function convertAmount($amount, $toCode, $currencyRate, $decimals = null)
    {
        $decimals = $decimals ?? config('citronel.decimals');
        
        $baseCurrencyCode = $this->getBaseCurrencyCode();
        $amount = (string) $amount;
    
        if ($toCode !== $baseCurrencyCode) {
            $rate = (string) $currencyRate->selling_rate;
    
            // Multiply with desired precision
            $amount = bcmul($amount, $rate, $decimals);
        }
    
        return $amount; // precise string
    }

    public function formatCurrencyAmount($amount, $currencyCode)
    {
        $supportedCurrency = $this->getSupportedCurrencies();
        $currency = $supportedCurrency[$currencyCode];

        $formatted = $this->helperService->formatAmount($amount);
        $formattedWithSymbol = $currency['symbol'] . ' ' . $amount;
        $formattedWithCode = $amount . ' ' . $currency['code'];

        return [
            'raw' =>  $amount,
            'formatted' =>  $formatted,
            'formatted_with_symbol' =>  $formattedWithSymbol,
            'formatted_with_code' =>  $formattedWithCode
        ];
    }
    
    /**
     * Method getCurrencyRateById
     *
     * @param mixed $id [explicite description]
     *
     * @return mixed
     */
    public function getCurrencyRateById($id)
    {
        return $this->currencyRateModel::select('id', 'from_code', 'to_code', 'buying_rate', 'selling_rate', 'refreshed_at')
        ->where('id', $id)
        ->first();
    }
}
