<?php

namespace Cdek;

use Cdek\Model\Tariff;
use Cdek\Transport\HttpClient;
use WP_Http;

class CdekApi
{
    protected const TOKEN_PATH = "oauth/token";
    protected const REGION_PATH = "location/cities";
    protected const ORDERS_PATH = "orders/";
    protected const PVZ_PATH = "deliverypoints";
    protected const CALC_PATH = "calculator/tariff";
    protected const WAYBILL_PATH = "print/orders/";
    protected const CALL_COURIER = "intakes";

    protected $apiUrl;
    protected $adminSetting;

    protected $clientId;
    protected $clientSecret;

    public function __construct()
    {
        $this->adminSetting = Helper::getSettingDataPlugin();
        $this->apiUrl = $this->getApiUrl();

        if (array_key_exists('test_mode', $this->adminSetting) && $this->adminSetting['test_mode'] === 'yes') {
            $this->clientId = CDEK_TEST_CLIENT_ID;
            $this->clientSecret = CDEK_TEST_CLIENT_SECRET;
        } else {
            $this->clientId = $this->adminSetting['client_id'];
            $this->clientSecret = $this->adminSetting['client_secret'];
        }
    }

    public function getToken()
    {
        $authUrl  = $this->getAuthUrl();
        $response = wp_remote_post($authUrl);
        $bodyJson = wp_remote_retrieve_body($response);
        $body = json_decode($bodyJson);

	    if ($body === null || property_exists($body, 'error')) {
		    return false;
	    }

	    return "Bearer " . $body->access_token;
    }

	protected function getAuthUrl(): string
    {
		return $this->apiUrl . self::TOKEN_PATH . "?grant_type=client_credentials&client_id=" . $this->clientId . "&client_secret=" . $this->clientSecret;
	}

	public function checkAuth(): bool
    {
		$token = $this->getToken();

		if (!$token) {
			return false;
		}

		return true;
	}

	public function getOrder($uuid)
	{
		$url = $this->apiUrl . self::ORDERS_PATH . $uuid;
		return HttpClient::sendCdekRequest($url, 'GET', $this->getToken());
	}

    public function getOrderByCdekNumber($number)
    {
        $url = $this->apiUrl . self::ORDERS_PATH;
        return HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), ['cdek_number' => $number]);
    }

    public function createOrder($param)
    {
        $url = $this->apiUrl . self::ORDERS_PATH;
        $param['developer_key'] = '7wV8tk&r6VH4zK:1&0uDpjOkvM~qngLl';
        $param['date_invoice'] = date('Y-m-d');
        $param['shipper_name'] = $this->adminSetting['shipper_name'];
        $param['shipper_address'] = $this->adminSetting['shipper_address'];
        $param['sender']['passport_series'] = $this->adminSetting['passport_series'];
        $param['sender']['passport_number'] = $this->adminSetting['passport_number'];
        $param['sender']['passport_date_of_issue'] = $this->adminSetting['passport_date_of_issue'];
        $param['sender']['passport_organization'] = $this->adminSetting['passport_organization'];
        $param['sender']['tin'] = $this->adminSetting['tin'];
        $param['sender']['passport_date_of_birth'] = $this->adminSetting['passport_date_of_birth'];
        $param['seller'] = ['address' => $this->adminSetting['seller_address']];

        if (Tariff::isTariffFromStoreByCode($param['tariff_code'])) {
            $param['shipment_point'] = $this->adminSetting['pvz_code'];
        } else {
            $param['from_location'] = [
                'code' => $this->adminSetting['city_code_value'],
                'address' => $this->adminSetting['street']
            ];
        }

	    return HttpClient::sendCdekRequest($url, 'POST', $this->getToken(), json_encode($param));
    }

    public function getWaybillByLink($link)
    {
        $WP_Http = new WP_Http();
        $resp = $WP_Http->request( $link, [
            'method' => 'GET',
            'headers' => [
                "Content-Type" => "application/json",
                "Authorization" => $this->getToken()
            ],
        ] );

        return $resp['body'];
    }

    public function createWaybill($orderUuid)
    {
        $url = $this->apiUrl . self::WAYBILL_PATH;
        return HttpClient::sendCdekRequest($url, 'POST', $this->getToken(), json_encode(['orders' => ['order_uuid' => $orderUuid]]));
    }

    public function deleteOrder($uuid)
    {
        $url = $this->apiUrl . self::ORDERS_PATH . $uuid;
        return HttpClient::sendCdekRequest($url, 'DELETE', $this->getToken());
    }

    public function getPvz($city, $weight = 0, $admin = false)
    {
        $url = $this->apiUrl . self::PVZ_PATH;
        if (empty($city)) {
            $city = '44';
        }

        if ($city === -1) {
            return json_encode([]);
        }

        $params = ['city_code' => $city];
        if ($admin) {
            $params['type'] = 'PVZ';
        } else {
            if ((int)$weight !== 0) {
                $params['weight_max'] = (int)ceil($weight);
            }
        }

        $result = HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), $params);
        $json = json_decode($result);
        if (!$json) {
            return json_encode(['success' => false, 'message' => CDEK_NO_DELIVERY_POINTS_IN_CITY]);
        }

        $pvz = [];
        foreach ($json as $elem) {
            if (isset($elem->code, $elem->type, $elem->location->longitude, $elem->location->latitude, $elem->location->address)) {
                $pvz[] = [
                    'code' => $elem->code,
                    'type' => $elem->type,
                    'longitude' => $elem->location->longitude,
                    'latitude' => $elem->location->latitude,
                    'address' => $elem->location->address
                ];
            }
        }

        return json_encode(['success' => true, 'pvz' => $pvz]);
    }

    public function calculate($deliveryParam, $tariff)
    {
        $url = $this->apiUrl . self::CALC_PATH;
        return HttpClient::sendCdekRequest($url, 'POST', $this->getToken(), json_encode([
            'tariff_code' => $tariff,
            'from_location' => [
                'code' => $this->adminSetting['city_code_value']
            ],
            'to_location' => [
                'code' => $deliveryParam['cityCode'],
            ],
            'packages' => [
                'weight' => $deliveryParam['package_data']['weight'],
                'length' => $deliveryParam['package_data']['length'],
                'width' => $deliveryParam['package_data']['width'],
                'height' => $deliveryParam['package_data']['height'],
            ],
            'services' => array_key_exists('selected_services', $deliveryParam) ? $deliveryParam['selected_services'] : []
        ]));
    }

    public function getRegion($city = null)
    {
        $url = $this->apiUrl . self::REGION_PATH;
        return HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), ['city' => $city]);
    }

    public function getCityCodeByCityName($city, $state): int
    {
        $url = $this->apiUrl . self::REGION_PATH;

        //по запросу к api v2 климовск записан как "климовск микрорайон" поэтому добавляем "микрорайон"
        if (mb_strtolower($city) === 'климовск') {
            $city = $city . ' микрорайон';
        }

        $cityData = json_decode(HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), ['city' => $city]));

        if ($state == 'false') {
            return $cityData[0]->code;
        }

        if (empty($cityData)) {
            return -1;
        }

        if (count($cityData) > 1) {
            foreach ($cityData as $data) {
                if ($this->getFormatState($data->region) === $this->getFormatState($state)) {
                    return $data->code;
                }
                if ($this->getFormatState($data->region) === $this->getFormatState($city)) {
                    return $data->code;
                }
            }
        }
        return $cityData[0]->code;
    }

    protected function getFormatState($state)
    {
        $state = mb_strtolower($state);
        $regionType = ['автономная область', 'область', 'республика', 'респ.', 'автономный округ', 'округ', 'край', 'обл.', 'обл'];
        foreach ($regionType as $type) {
            $state = str_replace($type, '', $state);
        }
        return trim($state);
    }

    public function getCityCode($city, $postalCode)
    {
        $url = $this->apiUrl . self::REGION_PATH;
        $cityData = json_decode(HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), ['city' => $city, 'postal_code' => $postalCode]));
        return $cityData[0]->code;
    }

    public function getCityByCode($code)
    {
        $url = $this->apiUrl . self::REGION_PATH;
        $cityData = json_decode(HttpClient::sendCdekRequest($url, 'GET', $this->getToken(), ['code' => $code]));
        return ['city' => $cityData[0]->city, 'region' => $cityData[0]->region];
    }

    public function getPvzCodeByPvzAddressNCityCode($pvzInfo, $cityCode)
    {
        $pvz = json_decode($this->getPvz($cityCode));
        if ($pvz->success) {
            foreach ($pvz->pvz as $point) {
                if ($point->address === $pvzInfo) {
                    return $point->code;
                }
            }
        }
        return false;
    }

    private function getApiUrl()
    {
        if (array_key_exists('test_mode', $this->adminSetting) && $this->adminSetting['test_mode'] === 'yes') {
            return CDEK_API_URL_TEST;
        }
        return CDEK_API_URL;
    }

    public function callCourier($param)
    {
        $url = $this->apiUrl . self::CALL_COURIER;
        return HttpClient::sendCdekRequest($url, 'POST', $this->getToken(), json_encode($param));
    }

    public function courierInfo($uuid)
    {
        $url = $this->apiUrl . self::CALL_COURIER . '/' . $uuid;
        return HttpClient::sendCdekRequest($url, 'GET', $this->getToken());
    }

    public function callCourierDelete($uuid)
    {
        $url = $this->apiUrl . self::CALL_COURIER . '/' . $uuid;
        return HttpClient::sendCdekRequest($url, 'DELETE', $this->getToken());
    }
}
