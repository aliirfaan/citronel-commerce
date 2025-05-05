<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Order;

use aliirfaan\CitronelCommerce\Http\Controllers\Order\OrderController;
use Illuminate\Http\Request;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\CitronelCommerce\Models\Order\OrderItem;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;
use aliirfaan\CitronelCommerce\Services\Product\CitronelProductService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;

class OrderItemsUpdateController extends OrderController
{
    public function update(string $orderGuid, Request $request, AuditLogService $auditService, OrderItem $orderItemApi, CitronelProductService $productService, CitronelOrderService $orderService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $this->subProcess = $this->errorCatalogueService->getSubProcess($this->mainProcess['key'], 'create');

        $this->actor = $request->get('actor', null);

        $this->auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $this->auditData['al_event_name'] = $this->subProcess['name'];
        
        $requestArray = $request->json()->all();

        try{
            $subProcessKey = $this->subProcess['key'];

            $validationRules = $this->modelApiCommand->updateOrderItemsValidationRules();
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

            // validate order items
            $orderItems = $requestArray['order_items'];
            $validationRulesCustomMessages = $orderItemApi->createValidationRulesMessages();
            $validationRules = $orderItemApi->createValidationRules();
            foreach ($orderItems as $anOrderItem) {
                $validationResponse = $this->apiHelperService->validateRequestFields($anOrderItem, $validationRules, $validationRulesCustomMessages);
                if (!is_null($validationResponse)) {
                    $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, null, $this->validationErrorCatalogue()['code']);
                    $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);
    
                    $this->auditData['al_is_success'] = $this->data['success'];
                    $this->auditData['al_code'] = $code['code'];
                    $this->auditData['al_request'] = json_encode($anOrderItem);
                    $this->auditData['al_response'] = json_encode($validationResponse);
                    AuditLogged::dispatch($this->auditData);
                
                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                }
            }

            // validate order items product
            foreach ($orderItems as $anOrderItem) {
                $productId = $anOrderItem['product_id'];
                $getProductResponse = $productService->getProductById($productId);
                if (!$getProductResponse['success']) {
                    $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'invalid_product');
                    $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $this->subProcess['key'], null, $this->recordNotFoundErrorCatalogue()['code']);
    
                    $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                    $this->auditData['al_is_success'] = $this->data['success'];
                    $this->auditData['al_event_name'] = $subProcessEvent['name'];
                    $this->auditData['al_code'] = $code['code'];
                    $this->auditData['al_request'] = json_encode($anOrderItem);
                    $this->auditData['al_message'] = $code['status'];
                    AuditLogged::dispatch($this->auditData);
                
                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                }
                $product = $getProductResponse['result'];

                // load product class
                $productInterfaceObj = $this->helperService->makeObject($product->product_class, ['product'=> $product]);

                // validate order for this specific product
                $validationRules = $productInterfaceObj->orderItemCreateValidationRules($anOrderItem);
                if (!empty($validationRules)) {
                    $validationResponse = $this->apiHelperService->validateRequestFields($anOrderItem, $validationRules['rules'], $validationRules['messages'], $validationRules['attributes']);

                    if (!is_null($validationResponse)) {
                        $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, null, $this->validationErrorCatalogue()['code']);
                        $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, $validationResponse, null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);
        
                        $this->auditData['al_is_success'] = $this->data['success'];
                        $this->auditData['al_code'] = $code['code'];
                        $this->auditData['al_request'] = json_encode($anOrderItem);
                        $this->auditData['al_response'] = json_encode($validationResponse);
                        AuditLogged::dispatch($this->auditData);
                    
                        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                    }
                }

                $productTempArray[$productId] = [
                    'product' => $product,
                    'product_class' => $productInterfaceObj
                ];
            }

            // order create pre process for order items
            $orderItemCreatePreProcessExtra = [
                'correlation_token' => $correlationToken
            ];
            foreach ($orderItems as $anOrderItem) {
                $productId = $anOrderItem['product_id'];
                $productInterfaceObj = $productTempArray[$productId]['product_class'];

                $orderItemCreatePreProcessResponse = $productInterfaceObj->orderItemCreatePreProcess($anOrderItem, $orderItemCreatePreProcessExtra);
                if (!$orderItemCreatePreProcessResponse['success']) {
                    $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'invalid_pre_process');
                    $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                    $this->auditData['al_event_name'] = $subProcessEvent['name'];
                    $this->auditData['al_is_success'] = $orderItemCreatePreProcessResponse['success'];
                    $this->auditData['al_code'] = $code['code'];
                    $this->auditData['al_request'] = json_encode($anOrderItem);
                    $this->auditData['al_message'] = $orderItemCreatePreProcessResponse['message'];
                    AuditLogged::dispatch($this->auditData);

                    $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], $orderItemCreatePreProcessResponse['message']);
                
                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                }
            }

            $orderData = [
                'correlation_token' => $correlationToken
            ];

            // update order items
            $updateOrderItemsResponse = $orderService->updateOrderItems($orderGuid, $orderData, $orderItems);
            if (!$updateOrderItemsResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'create_failure');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->auditData['al_event_name'] = $subProcessEvent['name'];
                $this->auditData['al_is_success'] = $updateOrderItemsResponse['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_message'] = $updateOrderItemsResponse['message'];
                AuditLogged::dispatch($this->auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], $updateOrderItemsResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $this->data['success'] = true;
            $this->data['result']['order'] = $updateOrderItemsResponse['result'];
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}