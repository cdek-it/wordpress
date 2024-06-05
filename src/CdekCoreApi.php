<?php

namespace Cdek;
use Cdek\Contracts\TokenStorageContract;
use Cdek\Exceptions\CdekApiException;
use Cdek\Helpers\DBCoreTokenStorage;
use Cdek\Helpers\DBTokenStorage;
use Cdek\Transport\HttpCoreClient;
use WpOrg\Requests\Exception;

class CdekCoreApi
{
    private const TOKEN_PATH = 'api/cms/wordpress/shop/%s/token';
    private const SHOP = 'cms/wordpress/shops';
    private const TASKS = 'cms/wordpress/tasks';
    private string $apiUrl;
    private TokenStorageContract $generalTokenStorage;
    private TokenStorageContract $tokenCoreStorage;
    private HttpCoreClient $coreClient;

    public function __construct(
        ?TokenStorageContract $tokenStorage = null,
        ?TokenStorageContract $tokenCoreStorage = null
    )
    {
        $this->apiUrl = $this->getApiUrl();
        $this->coreClient = new HttpCoreClient();
        $this->generalTokenStorage = $tokenStorage ?? new DBTokenStorage();
        $this->tokenCoreStorage = $tokenCoreStorage ?? new DBCoreTokenStorage();
    }

    public function fetchShopToken()
    {
        $response = $this->coreClient->sendCdekRequest(
            $this->apiUrl . self::SHOP,
            'POST',
            $this->generalTokenStorage->getToken(),
            [
                'name' => get_bloginfo('name'),
                'url' => [
                    'rest'  => rest_url(),
                    'home'  => home_url(),
                    'admin' => admin_url(),
                ],
            ],
        );

        if(empty($response['success'])){
            throw new CdekApiException('[CDEKDelivery] Failed to get shop uuid',
                                       'cdek_error.uuid.auth',
                                       $response,
                                       true);
        }

        $body = json_decode($response['body'], true);

        if(empty($body) || empty($body['id']) || $body['error']){
            throw new CdekApiException('[CDEKDelivery] Failed to get shop uuid',
                                       'cdek_error.uuid.auth',
                                       $response,
                                       true);
        }

        $body = json_decode(
            $this->coreClient->sendCdekRequest(
                sprintf($this->apiUrl . self::TOKEN_PATH, $body['id']),
                'POST',
                $this->generalTokenStorage->getToken(),
            ),
            true,
        )['body'];

        if ($body === null || isset($body['error_description']) || isset($body['error'])) {
            throw new CdekApiException('[CDEKDelivery] Failed to get shop token',
                                       'cdek_error.shop_token.auth',
                                       $body,
                                       true);
        }

        return $body['token'];
    }

    public function taskManager($data = null)
    {
        return $this->coreClient->sendCdekRequest($this->getShopApiUrl() . '/' .  self::TASKS, 'GET',
                                               $this->tokenCoreStorage->getToken(), $data);
    }

    public function taskInfo($taskId, $data = null)
    {
        return $this->coreClient->sendCdekRequest($this->getShopApiUrl() . '/' .  self::TASKS . '/' . $taskId, 'GET',
                                                  $this->tokenCoreStorage->getToken(), $data);
    }

    public function sendTaskData($taskId, $data)
    {
        return $this->coreClient->sendCdekRequest($this->getShopApiUrl() . '/' .  self::TASKS . '/' . $taskId, 'PUT',
                                               $this->tokenCoreStorage->getToken(), $data);
    }

    public function addHeaders(array $addHeaders): void
    {
        $this->coreClient->addHeaders($addHeaders);
    }

    private function getShopApiUrl()
    {
        return $this->apiUrl . $this->tokenCoreStorage->getPath();
    }

    private function getApiUrl(): string
    {
        return Config::API_CORE_URL;
    }
}
