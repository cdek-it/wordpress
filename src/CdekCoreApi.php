<?php

namespace Cdek;

use Cdek\Contracts\TokenStorageContract;
use Cdek\Exceptions\CdekApiException;
use Cdek\Exceptions\CdekCoreApiException;
use Cdek\Helpers\DBCoreTokenStorage;
use Cdek\Helpers\DBTokenStorage;
use Cdek\Model\CoreApiHeadersData;
use Cdek\Transport\HttpCoreClient;

class CdekCoreApi
{
    const SUCCESS_STATUS = 200;
    const FINISH_STATUS = 201;
    const HAS_NEXT_INFO_STATUS = 202;
    const UNKNOWN_METHOD = 404;
    const FATAL_ERRORS = [500, 502, 503, 504];
    private const TOKEN_PATH = 'cms/wordpress/shops/%s/token';
    private const SHOP = 'cms/wordpress/shops';
    private const TASKS = 'wordpress/tasks';
    public ?int $status;
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

    /**
     * @return array
     * @throws CdekApiException
     * @throws CdekCoreApiException
     * @throws \JsonException
     */
    public function fetchShopToken(): array
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

    /**
     * @param $data
     *
     * @return array|false|string|\WP_Error
     * @throws CdekApiException
     * @throws CdekCoreApiException
     * @throws \JsonException
     */
    public function taskManager($data = null)
    {
        $response = $this->coreClient->sendCdekRequest($this->getShopApiUrl() . '/' .  self::TASKS, 'GET',
                                                  $this->tokenCoreStorage->getToken(), $data);

        return $this->initData($response);
    }

    /**
     * @param       $taskId
     * @param null  $data
     * @param array $headers
     *
     * @return array|false|string|\WP_Error
     * @throws CdekApiException
     * @throws CdekCoreApiException
     * @throws \JsonException
     */
    public function taskInfo($taskId, $data = null, ?CoreApiHeadersData $headers = null)
    {
        $response = $this->coreClient->sendCdekRequest($this->getShopApiUrl() . '/' .  self::TASKS . '/' . $taskId, 'GET',
                                                  $this->tokenCoreStorage->getToken(), $data, $headers);

        return $this->initData($response);
    }

    /**
     * @param       $taskId
     * @param       $data
     * @param ?CoreApiHeadersData $headers
     *
     * @return array|false|string|\WP_Error
     * @throws CdekApiException
     * @throws CdekCoreApiException
     * @throws \JsonException
     */
    public function sendTaskData($taskId, $data, ?CoreApiHeadersData $headers = null)
    {
        $response = $this->coreClient->sendCdekRequest(
            $this->getShopApiUrl() . '/' .  self::TASKS . '/' . $taskId,
            'PUT',
            $this->tokenCoreStorage->getToken(),
            $data,
            $headers ? $headers->getHeaders() : []
        );

        return $this->initData($response);
    }

    private function getShopApiUrl()
    {
        return $this->tokenCoreStorage->getPath();
    }

    private function initData($response)
    {
        $decodeResponse = json_decode($response['body'], true);

        $this->status = $decodeResponse['status'];

        if(
            !in_array(
                $this->status,
                [self::FINISH_STATUS, self::HAS_NEXT_INFO_STATUS, self::SUCCESS_STATUS],
            )
            &&
            !in_array($this->status, self::FATAL_ERRORS)
        ){
            throw new CdekCoreApiException('[CDEKDelivery] Failed to get core api response',
                                           'cdek_error.core.response',
                                           $response,
                                           true);
        }

        return $decodeResponse;
    }
}
