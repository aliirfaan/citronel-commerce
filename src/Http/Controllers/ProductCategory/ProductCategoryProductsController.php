<?php

namespace aliirfaan\CitronelCommerce\Controllers\ProductCategoryController;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;

class ProductCategoryController extends ProductCategoryProductsController
{
    public function categoryProducts(Request $request, string $category, AuditLogService $auditService)
    {
        // @todo
    }
}
