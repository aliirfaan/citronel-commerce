<?php

namespace aliirfaan\CitronelCommerce\Services\Receipt;

use aliirfaan\CitronelErrorCatalogue\Traits\ErrorCatalogue;
use aliirfaan\CitronelErrorCatalogue\Services\CitronelErrorCatalogueService;

class CitronelReceiptService
{
    use ErrorCatalogue;

    /**
     * helperService
     *
     * @var mixed
     */
    public $helperService;

    /**
     * mainProcess
     *
     * @var string
     */
    public $mainProcess;

    public $errorCatalogueService;

    /**
     * Method __construct
     *
     * @return void
     */
    public function __construct()
    {
        $helperServiceClass = config('citronel-commerce.helper_service');
        $this->helperService = app($helperServiceClass);

        $this->errorCatalogueService = new CitronelErrorCatalogueService();

        $this->mainProcess = $this->errorCatalogueService->getMainProcess('order');
    }

    public function sendReceipt($order)
    {
    }
}
