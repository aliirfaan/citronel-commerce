<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Refund;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;
use aliirfaan\CitronelCommerce\Services\Refund\CitronelRefundService;

class InitiateOrderRefundController extends RefundController
{
    public function initiateOrderRefund(Request $request, $order_guid, AuditLogService $auditService, CitronelOrderService $orderService, CitronelRefundService $refundService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $this->subProcess = $this->errorCatalogueService->getSubProcess('refund', 'refund_order');

        $this->actor = $request->get('actor', null);

        $this->auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $this->auditData['al_event_name'] = $this->subProcess['name'];
        
        $requestArray = $request->json()->all();

        try {
            $subProcessKey = $this->subProcess['key'];

            // validate
            $isFullOrderRefundAllowed = $refundService->isFullOrderRefundAllowed();
            $initiateRefundValidationRulesExtra = [
                'is_full_order_refund_allowed' => $isFullOrderRefundAllowed,
            ];

            $validationRules = $this->modelApiQuery->initiateRefundValidationRules($initiateRefundValidationRulesExtra);
            $validationResponse = $this->apiHelperService->validateRequestFields($requestArray, $validationRules);
            if (!is_null($validationResponse)) {
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, null, $this->validationErrorCatalogue()['code']);
                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);

                $this->auditData['al_is_success'] = $this->data['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = json_encode($requestArray);
                $this->auditData['al_response'] = json_encode($validationResponse);
                AuditLogged::dispatch($this->auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $getOrderResponse = $orderService->getOrderByGuid($order_guid);
            if (!$getOrderResponse['success']) {
                $subProcessErrorKey = $this->subProcess['events']['invalid_order']['key'];
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $this->auditData['al_is_success'] = $this->data['success'];
                $this->auditData['al_event_name'] = $this->subProcess['events']['invalid_order']['name'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $order_guid;
                $this->auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($this->auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $order = $getOrderResponse['result'];

            $orderFulfillmentIds = array_key_exists('order_fulfillments', $requestArray) ? $requestArray['order_fulfillments'] : [];
            $initiateOrderRefundExtra = [
                'return_actor_id' => array_key_exists('return_actor_id', $requestArray) ? $requestArray['return_actor_id'] : null,
                'ticket_number' => array_key_exists('ticket_number', $requestArray) ? $requestArray['ticket_number'] : null,
                'reason' => array_key_exists('reason', $requestArray) ? $requestArray['reason'] : null,
            ];

            $initiateOrderRefundResponse = $refundService->initiateOrderRefund($order, $orderFulfillmentIds, $initiateOrderRefundExtra);
            if (!$initiateOrderRefundResponse['success']) {
                $subProcessErrorKey = $this->subProcess['events']['refund_initiation_failed']['key'];
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $initiateOrderRefundResponse['message']);

                $this->auditData['al_is_success'] = $this->data['success'];
                $this->auditData['al_event_name'] = $this->subProcess['events']['refund_initiation_failed']['name'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = json_encode($requestArray);
                $this->auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($this->auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $this->data = $initiateOrderRefundResponse;
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
