<?php

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Contracts\TokenStorageContract;
    use Cdek\Exceptions\CdekApiException;
    use Cdek\Exceptions\CdekScheduledTaskException;
    use Cdek\Helpers\DBCoreTokenStorage;
    use Cdek\Helpers\DBTokenStorage;
    use Cdek\Model\TaskOutputData;
    use Cdek\Transport\HttpCoreClient;

    class CdekCoreApi
    {
        private const SUCCESS_STATUS = 200;
        private const FINISH_STATUS = 201;
        private const HAS_NEXT_INFO_STATUS = 202;
        private const EMPTY_ANSWER = 204;
        private const UNKNOWN_METHOD = 404;
        private const FATAL_ERRORS_FIRST_NUMBER = 5;
        private const TOKEN_PATH = 'cms/wordpress/shops/%s/token';
        private const SHOP = 'cms/wordpress/shops';
        private const TASKS = 'wordpress/tasks';
        private ?int $status;
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
                                                     $response
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
         * @param string|null $next
         *
         * @throws CdekApiException
         * @throws CdekScheduledTaskException
         * @throws \JsonException
         */
        public function taskManager(?string $next = null): array
        {
            $response = $this->coreClient->sendCdekRequest(
                $this->getShopApiUrl() . '/' . self::TASKS . ($next === null ? '' : '?cursor=' . $next),
                'GET',
                $this->tokenCoreStorage->getToken(),
            );

            return $this->initData($response, false);
        }

        /**
         * @param string         $taskId
         * @param TaskOutputData $data
         *
         * @throws \Cdek\Exceptions\CdekApiException
         * @throws \Cdek\Exceptions\CdekScheduledTaskException
         * @throws \JsonException
         */
        public function taskInfo(string $taskId, TaskOutputData $data): array
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
         * @param string         $taskId
         * @param TaskOutputData $data
         *
         * @throws \Cdek\Exceptions\CdekApiException
         * @throws \Cdek\Exceptions\CdekScheduledTaskException
         * @throws \JsonException
         */
        public function sendTaskData(string $taskId, TaskOutputData $data): array
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
            return empty($this->status) || strpos($this->status, self::FATAL_ERRORS_FIRST_NUMBER) === 0;
        }

        /**
         * @throws CdekApiException
         * @throws CdekScheduledTaskException
         * @throws \JsonException
         */
        private function getShopApiUrl(): string
        {
            return $this->tokenCoreStorage->getOrRefreshApiPath();
        }

        /**
         * @param array $response
         *
         * @throws CdekScheduledTaskException
         */
        private function initData(array $response, bool $stopPropagation = true): array
        {
            if($response['error']){
                throw new CdekScheduledTaskException(
                    '[CDEKDelivery] Failed to get core api response',
                    'cdek_error.core.response_error',
                    $response,
                    $stopPropagation,
                );
            }

            $decodeResponse = json_decode($response['body'], true);

            $this->status = $response['response']['code'];

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
                    $response,
                    $stopPropagation,
                );
            }

            return $decodeResponse ?? [];
        }

        private function isSuccessStatus(): bool
        {
            if ($this->status === self::SUCCESS_STATUS) {
                return true;
            }

            if ($this->status === self::FINISH_STATUS) {
                return true;
            }

            if ($this->status === self::HAS_NEXT_INFO_STATUS) {
                return true;
            }

            if ($this->status === self::EMPTY_ANSWER) {
                return true;
            }

            return false;
        }
    }

}
