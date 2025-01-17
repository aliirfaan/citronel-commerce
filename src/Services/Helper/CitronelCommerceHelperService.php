<?php

namespace aliirfaan\CitronelCommerce\Services\Helper;

use aliirfaan\CitronelCore\Services\CitronelHelperService;
use aliirfaan\CitronelCore\Traits\CitronelMoneyTrait;
use aliirfaan\CitronelCore\Traits\CitronelCorrelationTokenTrait;

class CitronelCommerceHelperService extends CitronelHelperService
{
    use CitronelMoneyTrait, CitronelCorrelationTokenTrait;
}

