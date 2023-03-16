<?php

namespace Cdek;

use Cdek\Model\AdminSetting;
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

    public function __construct($currentAdminSetting = null)
    {
        $adminSetting = new AdminSetting();
        $this->adminSetting = $adminSetting->getCurrentSetting($currentAdminSetting);

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
		return self::API . self::TOKEN_PATH . "?grant_type=client_credentials&client_id=" . $this->adminSetting->clientId
			. "&client_secret=" . $this->adminSetting->clientSecret;
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
        $param['developer_key'] = $this->adminSetting->developerKey;
        $param['date_invoice'] = date('Y-m-d');
        $param['shipper_name'] = $this->adminSetting->shipperName;
        $param['shipper_address'] = $this->adminSetting->shipperAddress;
        $param['seller'] = ['address' => $this->adminSetting->sellerAddress];

        if (Tariff::getTariffModeByCode($param['tariff_code'])) {
            $param['shipment_point'] = $this->adminSetting->pvzCode;
        } else {
            $param['from_location'] = [
                'code' => $this->adminSetting->fromCity,
                'address' => $this->adminSetting->fromAddress
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

        if ($admin) {
            $result = $this->httpClient->sendRequest($url, 'GET', $this->getToken(), ['city_code' => $city, 'type' => 'PVZ']);
        } else {
            $result = $this->httpClient->sendRequest($url, 'GET', $this->getToken(), ['city_code' => $city, 'weight_max' => (int)ceil($weight)]);
        }
        $pvz = array_map(function($elem) {
            return [
                'code' => $elem->code,
                'type' => $elem->type,
                'longitude' => $elem->location->longitude,
                'latitude' => $elem->location->latitude,
                'address' => $elem->location->address
            ];
            }, json_decode($result));
        return json_encode($pvz);
    }

    public function calculate($deliveryParam, $tariff)
    {
        $url = self::API . self::CALC_PATH;

        $toLocationCityCode = $this->getCityCodeByCityName($deliveryParam['city'], $deliveryParam['state']);

        if ($toLocationCityCode === -1) {
            return [];
        }

        $token = $this->getToken();
        return $this->httpClient->sendRequest($url, 'POST', $token, json_encode([
            'tariff_code' => $tariff,
            'from_location' => [
                'code' => $this->adminSetting->fromCity
            ],
            'to_location' => [
                'code' => $toLocationCityCode,
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
            return -1;
        }
        return $cityData[0]->code;
    }

    protected function getFormatState($state)
    {
        $state = mb_strtolower($state);
        $regionType = ['автономная область', 'область', 'республика', 'респ.', 'автономный округ', 'округ', 'край', 'обл.'];
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
