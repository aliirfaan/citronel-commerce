<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Payment;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentService;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;

class ActorPaymentController extends PaymentController
{
    public function actorPaymentsWithOrderItems(Request $request, string $actor_id, AuditLogService $auditService, CitronelPaymentService $paymentService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);
        
        $this->subProcess = $this->errorCatalogueService->getSubProcess('payment', 'get_actor_payments_with_order_items');

        $gatewayMerchantTransactionNo = null;
        $orderNumber = null;

        if ($request->has('gateway_merchant_transaction_no')) {
            $gatewayMerchantTransactionNo = $request->input('gateway_merchant_transaction_no');
        }
        if ($request->has('order_number')) {
            $orderNumber = $request->input('order_number');
        }

        $this->actor = $request->get('actor', null);

        $this->auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $this->auditData['al_event_name'] = $this->subProcess['name'];

        try{
            // authorize
            Gate::forUser($this->actor)->authorize('matchActorToken', $actor_id);

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

            $getActorPaymentsWithOrderItems = $paymentService->getActorPaymentsWithOrderItems($actor_id, $gatewayMerchantTransactionNo, $orderNumber)
                ->paginate($perPage, ['*'], 'page', $pageNumber)
                ->withQueryString();
            if ($getActorPaymentsWithOrderItems->count() === 0) {
                $this->data['status_code'] = Response::HTTP_NO_CONTENT;
                $this->resultResponse = new ApiResponseCollection($this->data);

                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $this->data['success'] = true;
            $this->data['result'] = $getActorPaymentsWithOrderItems;
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
