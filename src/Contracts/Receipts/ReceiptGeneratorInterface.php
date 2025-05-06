<?php

namespace aliirfaan\CitronelCommerce\Contracts\Receipts;

interface ReceiptGeneratorInterface
{
    public function generate($order, $channel = null);
}
