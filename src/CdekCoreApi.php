<?php

namespace Cdek;

use Cdek\Contracts\TokenStorageContract;
use Cdek\Exceptions\CdekApiException;
use Cdek\Exceptions\CdekScheduledTaskException;
use Cdek\Helpers\DBCoreTokenStorage;
use Cdek\Helpers\DBTokenStorage;
use Cdek\Model\TaskOutputData;
use Cdek\Transport\HttpCoreClient;

class CdekCoreApi
{
    const SUCCESS_STATUS = 200;
    const FINISH_STATUS = 201;
    const HAS_NEXT_INFO_STATUS = 202;
    const EMPTY_ANSWER = 204;
    const UNKNOWN_METHOD = 404;
    const FATAL_ERRORS_FIRST_NUMBER = 5;
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
     * @throws CdekScheduledTaskException
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
                'url'  => [
                    'rest'  => rest_url(),
                    'home'  => home_url(),
                    'admin' => admin_url(),
                ],
            ],
        );

        if (empty($response['body'])) {
            throw new CdekScheduledTaskException('[CDEKDelivery] Register shop failed',
                                                 'cdek_error.register.shop',
                                                 $response,
            );
        }

        $body = json_decode($response['body'], true);

        if (empty($body['data']['id'])) {
            throw new CdekScheduledTaskException('[CDEKDelivery] Failed to get shop uuid',
                                                 'cdek_error.uuid.auth',
                                                 $response,
            );
        }

        $response = $this->coreClient->sendCdekRequest(
            sprintf(Config::API_CORE_URL . self::TOKEN_PATH, $body['data']['id']),
            'POST',
            $this->generalTokenStorage->getToken(),
        );

        $body = json_decode($response['body'], true);

        if ($body === null || !$body['success'] || empty($body['data'])) {
            throw new CdekScheduledTaskException('[CDEKDelivery] Failed to get shop token',
                                                 'cdek_error.shop_token.auth',
                                                 $body,
            );
        }

        return ['tokens' => $body['data']];
    }

    /**
     * @param $data
     *
     * @return array|false|string|\WP_Error
     * @throws CdekApiException
     * @throws CdekScheduledTaskException
     * @throws \JsonException
     */
    public function taskManager($data = null)
    {
        $response = $this->coreClient->sendCdekRequest($this->getShopApiUrl() . '/' . self::TASKS,
                                                       'GET',
                                                       $this->tokenCoreStorage->getToken(),
                                                       $data);

        return $this->initData($response);
    }

    /**
     * @param                             $taskId
     * @param TaskOutputData              $data
     *
     * @return array|false|string|\WP_Error
     * @throws CdekApiException
     * @throws CdekScheduledTaskException
     * @throws \JsonException
     */
    public function taskInfo($taskId, TaskOutputData $data)
    {
        $response = $this->coreClient->sendCdekRequest(
            $this->getShopApiUrl() . '/' . self::TASKS . '/' . $taskId,
            'GET',
            $this->tokenCoreStorage->getToken(),
            [
                'status' => $data->getStatus(),
                'result' => $data->getData(),
            ],
        );

        return $this->initData($response);
    }

    /**
     * @param                 $taskId
     * @param TaskOutputData  $data
     *
     * @return array|false|string|\WP_Error
     * @throws CdekApiException
     * @throws CdekScheduledTaskException
     * @throws \JsonException
     */
    public function sendTaskData($taskId, TaskOutputData $data)
    {
        $response = $this->coreClient->sendCdekRequest(
            $this->getShopApiUrl() . '/' . self::TASKS . '/' . $taskId,
            'PUT',
            $this->tokenCoreStorage->getToken(),
            [
                'status' => $data->getStatus(),
                'result' => $data->getData(),
            ],
            [
                'X-Current-Page' => $data->getCurrentPage(),
                'X-Total-Pages'  => $data->getTotalPages(),
            ],
        );

        return $this->initData($response);
    }

    public function isServerError(): bool
    {
        return empty($this->status) || str_starts_with($this->status, self::FATAL_ERRORS_FIRST_NUMBER);
    }

    /**
     * @return mixed|string
     * @throws CdekApiException
     * @throws CdekScheduledTaskException
     * @throws \JsonException
     */
    private function getShopApiUrl(): string
    {
        return $this->tokenCoreStorage->getPath();
    }

    /**
     * @param $response
     *
     * @return array
     * @throws CdekScheduledTaskException
     */
    private function initData($response): array
    {
        $decodeResponse = json_decode($response['body'], true);

        $this->status = $response['response']['status'];

        if (
            !$this->isSuccessStatus()
            &&
            !$this->isServerError()
            ||
            (
                empty($decodeResponse['success'])
                &&
                $this->status !== self::EMPTY_ANSWER
            )
        ) {
            throw new CdekScheduledTaskException(
                '[CDEKDelivery] Failed to get core api response',
                'cdek_error.core.response',
                $response
            );
        }

        return $decodeResponse;
    }

    private function isSuccessStatus(): bool
    {
        if($this->status === self::SUCCESS_STATUS){
            return true;
        }

        if($this->status === self::FINISH_STATUS){
            return true;
        }

        if($this->status === self::HAS_NEXT_INFO_STATUS){
            return true;
        }

        if($this->status === self::EMPTY_ANSWER){
            return true;
        }

        return false;
    }
}
