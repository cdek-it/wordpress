<?php

namespace Cdek;
use Cdek\Contracts\TokenStorageContract;
use Cdek\Exceptions\CdekCoreApiException;
use Cdek\Helpers\DBCoreTokenStorage;
use Cdek\Helpers\DBTokenStorage;
use Cdek\Transport\HttpCoreClient;
use WpOrg\Requests\Exception;

class CdekCoreApi
{
    private const TOKEN_PATH = 'cms/wordpress/shops/%s/token';
    private const SHOP = 'cms/wordpress/shops';
    private const TASKS = 'wordpress/tasks';
    private TokenStorageContract $generalTokenStorage;
    private TokenStorageContract $tokenCoreStorage;
    private HttpCoreClient $coreClient;

    public function __construct(
        ?TokenStorageContract $tokenStorage = null,
        ?TokenStorageContract $tokenCoreStorage = null
    )
    {
        $this->coreClient = new HttpCoreClient();
        $this->generalTokenStorage = $tokenStorage ?? new DBTokenStorage();
        $this->tokenCoreStorage = $tokenCoreStorage ?? new DBCoreTokenStorage();
    }

    public function fetchShopToken()
    {
        $response = $this->coreClient->sendCdekRequest(
            Config::API_CORE_URL . self::SHOP,
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

        if(empty($response['body'])){
            throw new CdekCoreApiException('[CDEKDelivery] Failed to get shop uuid',
                                       'cdek_error.uuid.auth',
                                       $response,
                                       true);
        }

        $body = json_decode($response['body'], true);

        if(empty($body) || empty($body['data']['id'])){
            throw new CdekCoreApiException('[CDEKDelivery] Failed to get shop uuid',
                                       'cdek_error.uuid.auth',
                                       $response,
                                       true);
        }

        $response = $this->coreClient->sendCdekRequest(
            sprintf(Config::API_CORE_URL . self::TOKEN_PATH, $body['data']['id']),
            'POST',
            $this->generalTokenStorage->getToken(),
        );

        $body = json_decode($response['body'],true);

        if ($body === null || !$body['success'] || empty($body['data'])) {
            throw new CdekCoreApiException('[CDEKDelivery] Failed to get shop token',
                                       'cdek_error.shop_token.auth',
                                       $body,
                                       true);
        }

        return ['tokens' => $body['data']];
    }

    public function taskManager($data = null)
    {
        return $this->coreClient->sendCdekRequest($this->getShopApiUrl() . '/' .  self::TASKS, 'GET',
                                               $this->tokenCoreStorage->getToken(), $data);
    }

    public function taskInfo($taskId, $data = null, $headers = [])
    {
        return $this->coreClient->sendCdekRequest($this->getShopApiUrl() . '/' .  self::TASKS . '/' . $taskId, 'GET',
                                                  $this->tokenCoreStorage->getToken(), $data);
    }

    public function sendTaskData($taskId, $data, $headers = [])
    {
        return $this->coreClient->sendCdekRequest(
            $this->getShopApiUrl() . '/' .  self::TASKS . '/' . $taskId,
            'PUT',
            $this->tokenCoreStorage->getToken(),
            $data,
            $headers
        );
    }

    private function getShopApiUrl()
    {
        return $this->tokenCoreStorage->getPath();
    }
}
