<?php

namespace Cdek\Model;

use Cdek\Helper;

class AdminSetting
{
    public $grantType = 'client_credentials';
    public $clientId;
    public $clientSecret;
    public $tariffList;
    public $sellerName;
    public $sellerPhone;
    public $fromCity;
    public $fromAddress;
    public $pvzCode;
    public $pvzAddress;
    public $developerKey = '7wV8tk&r6VH4zK:1&0uDpjOkvM~qngLl';
    public $shipperName;
    public $shipperAddress;
    public $sellerAddress;
    public $extraDay;
    public $extraCost;
    public $insurance;
    public $fixPriceToggle;
    public $fixPrice;
    public $stepPriceToggle;
    public $stepPrice;
    public $percentPriceToggle;
    public $percentPrice;
    public $productLengthDefault;
    public $productWidthDefault;
    public $productHeightDefault;
    public $productPackageDefaultToggle;

    public function getCurrentSetting($currentAdminSetting = null): AdminSetting
    {
        $adminSetting = new AdminSetting();

        if ($currentAdminSetting) {
            $cdekShippingSettings = $currentAdminSetting;
        } else {
            $cdekShippingSettings = Helper::getSettingDataPlugin();
        }

        $adminSetting->clientId = $cdekShippingSettings['client_id'] ?? '';
        $adminSetting->clientSecret = $cdekShippingSettings['client_secret'] ?? '';
        $adminSetting->tariffList = $cdekShippingSettings['tariff_list'] ?? '';
        $adminSetting->sellerName = $cdekShippingSettings['seller_name'] ?? '';
        $adminSetting->sellerPhone = $cdekShippingSettings['seller_phone'] ?? '';
        $adminSetting->fromCity = $cdekShippingSettings['city_code_value'] ?? '';
        $adminSetting->fromAddress = $cdekShippingSettings['address'] ?? '';
        $adminSetting->pvzCode = $cdekShippingSettings['pvz_code'] ?? '';
        $adminSetting->pvzAddress = $cdekShippingSettings['pvz_address'] ?? '';
        $adminSetting->shipperName = $cdekShippingSettings['shipper_name'] ?? '';
        $adminSetting->shipperAddress = $cdekShippingSettings['shipper_address'] ?? '';
        $adminSetting->sellerAddress = $cdekShippingSettings['seller_address'] ?? '';
        $adminSetting->extraDay = $cdekShippingSettings['extra_day'] ?? '';
        $adminSetting->extraCost = $cdekShippingSettings['extra_cost'] ?? '';
        $adminSetting->insurance = $cdekShippingSettings['insurance'] ?? '';
        $adminSetting->fixPriceToggle = $cdekShippingSettings['fixprice_toggle'] ?? '';
        $adminSetting->fixPrice = $cdekShippingSettings['fixprice'] ?? '';
        $adminSetting->stepPriceToggle = $cdekShippingSettings['stepprice_toggle'] ?? '';
        $adminSetting->stepPrice = $cdekShippingSettings['stepprice'] ?? '';
        $adminSetting->percentPriceToggle = $cdekShippingSettings['percentprice_toggle'] ?? '';
        $adminSetting->percentPrice = $cdekShippingSettings['percentprice'] ?? '';
        $adminSetting->productLengthDefault = $cdekShippingSettings['product_length_default'] ?? '';
        $adminSetting->productWidthDefault = $cdekShippingSettings['product_width_default'] ?? '';
        $adminSetting->productHeightDefault = $cdekShippingSettings['product_height_default'] ?? '';
        $adminSetting->productPackageDefaultToggle = $cdekShippingSettings['product_package_default_toggle'] ?? '';
        return $adminSetting;
    }
}
