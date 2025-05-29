<?php

namespace aliirfaan\CitronelCommerce\Contracts\Payment;

use \Carbon\Carbon;
use Illuminate\Support\Arr;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentService;
use aliirfaan\CitronelCommerce\Services\Helper\CitronelCommerceHelperService;
use aliirfaan\CitronelCommerce\Traits\Payment\PaymentGatewayLogTrait;
use aliirfaan\CitronelCommerce\Traits\Payment\PaymentGatewayCurrencyTrait;
use aliirfaan\CitronelCommerce\Traits\Payment\PaymentGatewayMessageTrait;
use aliirfaan\CitronelCommerce\Traits\Payment\PaymentGatewayManualConfirmationTrait;

abstract class AbstractPaymentGateway
{
    use PaymentGatewayLogTrait, PaymentGatewayCurrencyTrait, PaymentGatewayMessageTrait, PaymentGatewayManualConfirmationTrait;

    /**
     * Payment method object with payment configuiration settings
     *
     * @var Model
     */
    protected $paymentMethod;

    /**
     * Array of custom values used by payment gateway
     * This is used to map custom_values_x columns in db to actual names
     *
     * Example:
     * If custom_value_1 in db is app_id, then customValues['custom_value_1'] = 'app_id':
     * $this->customValues = [
     *      'custom_value_1' => 'app_id',
     * ];
     *
     * @var array
     */
    protected $customValues = [];

    /**
     * Array of fields to validate when processing payment via different callback channels
     * A payment gateway may respond using different channels
     * Validation based on Laravel validation rules
     *
     * Example:
     * If payment gateway has a callback channel called server_callback, with field txn_no as required:
     * $this->callbackValidationRules = [
     *   'server_callback_notify' => [
     *     'txn_no' => 'required'
     *    ],
     * ]
     *
     * @var array
     */
    public $callbackValidationRules = [
        'server_callback' => [],
        'mobile_app' => [],
    ];

    /**
     * namespace for error reporting
     *
     * @var string
     */
    public $namespace = 'payment_gateway';

    /**
     * Array to map payment gateway service responses to our local gateway parameters based on channel.
     * Our payment tables saves responses in generic columns. This mapping is used to map service response to our local columns.
     *
     * @var array
     */
    protected $gatewayInterfaceFieldsMapping = [
        'server_callback' => [
            'gateway_merchant_transaction_no' => null,
            'gateway_transaction_no' => null,
            'gateway_response_code' => null,
            'gateway_response_status' => null,
            'gateway_response_message' => null
        ],
        'mobile_app' => [
            'gateway_merchant_transaction_no' => null,
            'gateway_transaction_no' => null,
            'gateway_response_code' => null,
            'gateway_response_status' => null,
            'gateway_response_message' => null
        ],
    ];

    /**
     * helperService
     *
     * @var mixed
     */
    public $helperService;

    /**
     * Payment method fields that are safe to show to the outside world
     *
     * @var array
     */
    public $publicConfigurations = ['id', 'title', 'description', 'logo', 'client_callback_url', 'server_callback_url', 'allowed_channels'];

    /**
     * mainProcess for error catalogue mapping
     *
     * @var string
     */
    public $mainProcess;

    /**
     * paymentService
     *
     * @var mixed
     */
    protected $paymentService;
    
    /**
     * gatewayMerchantTransactionNoMaxLength
     *
     * @var int
     */
    protected $gatewayMerchantTransactionNoMaxLength = 64;
    
    /**
     * gatewayMerchantTransactionNoPrefix
     *
     * Prefix for gateway merchant transaction number to be able to identity gateway transactions
     *
     * @var string
     */
    protected $gatewayMerchantTransactionNoPrefix = 'MY';

    /**
     * Method __construct
     *
     * @param mixed $paymentMethod
     *
     *
     * @return void
     */
    public function __construct($paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;

        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);

        $this->paymentService = new CitronelPaymentService();
    }

    /**
     * Method isActive
     *
     * @return bool
     */
    public function isActive()
    {
        return (!is_null($this->paymentMethod)) && intval($this->paymentMethod->active);
    }

    /**
     * validateTransactionAmount
     *
     * Validate against minimum and maximum transaction amount
     *
     * @param  float $amount
     * @return array
     */
    public function validateTransactionAmount($amount)
    {
        $data = $this->helperService->returnFormat();
    
        // Get min and max amounts as strings from the DB (no float conversion)
        $minimumAmount = (string) $this->paymentMethod->min_amount;
        $maximumAmount = (string) $this->paymentMethod->max_amount;
    
        // Convert the $amount to a string as well
        $amount = (string) $amount;
    
        // Check if amount is less than the minimum
        if (bccomp($amount, $minimumAmount, 2) < 0) { // 2 decimal precision for comparison
            $data['errors'] = true;
            $formattedAmount = $this->helperService->formatCurrencyAmountWithCode($minimumAmount);
            $data['message'] = __('citronel-commerce::payment/messages.payment_method_min_amount', ['amount' => $formattedAmount]);
        }
        // Check if amount is greater than the maximum
        elseif (bccomp($amount, $maximumAmount, 2) > 0) { // 2 decimal precision for comparison
            $data['errors'] = true;
            $formattedAmount = $this->helperService->formatCurrencyAmountWithCode($maximumAmount);
            $data['message'] = __('citronel-commerce::payment/messages.payment_method_max_amount', ['amount' => $formattedAmount]);
        } else {
            $data['success'] = true;
        }
    
        return $data;
    }
    

    /**
     * Get configurations
     *
     * @param bool $public
     *
     * @return array
     */
    public function getConfigurations($public = true)
    {
        $configurations = $this->paymentMethod->toArray();
        if ($public) {
            $configurations = Arr::only(
                $configurations,
                $this->publicConfigurations
            );
        }

        return $configurations;
    }

    /**
     * getMappedConfigurations
     *
     * @param bool $public
     *
     * Replace custom_values with gateway mappings from property $customValues
     * Example: custom_value_1 => api_key
     * Generate full url if custom_value is a url
     * Generate asset url if custom_value is an asset
     * If behind a reverse proxy, use reverse proxy url
     *
     * @return array
     */
    public function getMappedConfigurations($public = true)
    {
        $configurations = $this->getConfigurations($public);
        $mappedConfigurations = [];
        $reverseProxyUrl = config('citronel.reverse_proxy_url');
        foreach ($configurations as $key => $value) {
            $configurationKey = $key;
            if (\array_key_exists($key, $this->customValues)) {
                $configurationKey = $this->customValues[$key];
            }
            $mappedConfigurations[$configurationKey] = $value;

            if (is_null($value)) {
                continue;
            }

            // columns to convert to urls
            if (in_array($configurationKey, ['client_callback_url', 'server_callback_url'])) {
                if (!is_null($reverseProxyUrl)) {
                    $mappedConfigurations[$configurationKey] = $reverseProxyUrl . '/' . $value;
                } else {
                    $mappedConfigurations[$configurationKey] = url($value);
                }
            }
            // columns to convert to assets
            elseif (in_array($configurationKey, ['logo'])) {
                if (!is_null($reverseProxyUrl)) {
                    $mappedConfigurations[$configurationKey] = $reverseProxyUrl . '/' . $value;
                } else {
                    $mappedConfigurations[$configurationKey] = asset(config('citronel-payment.payment_method_logo_path') . $value);
                }
            }
        }

        return $mappedConfigurations;
    }

    /**
     * mapCallbackFields
     *
     * Use $this->gatewayInterfaceFieldsMapping to map callback response fields
     * Supports 2 level deep array
     *
     * @param  mixed $callbackFieldsArr
     * @return array
     */
    public function mapCallbackFields($callbackFieldsArr, $channel = 'server_callback')
    {
        $mappedCallbackFields = [];
        foreach ($this->gatewayInterfaceFieldsMapping[$channel] as $key => $value) {
            $mappedCallbackValue = null;
            $explodedValue = explode('.', $value);
            if (count($explodedValue) == 2) {
                if (\array_key_exists($explodedValue[0], $callbackFieldsArr) && \array_key_exists($explodedValue[1], $callbackFieldsArr[$explodedValue[0]])) {
                    $mappedCallbackValue = $callbackFieldsArr[$explodedValue[0]][$explodedValue[1]];
                }
            } else {
                if (\array_key_exists($explodedValue[0], $callbackFieldsArr)) {
                    $mappedCallbackValue = $callbackFieldsArr[$explodedValue[0]];
                }
            }

            $mappedCallbackFields[$key] = $mappedCallbackValue;
        }

        return $mappedCallbackFields;
    }

    /**
     * validateUpdateTime
     *
     * Check if payment is updated within a reasonable time frame
     * Override this method in payment method if you want to change the time frame
     *
     * @param  mixed payment
     * @return array
     */
    public function validateUpdateTime($payment)
    {
        $data = $this->helperService->returnFormat();

        $allowedTimeLapse = intval(config('citronel-payment.payment_update_time_gap_seconds'));
        if ($payment->created_at < Carbon::now()->subSeconds($allowedTimeLapse)) {
            $data['errors'] = true;
            $data['message'] = __('citronel-commerce::payment/messages.payment_method_update_time_exceeded', [
                'payment_reference' => $payment->gateway_merchant_transaction_no,
            ]);
        } else {
            $data['success'] = true;
        }

        return $data;
    }

    /**
     * Method callbackFieldsValidationRule
     *
     * @param $channel $channel [explicite description]
     *
     * @return array
     */
    public function callbackFieldsValidationRules($channel = 'web_callback')
    {
        $validationRules = [];
        if (!is_null($channel) && \array_key_exists($channel, $this->callbackValidationRules)) {
            $validationRules = $this->callbackValidationRules[$channel];
        }
        
        return $validationRules;
    }

    /**
     * Any specific processing for the payment method
     *
     * @param array $requestArray
     * @param array $extra
     * @param string $channel
     *
     * @return array
     */
    abstract public function processPayment($requestArray, $extra = [], $channel = 'server_callback');

    /**
     * Create payment gateway order, some payment gateways require us to first register an order
     *
     * @param $payment $payment [explicite description]
     * @param $extra $extra [explicite description]
     *
     * @return array
     */
    public function registerGatewayOrder($payment, $extra = null)
    {
        $data = $this->helperService->returnFormat();
        $data['success'] = true;

        // data to update payment
        $data['result']['payment_data'] = null;
        $data['result']['leg_data'] = [];

        return $data;
    }

    /**
     * Method retrieveOrder
     *
     * Get payment from payment gateway
     * Not all payment gateway support this feature
     *
     * @param mixed $payment [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function retrieveGatewayOrder($payment, $extra = null)
    {
        return $this->helperService->returnFormat();
    }

    /**
     * generateGatewayMerchantTransactionNo
     *
     * @param  mixed $identifier
     * @param  mixed $prefix
     * @param  mixed $scopeIdentifier - add scope to order number. For example you can add product or service name/id
     * @return string
     */
    public function generateGatewayMerchantTransactionNo($identifier, $prefix = null, $scopeIdentifier = null)
    {
        if (is_null($prefix)) {
            $prefix = config('citronel-payment.transaction_number_prefix');
        }

        $prefix = $prefix . $this->gatewayMerchantTransactionNoPrefix .$scopeIdentifier;

        $suffix = random_int(1000, 9999) . date('d');
        $transactionNumber = $prefix . $identifier . $suffix;

        if (\strlen($transactionNumber) > $this->gatewayMerchantTransactionNoMaxLength) {
            $transactionNumber = (string) Str::uuid();
            $transactionNumber = \str_replace('-', '', $transactionNumber);
        }

        return $transactionNumber;
    }

    /**
     * Method verifyGatewayOrder
     *
     * Get payment from payment gateway
     * If payment is already processed at gateway, return error
     * If we could not get the payment, return success
     * For payment gateway that do not support this featute, return success
     *
     * @param mixed $payment [explicite description]
     * @param array $extra [explicite description]
     *
     * @return array
     */
    public function verifyGatewayOrder($payment, $extra = [], $channel = 'retrieve_order')
    {
        return $this->helperService->returnFormat();
    }

    /**
     * Method validatePaymentChannel
     *
     * @param $paymentChannel $paymentChannel [explicite description]
     *
     * @return array
     */
    public function validatePaymentChannel($paymentChannel)
    {
        $data = $this->helperService->returnFormat();

        $configurations = $this->getConfigurations();
        $allowedPaymentChannels = array_map('trim', explode(',', $configurations['allowed_channels']));
        if (\in_array($paymentChannel, $allowedPaymentChannels)) {
            $data['success'] = true;
            $data['message'] = __('citronel-commerce::payment/messages.valid_payment_channel');
        } else {
            $data['message'] = __('citronel-commerce::payment/messages.invalid_payment_channel');
        }

        return $data;
    }
    
    /**
     * Method createPaymentValidationRules
     * validation rules before creating payment as some payment gateways require additional payment data like gateway_payment_mode
     *
     * @param array $requestArray [explicite description]
     *
     * @return array
     */
    public function createPaymentValidationRules($requestArray = [])
    {
        $rules = [];
        $attributes = [];

        return  [
            'rules' => $rules,
            'attributes' => $attributes
        ];
    }
}
