<?php

namespace Cdek;
use Cdek\Contracts\TokenStorageContract;
use Cdek\Exceptions\CdekApiException;
use Cdek\Helpers\DBCoreTokenStorage;
use Cdek\Helpers\DBTokenStorage;
use Cdek\Transport\HttpCoreClient;

class CdekCoreApi
{
    private const TOKEN_PATH = 'cms/wordpress/shop/%s/token';
    private const REINDEX_ORDERS = 'cms/wordpress/full-sync';
    private const SHOP = 'cms/wordpress/shop';
    private string $apiUrl;
    private CdekShippingMethod $deliveryMethod;
    private TokenStorageContract $generalTokenStorage;
    private TokenStorageContract $tokenCoreStorage;

    public function __construct(
        ?int $shippingInstanceId = null,
        ?TokenStorageContract $tokenStorage = null,
        ?TokenStorageContract $tokenCoreStorage = null
    )
    {
        $this->deliveryMethod = Helper::getActualShippingMethod($shippingInstanceId);
        $this->apiUrl = $this->getApiUrl();
        $this->generalTokenStorage = $tokenStorage ?? new DBTokenStorage();
        $this->tokenCoreStorage = $tokenCoreStorage ?? new DBCoreTokenStorage();
    }

    public function fetchShopToken()
    {
        $body = json_decode(
            HttpCoreClient::sendCdekRequest(
                $this->getAuthUrl(),
                'POST',
                $this->generalTokenStorage->getToken()
            ),
            true
        )['body'];

        if ($body === null || isset($body['error_description']) || isset($body['error'])) {
            throw new CdekApiException('[CDEKDelivery] Failed to get shop token',
                                       'cdek_error.shop_token.auth',
                                       $body,
                                       true);
        }

        return $body['token'];
    }

    private function getStoreUuid()
    {
        $response = HttpCoreClient::sendCdekRequest(
            $this->apiUrl . self::SHOP,
            'POST',
            $this->generalTokenStorage->getToken(),
            [
                "url" => [
                    "rest"  => rest_url(),
                    "home"  => home_url(),
                    "admin" => admin_url(),
                ],
            ]
        );

        if(empty($response) || $response['error']){
            throw new CdekApiException('[CDEKDelivery] Failed to get shop uuid',
                                       'cdek_error.uuid.auth',
                                       $response,
                                       true);
        }

        $body = json_decode($response, true)['body'];

        if(empty($body) || $body['error']){
            throw new CdekApiException('[CDEKDelivery] Failed to get shop uuid',
                                       'cdek_error.uuid.auth',
                                       $response,
                                       true);
        }

        return $body['uuid'];
    }

    public function reindexOrders($orders)
    {
        return HttpCoreClient::sendCdekRequest($this->apiUrl . self::REINDEX_ORDERS, 'PUT', $this->tokenCoreStorage->getToken(), $orders);
    }

    public function checkUpdateOrders()
    {
        return HttpCoreClient::sendCdekRequest(
            $this->apiUrl . self::REINDEX_ORDERS,
            'GET',
            $this->tokenCoreStorage->getToken()
        );
    }

    private function getAuthUrl(): string
    {
        return sprintf($this->apiUrl . self::TOKEN_PATH, $this->getStoreUuid());
    }

    private function getApiUrl(): string
    {
        if ($this->deliveryMethod->get_option('test_mode') === 'yes') {
            return $_ENV['CDEK_REST_CORE_API'] ?? Config::TEST_API_CORE_URL;
        }

        return Config::API_CORE_URL;
    }
}
