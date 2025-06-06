<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Refund;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\CitronelCommerce\Services\Refund\CitronelRefundService;

class GetOrderRefundController extends RefundController
{
    public function getOrderRefunds(Request $request, string $orderGuid, AuditLogService $auditService, CitronelRefundService $refundService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $this->subProcess = $this->errorCatalogueService->getSubProcess('refund', 'get_order_refunds');

        $this->actor = $request->get('actor', null);

        $this->auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $this->auditData['al_event_name'] = $this->subProcess['name'];

        try{
            $perPage = config('citronel.per_page');
            if ($request->per_page) {
                $perPage = (int) $request->per_page;
                $maxPerPage = config('citronel.max_per_page');
                if (intval($perPage) > intval($maxPerPage)) {
                    $perPage = $maxPerPage;
                }
            }
    
            $pageNumber = 1;
            if ($request->page) {
                $pageNumber = (int) $request->page;
            }

            $getPaymentRefundsByOrderId = $refundService->getPaymentRefundsByOrderGuid($orderGuid)
                ->paginate($perPage, ['*'], 'page', $pageNumber)
                ->withQueryString();
            if ($getPaymentRefundsByOrderId->count() === 0) {
                $this->data['status_code'] = Response::HTTP_NO_CONTENT;
                $this->resultResponse = new ApiResponseCollection($this->data);

                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $this->data['success'] = true;
            $this->data['result'] = $getPaymentRefundsByOrderId;
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);

        }
        catch(\Exception $e){
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}