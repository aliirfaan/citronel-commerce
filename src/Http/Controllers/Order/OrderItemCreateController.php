<?php

namespace aliirfaan\CitronelCommerce\Http\Controllers\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use aliirfaan\LaravelSimpleApi\Http\Resources\ApiResponseCollection;
use aliirfaan\LaravelSimpleAuditLog\Services\AuditLogService;
use aliirfaan\LaravelSimpleAuditLog\Events\AuditLogged;
use aliirfaan\CitronelCommerce\Services\Currency\CitronelCurrencyService;
use aliirfaan\CitronelCommerce\Services\Order\CitronelOrderService;
use aliirfaan\CitronelCommerce\Services\Payment\CitronelPaymentMethodService;
use aliirfaan\CitronelCore\Http\Controllers\CitronelController;
use aliirfaan\CitronelCommerce\Models\Order\OrderItem;
use aliirfaan\CitronelCore\Traits\CitronelApiControllerTrait;
use aliirfaan\CitronelCommerce\Services\Product\CitronelProductService;

class OrderItemCreateController extends CitronelController
{
    use CitronelApiControllerTrait;

    public function __construct(OrderItem $modelApiCommand, OrderItem $modelApiQuery)
    {
        parent::__construct();

        $this->namespace = 'order';
        $this->mainProcess = $this->errorCatalogueService->getMainProcess('order');

        $this->modelApiCommand = $modelApiCommand;
        $this->modelApiQuery = $modelApiQuery;
    }

    public function create(Request $request, $order_guid, AuditLogService $auditService, CitronelCurrencyService $currencyService, CitronelOrderService $orderService, CitronelPaymentMethodService $paymentMethodService, CitronelProductService $productService)
    {
        $correlationToken = $this->helperService->getCorrelationTokenFromHeader($request);
        $reponseHeaders = $this->helperService->setCorrelationResponseHeader($correlationToken);

        $this->subProcess = $this->errorCatalogueService->getSubProcess($this->mainProcess['key'], 'item_create');

        $this->actor = $request->get('actor', null);

        $this->auditData = $auditService->generatePreliminaryAuditData($request, $correlationToken, $this->actor);
        $this->auditData['al_event_name'] = $this->subProcess['name'];
        
        $requestArray = $request->json()->all();

        try{
            $subProcessKey = $this->subProcess['key'];

            // validate
            $validationRules = $orderService->orderModel->createValidationRules();
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
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'item_create', 'invalid_order');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->resultResponse = $this->apiHelperService->apiNotFoundErrorResponse($this->namespace, [], null, $this->recordNotFoundErrorCatalogue()['lang'], ['code' => $code['code']]);

                $this->auditData['al_is_success'] = $this->data['success'];
                $this->auditData['al_event_name'] = $subProcessEvent['name'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $order_guid;
                $this->auditData['al_message'] = $code['status'];
                AuditLogged::dispatch($this->auditData);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }
            $order = $getOrderResponse['result'];

            // check order expiry
            $checkOrderExpiryResponse = $orderService->checkOrderExpiry($order);
            if (!$checkOrderExpiryResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'item_create', 'expired_order');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->auditData['al_event_name'] = $subProcessEvent['name'];
                $this->auditData['al_is_success'] = $checkOrderExpiryResponse['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_request'] = $order->id;
                AuditLogged::dispatch($this->auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $checkOrderExpiryResponse['message'], ['code' => $code['code']]);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            // validate order items
            $orderItems = $requestArray['order_items'];
            $validationRulesCustomMessages = $this->modelApiCommand->createValidationRulesMessages();
            $validationRules = $this->modelApiCommand->createValidationRules();
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
                    $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'item_create', 'invalid_product');
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
                    $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'item_create', 'invalid_pre_process');
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

            // get current currency rate
            $currencyRate = null;
            if (!$order->lockCurrency && ($order->order_currency_code !== $currencyService->getBaseCurrencyCode())) {
                $getCurrencyRateResponse = $currencyService->getLatestCurrencyRate();
                if (is_null($getCurrencyRateResponse)) {
                    $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'invalid_currency');
                    $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                    $this->auditData['al_event_name'] = $subProcessEvent['name'];
                    $this->auditData['al_is_success'] = $getCurrencyRateResponse['success'];
                    $this->auditData['al_code'] = $code['code'];
                    $this->auditData['al_request'] = $order->order_currency_code;
                    AuditLogged::dispatch($this->auditData);

                    $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], null, $this->validationErrorCatalogue()['lang'], ['code' => $code['code']]);
                
                    return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
                }
                $currencyRate = $getCurrencyRateResponse;
            }

            $createOrderItemExtra = [
                'correlation_token' => $correlationToken,
                'product_temp_array' => $productTempArray,
                'currency_rate' => $currencyRate,
            ];

            $createOrderItemResponse = $orderService->createOrderItem($order, $orderItems, $createOrderItemExtra);
            if (!$createOrderItemResponse['success']) {
                $subProcessEvent = $this->errorCatalogueService->getSubProcessEvent('order', 'create', 'create_failure');
                $code = $this->errorCatalogueService->generateCodeFromCatalogue($this->mainProcess['key'], $subProcessKey, $subProcessEvent['key']);

                $this->auditData['al_event_name'] = $subProcessEvent['name'];
                $this->auditData['al_is_success'] = $createOrderItemResponse['success'];
                $this->auditData['al_code'] = $code['code'];
                $this->auditData['al_message'] = $createOrderItemResponse['message'];
                AuditLogged::dispatch($this->auditData);

                $this->resultResponse = $this->apiHelperService->apiValidationErrorResponse($this->namespace, [], $createOrderItemResponse['message']);
            
                return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
            }

            $this->data['extra'] = $paymentMethodService->generatePaymentMethodExtra();
            $currencyExtra = $currencyService->generateCurrencyExtra();
            $this->data['extra'] = array_merge($this->data['extra'], $currencyExtra);

            $this->data['success'] = true;
            $this->data['result'] = $createOrderItemResponse['result'];
            $this->data['status_code'] = Response::HTTP_OK;
            $this->resultResponse = new ApiResponseCollection($this->data);

        } catch (\Exception $e) {
            $this->resultResponse = $this->handleException($e);
        }

        return $this->sendApiResponse($this->resultResponse, $this->resultResponse->collection['status_code'], $reponseHeaders);
    }
}
