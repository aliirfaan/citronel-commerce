<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\ProductCategory;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;

class ProductCategoryProductsController extends ProductCategoryController
{
    public function categoryProducts(Request $request, string $category, AuditLogService $auditService)
    {
        // @todo
    }
}
