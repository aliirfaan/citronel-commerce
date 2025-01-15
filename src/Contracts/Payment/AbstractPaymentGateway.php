<?php

namespace aliirfaan\CitronelCommerce\Contracts\Payment;

use aliirfaan\CitronelCore\Services\CitronelHelperService;
use aliirfaan\CitronelCommerce\Contracts\Traits\Payment\PaymentGatewayLogTrait;
use aliirfaan\CitronelCommerce\Contracts\Traits\Payment\PaymentGatewayCurrencyTrait;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentService;

abstract class AbstractPaymentGateway
{
    use PaymentGatewayLogTrait, PaymentGatewayCurrencyTrait;

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
     * Method __construct
     *
     * @param mixed $paymentMethodConfiguration
     *
     *
     * @return void
     */
    public function __construct($paymentMethodConfiguration)
    {
        $this->paymentMethod = $paymentMethodConfiguration;
        $this->helperService = new CitronelHelperService();
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
}
