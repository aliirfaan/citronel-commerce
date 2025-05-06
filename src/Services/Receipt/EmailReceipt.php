<?php

namespace aliirfaan\CitronelCommerce\Services\Receipt;

use aliirfaan\CitronelErrorCatalogue\Traits\ErrorCatalogue;
use aliirfaan\CitronelErrorCatalogue\Services\CitronelErrorCatalogueService;
use aliirfaan\CitronelCommerce\Contracts\Receipts\ReceiptGeneratorInterface;

class EmailReceipt implements ReceiptGeneratorInterface
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

        $this->mainProcess = $this->errorCatalogueService->getMainProcess('product');
    }

    public function generate($order, $channel = null)
    {
        return view('emails.receipts.default', ['order' => $order])->render();
    }
}
