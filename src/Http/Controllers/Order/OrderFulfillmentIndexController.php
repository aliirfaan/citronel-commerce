<?php

namespace Aliirfaan\CitronelCommerce\Http\Controllers\Order;

use aliirfaan\CitronelCommerce\Http\Controllers\Order\OrderController;
use Illuminate\Http\Request;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use Illuminate\Http\Response;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;

class OrderFulfillmentIndexController extends OrderController
{
    public function index(Request $request,string $orderGuid, AuditLogService $auditService, CitronelOrderService $orderService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $this->subProcess = $this->errorCatalogueService->getSubProcess($this->mainProcess['key'], 'order_fulfillments_index');

        $this->actor = $request->get('actor', null);

        $this->auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $this->auditData['al_event_name'] = $this->subProcess['name'];

        try{
            $orderFulfillmentsResponse = $orderService->getOrderFulfillmentsByItemId($orderGuid);

            if(!$orderFulfillmentsResponse['success']){
                $this->data['success'] = true;
                $this->data['status_code'] = Response::HTTP_NO_CONTENT;
            }
            else{
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

                $orderFulfillments = $orderFulfillmentsResponse['result']
                    ->paginate($perPage, ['*'], 'page', $pageNumber)
                    ->withQueryString();

                $this->data['success'] = true;
                $this->data['status_code'] = Response::HTTP_OK;
                $this->data['result'] = $orderFulfillments;
            }
            $this->resultResponse = new ApiResponseCollection($this->data);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}