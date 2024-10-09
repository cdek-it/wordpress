<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Contracts\TokenStorageContract;
    use Cdek\Enums\BarcodeFormat;
    use Cdek\Exceptions\AuthException;
    use Cdek\Exceptions\CdekApiException;
    use Cdek\Exceptions\CdekClientException;
    use Cdek\Exceptions\CdekServerException;
    use Cdek\Exceptions\RestApiInvalidRequestException;
    use Cdek\Helpers\DBTokenStorage;
    use Cdek\Transport\HttpClient;
    use WC_Shipping_Method;

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
        private WC_Shipping_Method $deliveryMethod;

        private TokenStorageContract $tokenStorage;


        public function __construct(?int $shippingInstanceId = null, ?TokenStorageContract $tokenStorage = null)
        {
            $this->deliveryMethod = Helper::getActualShippingMethod($shippingInstanceId);
            $this->apiUrl         = $this->getApiUrl();

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
                return $_ENV['CDEK_REST_API'] ?? Config::TEST_API_URL;
            }

            return Config::API_URL;
        }

        /**
         * @throws CdekApiException|\JsonException
         */
        final public function checkAuth(): bool
        {
            try {
                $this->tokenStorage->getToken();

                return true;
            } catch (AuthException $e) {
                return false;
            }
        }

        /**
         * @throws AuthException
         * @throws \JsonException
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
                    throw new AuthException(
                        'Failed to get the token', 'authorization.integration', $body->json(), true,
                    );
                }

                return $body->json()['access_token'];
            } catch (CdekApiException $e) {
                throw new AuthException('Failed to get the token', 'auth.integration', $e->getData(), true);
            }
        }

        /**
         * @throws \JsonException
         * @throws CdekApiException
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
         * @param array $params
         *
         * @return array
         * @throws AuthException
         * @throws CdekApiException
         * @throws CdekClientException
         * @throws CdekServerException
         * @throws RestApiInvalidRequestException
         * @throws \JsonException
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
         * @throws CdekApiException
         */
        public function getFileByLink(string $link): string
        {
            return HttpClient::sendJsonRequest($link, 'GET', $this->tokenStorage->getToken())->body();
        }

        /**
         * @throws \JsonException
         * @throws CdekApiException
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
         * @throws \JsonException
         * @throws CdekApiException
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
         * @throws \JsonException
         * @throws CdekApiException
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
         * @throws \JsonException
         * @throws CdekApiException
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
         * @throws \JsonException
         * @throws CdekApiException
         */
        public function deleteOrder($uuid): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::ORDERS_PATH.$uuid,
                'DELETE',
                $this->tokenStorage->getToken(),
            )->json();
        }

        /**
         * @throws \JsonException
         * @throws CdekApiException
         */
        public function calculateTariffList($deliveryParam): array
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
         * @throws CdekApiException
         * @throws \JsonException
         */
        public function calculateTariff($deliveryParam)
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
         * @throws \JsonException
         * @throws CdekApiException
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
            } catch (CdekApiException $e) {
                return -1;
            }
        }

        /**
         * @throws CdekApiException
         * @throws \JsonException
         */
        public function getOffices($filter)
        {
            $result = HttpClient::sendJsonRequest(
                $this->apiUrl.self::PVZ_PATH,
                'GET',
                $this->tokenStorage->getToken(),
                $filter,
            )->json();
            if (!$result) {
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
         * @throws \JsonException
         * @throws CdekApiException
         */
        public function callCourier($param): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::CALL_COURIER,
                'POST',
                $this->tokenStorage->getToken(),
                $param,
            )->json();
        }

        /**
         * @throws CdekApiException
         * @throws \JsonException
         */
        public function courierInfo($uuid): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::CALL_COURIER.'/'.$uuid,
                'GET',
                $this->tokenStorage->getToken(),
            )->json();
        }

        /**
         * @throws CdekApiException
         * @throws \JsonException
         */
        public function callCourierDelete($uuid): array
        {
            return HttpClient::sendJsonRequest(
                $this->apiUrl.self::CALL_COURIER.'/'.$uuid,
                'DELETE',
                $this->tokenStorage->getToken(),
            )->json();
        }
    }
}
