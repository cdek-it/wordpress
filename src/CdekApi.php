<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Contracts\TokenStorageContract;
    use Cdek\Enums\BarcodeFormat;
    use Cdek\Exceptions\External\ApiException;
    use Cdek\Exceptions\External\LegacyAuthException;
    use Cdek\Exceptions\External\RestApiInvalidRequestException;
    use Cdek\Helpers\DBTokenStorage;
    use Cdek\Transport\HttpClient;

    /**
     * @deprecated use CoreApi instead
     */
    final class CdekApi
    {
        private const TOKEN_PATH = 'oauth/token';
        private const REGION_PATH = 'location/cities';
        private const ORDERS_PATH = 'orders/';
        private const PVZ_PATH = 'deliverypoints';
        private const CALC_LIST_PATH = 'calculator/tarifflist';
        private const CALC_PATH = 'calculator/tariff';
        private const WAYBILL_PATH = 'print/orders/';
        private const BARCODE_PATH = 'print/barcodes/';
        private const CALL_COURIER = 'intakes';

        private string $apiUrl;

        private string $clientId;
        private string $clientSecret;
        private ShippingMethod $deliveryMethod;

        private TokenStorageContract $tokenStorage;


        public function __construct(?int $shippingInstanceId = null, ?TokenStorageContract $tokenStorage = null)
        {
            $this->deliveryMethod = Helper::getActualShippingMethod($shippingInstanceId);
            $this->apiUrl         = $this->getApiUrl();

            /** @noinspection GlobalVariableUsageInspection */
            if (!isset($_ENV['CDEK_REST_API']) && $this->deliveryMethod->get_option('test_mode') === 'yes') {
                $this->clientId     = Config::TEST_CLIENT_ID;
                $this->clientSecret = Config::TEST_CLIENT_SECRET;
            } else {
                $this->clientId     = $this->deliveryMethod->get_option('client_id');
                $this->clientSecret = $this->deliveryMethod->get_option('client_secret');
            }

            $this->tokenStorage = $tokenStorage ?? new DBTokenStorage();
        }

        private function getApiUrl(): string
        {
            if ($this->deliveryMethod->get_option('test_mode') === 'yes') {
                /** @noinspection GlobalVariableUsageInspection */
                return $_ENV['CDEK_REST_API'] ?? Config::TEST_API_URL;
            }

            return Config::API_URL;
        }

        public function checkAuth(): bool
        {
            try {
                $this->tokenStorage->getToken();

                return true;
            } catch (LegacyAuthException $e) {
                return false;
            }
        }

        /**
         * @throws LegacyAuthException
         */
        public function fetchToken(): string
        {
            try {
                $body = HttpClient::processRequest(
                    sprintf('%s%s?%s', $this->apiUrl, self::TOKEN_PATH, http_build_query([
                        'grant_type'    => 'client_credentials',
                        'client_id'     => $this->clientId,
                        'client_secret' => $this->clientSecret,
                    ])),
                    'POST',
                );

                if (!isset($body->json()['access_token'])) {
                    throw new LegacyAuthException([
                        ...$body->json(),
                        'host'   => parse_url($this->apiUrl, PHP_URL_HOST),
                        'client' => $this->clientId,
                    ]);
                }

                return $body->json()['access_token'];
            } catch (ApiException $e) {
                throw new LegacyAuthException(
                    [...$e->getData(), 'host' => parse_url($this->apiUrl, PHP_URL_HOST), 'client' => $this->clientId],
                );
            }
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        final public function getOrder(string $uuid): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::ORDERS_PATH.$uuid,
                'GET',
                $this->tokenStorage->getToken(),
            )->json();
        }

        /**
         * @param  array  $params
         *
         * @return array
         * @throws LegacyAuthException
         * @throws ApiException
         * @throws RestApiInvalidRequestException
         */
        public function createOrder(array $params): array
        {
            $url                     = $this->apiUrl.self::ORDERS_PATH;
            $params['developer_key'] = Config::DEV_KEY;

            $result
                = HttpClient::sendJsonRequest($url, 'POST', $this->tokenStorage->getToken(), $params)->json();

            $request = $result['requests'][0];

            if ($request['state'] === 'INVALID') {
                throw new RestApiInvalidRequestException(self::ORDERS_PATH, $request['errors']);
            }

            return $result;
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function getFileByLink(string $link): string
        {
            return HttpClient::sendJsonRequest($link, 'GET', $this->tokenStorage->getToken())->body();
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function createWaybill(string $orderUuid): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::WAYBILL_PATH,
                'POST',
                $this->tokenStorage->getToken(),
                ['orders' => ['order_uuid' => $orderUuid]],
            )->json();
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function createBarcode(string $orderUuid): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::BARCODE_PATH,
                'POST',
                $this->tokenStorage->getToken(),
                [
                    'orders' => ['order_uuid' => $orderUuid],
                    'format' => BarcodeFormat::getByIndex(
                        $this->deliveryMethod->get_option(
                            'barcode_format',
                            0,
                        ),
                    ),
                ],
            )->json();
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function getBarcode(string $uuid): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::BARCODE_PATH.$uuid,
                'GET',
                $this->tokenStorage->getToken(),
            )->json();
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function getWaybill(string $uuid): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::WAYBILL_PATH.$uuid,
                'GET',
                $this->tokenStorage->getToken(),
            )->json();
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function deleteOrder(string $uuid): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::ORDERS_PATH.$uuid,
                'DELETE',
                $this->tokenStorage->getToken(),
            )->json();
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function calculateTariffList(array $deliveryParam): array
        {
            $request = [
                'type'          => $deliveryParam['type'],
                'from_location' => $deliveryParam['from'],
                'to_location'   => $deliveryParam['to'],
                'packages'      => $deliveryParam['packages'],
            ];

            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::CALC_LIST_PATH,
                'POST',
                $this->tokenStorage->getToken(),
                $request,
            )->json();
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function calculateTariff(array $deliveryParam): array
        {
            $request = [
                'type'          => $deliveryParam['type'],
                'from_location' => $deliveryParam['from'],
                'tariff_code'   => $deliveryParam['tariff_code'],
                'to_location'   => $deliveryParam['to'],
                'packages'      => $deliveryParam['packages'],
                'services'      => array_key_exists('services', $deliveryParam) ? $deliveryParam['services'] : [],
            ];

            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::CALC_PATH,
                'POST',
                $this->tokenStorage->getToken(),
                $request,
            )->json();
        }

        /**
         * @throws LegacyAuthException
         */
        public function getCityCode(string $city, ?string $postcode): int
        {
            //по запросу к api v2 климовск записан как "климовск микрорайон" поэтому добавляем "микрорайон"
            if (mb_strtolower($city) === 'климовск') {
                $city .= ' микрорайон';
            }

            try {
                return HttpClient::sendJsonRequest(
                    $this->apiUrl.self::REGION_PATH,
                    'GET',
                    $this->tokenStorage->getToken(),
                    ['city' => $city, 'postal_code' => $postcode],
                )->json()[0]['code'];
            } catch (ApiException $e) {
                return -1;
            }
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function getOffices(array $filter)
        {
            $result = HttpClient::sendJsonRequest(
                $this->apiUrl.self::PVZ_PATH,
                'GET',
                $this->tokenStorage->getToken(),
                $filter,
            );
            if (!$result->body()) {
                return [
                    'success' => false,
                    'message' => esc_html__(
                        "In this locality, delivery is available only for \"door-to-door\" tariffs. Select another locality to gain access to \"from warehouse\" tariffs.",
                        'cdekdelivery',
                    ),
                ];
            }

            return $result;
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         */
        public function callCourier(array $param): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::CALL_COURIER,
                'POST',
                $this->tokenStorage->getToken(),
                $param,
            )->json();
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function courierInfo(string $uuid): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::CALL_COURIER.'/'.$uuid,
                'GET',
                $this->tokenStorage->getToken(),
            )->json();
        }

        /**
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function callCourierDelete(string $uuid): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::CALL_COURIER.'/'.$uuid,
                'DELETE',
                $this->tokenStorage->getToken(),
            )->json();
        }
    }
}
