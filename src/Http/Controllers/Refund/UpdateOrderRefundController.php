<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Refund;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Refund\CitronelRefundService;

class UpdateOrderRefundController extends RefundController
{
    public function updateOrderRefund(Request $request, $payment_refund_id, AuditLogService $auditService, CitronelRefundService $refundService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $this->subProcess = $this->errorCatalogueService->getSubProcess('refund', 'update_refund_order');

        $this->actor = $request->get('actor', null);

        $this->auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $this->auditData['al_event_name'] = $this->subProcess['name'];
        
        $requestArray = $request->json()->all();

        try {
            $subProcessKey = $this->subProcess['key'];

            // validate
            $validationRules = $this->modelApiQuery->updateRefundValidationRules();
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

            $getPaymentRefundByIdResponse = $refundService->getPaymentRefundById($payment_refund_id);
            if (!$getPaymentRefundByIdResponse['success']) {
                $subProcessErrorKey = $this->subProcess['events']['invalid_payment_refund']['key'];
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $this->auditData['al_is_success'] = $this->data['success'];
                $this->auditData['al_event_name'] = $this->subProcess['events']['invalid_payment_refund']['name'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $payment_refund_id;
                $this->auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($this->auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $paymentRefund = $getPaymentRefundByIdResponse['result'];

            $updateOrderRefundExtra = [
                'update_actor_id' => array_key_exists('update_actor_id', $requestArray) ? $requestArray['update_actor_id'] : null,
                'refund_transaction_no' => array_key_exists('refund_transaction_no', $requestArray) ? $requestArray['refund_transaction_no'] : null
            ];

            $updateOrderRefundResponse = $refundService->updateOrderRefund($paymentRefund, $updateOrderRefundExtra);
            if (!$updateOrderRefundResponse['success']) {
                $subProcessErrorKey = $this->subProcess['events']['refund_initiation_failed']['key'];
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessErrorKey);

                $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $updateOrderRefundResponse['message']);

                $this->auditData['al_is_success'] = $this->data['success'];
                $this->auditData['al_event_name'] = $this->subProcess['events']['refund_initiation_failed']['name'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = json_encode($requestArray);
                $this->auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($this->auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $this->data = $updateOrderRefundResponse;
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
