<?php

namespace Cdek;

use Cdek\Model\Tariff;
use WP_Http;

class CdekApi
{
    protected const API = "https://api.cdek.ru/v2/";
    protected const TOKEN_PATH = "oauth/token";
    protected const REGION_PATH = "location/cities";
    protected const ORDERS_PATH = "orders/";
    protected const PVZ_PATH = "deliverypoints";
    protected const CALC_PATH = "calculator/tariff";
    protected const WAYBILL_PATH = "print/orders/";

    protected $adminSetting;
    protected $httpClient;

    public function __construct()
    {
        $this->adminSetting = Helper::getSettingDataPlugin();

        $this->httpClient = new HttpClientWrapper();
    }

    public function getToken()
    {
        $authUrl  = $this->getAuthUrl();
        $response = wp_remote_post($authUrl);
        $bodyJson = wp_remote_retrieve_body($response);
        $body = json_decode($bodyJson);

	    if (property_exists($body, 'error')) {
		    return false;
	    }

	    return "Bearer " . $body->access_token;
    }

	protected function getAuthUrl(): string
    {
		return self::API . self::TOKEN_PATH . "?grant_type=client_credentials&client_id=" . $this->adminSetting['client_id']
			. "&client_secret=" . $this->adminSetting['client_secret'];
	}

	public function checkAuth(): bool
    {
		$token = $this->getToken();

		if (!$token) {
			return false;
		}

		return true;
	}

	public function getOrder($number)
	{
		$url = self::API . self::ORDERS_PATH . $number;
		return $this->httpClient->sendRequest($url, 'GET', $this->getToken());
	}

    public function createOrder($param)
    {
        $url = self::API . self::ORDERS_PATH;
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

	    return $this->httpClient->sendRequest($url, 'POST', $this->getToken(), json_encode($param));
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
        $url = self::API . self::WAYBILL_PATH;
        return $this->httpClient->sendRequest($url, 'POST', $this->getToken(), json_encode(['orders' => ['order_uuid' => $orderUuid]]));
    }

    public function deleteOrder($number)
    {
        $url = self::API . self::ORDERS_PATH . $number;
        return $this->httpClient->sendRequest($url, 'DELETE', $this->getToken());
    }

    public function getPvz($city, $weight, $admin = false)
    {
        $url = self::API . self::PVZ_PATH;
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
            $params['weight_max'] = (int)ceil($weight);
        }

        $result = $this->httpClient->sendRequest($url, 'GET', $this->getToken(), $params);
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
        $url = self::API . self::CALC_PATH;
        return $this->httpClient->sendRequest($url, 'POST', $this->getToken(), json_encode([
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
            'services' => $deliveryParam['selected_services']
        ]));
    }

    public function getRegion($city = null)
    {
        $url = self::API . self::REGION_PATH;
        return $this->httpClient->sendRequest($url, 'GET', $this->getToken(), ['city' => $city]);
    }

    public function getCityCodeByCityName($city, $state): int
    {
        $url = self::API . self::REGION_PATH;
        $cityData = json_decode($this->httpClient->sendRequest($url, 'GET', $this->getToken(), ['city' => $city]));

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
        $url = self::API . self::REGION_PATH;
        $cityData = json_decode($this->httpClient->sendRequest($url, 'GET', $this->getToken(), ['city' => $city, 'postal_code' => $postalCode]));
        return $cityData[0]->code;
    }

    public function getCityByCode($code)
    {
        $url = self::API . self::REGION_PATH;
        $cityData = json_decode($this->httpClient->sendRequest($url, 'GET', $this->getToken(), ['code' => $code]));
        return ['city' => $cityData[0]->city, 'region' => $cityData[0]->region];
    }
}
