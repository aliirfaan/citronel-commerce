<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Order\CitronelFulfillmentService;
use aliirfaan\CitronelCommerce\Models\Order\ManualFulfillmentRetry;

class ManualFulfillmentController extends OrderController
{
    public function fulfillItem(Request $request, $order_fulfillment_id,AuditLogService $auditService, CitronelFulfillmentService $fulfillmentService, ManualFulfillmentRetry $manualRetryApiCommand)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $subProcess = $this->errorCatalogueService->getSubProcess('order', 'manual_fulfillment');

        $this->actor = $request->get('actor', null);

        $auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $auditData['al_event_name'] = $subProcess['name'];
        
        $requestArray = $request->json()->all();

        try {
            $subProcessKey = $subProcess['key'];

            // validate
            $validationRules = $manualRetryApiCommand->createValidationRules();
            $validationResponse = $this->apiHelperService->validateRequestFields($requestArray, $validationRules);
            if (!is_null($validationResponse)) {
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, null, $this->validationErrorCatalogue()['code']);
                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = json_encode($requestArray);
                $auditData['al_response'] = json_encode($validationResponse);
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $orderFulfillmentItemResponse = $fulfillmentService->getFulfillmentById($order_fulfillment_id);
            if (!$orderFulfillmentItemResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'manual_fulfillment', 'invalid_item');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key'], $this->recordNotFoundErrorCatalogue()['code']);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $auditData['al_is_success'] = $this->data['success'];
                $auditData['al_event_name'] = $subProcessEvent['name'];
                $auditData['al_code'] = $code['code'];
                $auditData['al_request'] = $order_fulfillment_id;
                $auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $item = $orderFulfillmentItemResponse['result'];

            $manuallyFulfillItemExtra['retry_user_id'] = $requestArray['retry_user_id'];
            $fulfillItemResponse = $fulfillmentService->manuallyFulfillItem($item, $manuallyFulfillItemExtra);
            if (!$fulfillItemResponse['success']) {
                $this->resultResponse = $this->apiHelperService->apiProcessingErrorResponse($this->namespace, [], $fulfillItemResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $this->data = $fulfillItemResponse;
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
