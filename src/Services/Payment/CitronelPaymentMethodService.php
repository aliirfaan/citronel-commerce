<?php

namespace aliirfaan\CitronelCommerce\Services\Payment;

use aliirfaan\CitronelCommerce\Services\Helper\CitronelCommerceHelperService;
use aliirfaan\CitronelCommerce\Models\Payment\PaymentMethodConfiguration;

class CitronelPaymentMethodService
{
    /**
     * helperService
     *
     * @var mixed
     */
    protected $helperService;

    /**
     * paymentMethodConfigurationModel
     *
     * @var mixed
     */
    private $paymentMethodConfigurationModel;

    /**
     * Method __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->helperService = new CitronelCommerceHelperService();
        $this->paymentMethodConfigurationModel = new PaymentMethodConfiguration();
    }

    /**
     * Method getDefaultPaymentMethod
     *
     * @return string
     */
    public function getDefaultPaymentMethod()
    {
        return config('citronel-payment.payment_method_default');
    }

    /**
     * Get a list of payment methods
     *
     * @return array
     */
    public function getPaymentMethods()
    {
        $data = $this->helperService->returnFormat();
  
        $result = $this->paymentMethodConfigurationModel->getPaymentMethodConfigurations();
        if (is_null($result)) {
          $data['errors'] = true;
        } else {
            $paymentMethods = array_map(
                function ($item) {
                    if (!is_null($item->logo)) {
                        $reverseProxyUrl = config('citronel.reverse_proxy_url');
                        if (!is_null($reverseProxyUrl)) {
                            $item->logo = $reverseProxyUrl . '/' . $item->logo;
                        } else {
                            $item->logo = asset(config('citronel-payment.payment_method_logo_path') . $item->logo);
                        }
                    }

                    return $item;
                },
                $result->all()
            );
            $data['result'] = $paymentMethods;
        }
  
        if (is_null($data['errors'])) {
          $data['success'] = true;
        }
        
        return $data;
    }
    
    /**
     * Method generatePaymentMethodExtra
     *
     * @return array
     */
    public function generatePaymentMethodExtra()
    {
        $getPaymentMethodsResponse = $this->getPaymentMethods();

        return [
            'payment_methods' => $getPaymentMethodsResponse['result']
        ];
    }

    public function getPaymentMethodConfigurationById($id)
    {
        $data = $this->helperService->returnFormat();
  
        $result = $this->paymentMethodConfigurationModel::where('id', $id)->first();
        if (is_null($result)) {
          $data['errors'] = true;
        }

        if (is_null($data['errors'])) {
          $data['result'] = $result;
          $data['success'] = true;
        }
        
        return $data;
    }
}

