<?php

namespace Cdek;

use Cdek\Model\AdminSetting;
use Cdek\Model\DefaultPackage;
use Cdek\Model\Tariff;

class DeliveryCalc
{
    public $rates = [];

    public function calculate($package, $id): bool
    {
        $api = new CdekApi();
        if (!$api->checkAuth()) {
            return false;
        }

        $deliveryParam['city'] = $package["destination"]['city'];
        if (!$deliveryParam['city']) {
            return false;
        }

        $cdekShippingSettings = Helper::getSettingDataPlugin();
        $tariffList = $cdekShippingSettings['tariff_list'];
        $deliveryParam['state'] = $this->getState($package["destination"]);
        $deliveryParam['package_data'] = $this->getPackagesData($package['contents']);
        $services = $cdekShippingSettings['service_list'];
        $weightInKg = $deliveryParam['package_data']['weight'] / 1000;

        $api = new CdekApi();
        $adminSetting = new AdminSetting();
        $setting = $adminSetting->getCurrentSetting();
        foreach ($tariffList as $tariff) {

            $weightLimit = (int) Tariff::getTariffWeightByCode($tariff);
            if ($weightInKg > $weightLimit) {
                continue;
            }

            $deliveryParam['selected_services'] = $this->getServicesList($services, $tariff);
            if ($setting->insurance === 'yes') {
                $deliveryParam['selected_services'][] = ['code' => 'INSURANCE', 'parameter' => (int)$package['cart_subtotal']];
            }

            $codeCity = $api->getCityCodeByCityName($deliveryParam['city'], $deliveryParam['state']);
            $pvz = json_decode($api->getPvz($codeCity, $deliveryParam['package_data']['weight'] / 1000));

            if (empty($pvz)) {
                continue;
            }

            $calcResult = $api->calculate($deliveryParam, $tariff);

            if (empty($calcResult)) {
                continue;
            }

            $delivery = json_decode($calcResult);

            if (!$this->checkDeliveryResponse($delivery)) {
                continue;
            }

            $minDay = (int)$delivery->period_min + (int)$setting->extraDay;
            $maxDay = (int)$delivery->period_max + (int)$setting->extraDay;
            $cost = (int)$delivery->total_sum + (int)$setting->extraCost;

            if ($setting->percentPriceToggle === 'yes') {
                $cost = (int) (($setting->percentPrice / 100) * $cost);
            }

            if ($setting->fixPriceToggle === 'yes') {
                $cost = (int)$setting->fixPrice;
            }

            if ($setting->stepPriceToggle === 'yes') {
                if ((int)$package['cart_subtotal'] > (int)$setting->stepPrice) {
                    $cost = 0;
                }
            }

            $this->rates[] = [
                'id' => $id . '_' . $tariff,
                'label' => sprintf(
                    "CDEK: %s, (%s-%s дней)",
                    Tariff::getTariffNameByCode($tariff),
                    $minDay,
                    $maxDay
                ),
                'cost' => $cost,
                'meta_data' => [
                    'tariff_code' => $tariff,
                    'total_weight_kg' => $weightInKg
                ]
            ];
        }

        return true;
    }

    protected function getState($destination): string
    {
        $state = '';
        if (array_key_exists('state', $destination)) {
            $state = $destination['state'];
        }
        return $state;
    }

    protected function getPackagesData($contents): array
    {
        $totalWeight = 0;
        $lengthList = [];
        $widthList = [];
        $heightList = [];
        foreach ($contents as $productGroup) {
            $quantity = $productGroup['quantity'];
            $weight = $productGroup['data']->get_weight();
            $dimension = get_option('woocommerce_dimension_unit');
            if ($dimension === 'mm') {
                $lengthList[] = (int)((int)$productGroup['data']->get_length() / 10);
                $widthList[] = (int)((int)$productGroup['data']->get_width() / 10);
                $heightList[] = (int)((int)$productGroup['data']->get_height() / 10);
            } else {
                $lengthList[] = (int)$productGroup['data']->get_length();
                $widthList[] = (int)$productGroup['data']->get_width();
                $heightList[] = (int)$productGroup['data']->get_height();
            }

            $weightClass = new WeightCalc();
            $weight = $weightClass->getWeight($weight);
            $totalWeight += $quantity * $weight;
        }

        rsort($lengthList);
        rsort($widthList);
        rsort($heightList);

        $length = $lengthList[0];
        $width = $widthList[0];
        $height = $heightList[0];

        $adminSetting = new AdminSetting();
        $setting = $adminSetting->getCurrentSetting();

        if ($setting->productPackageDefaultToggle === 'yes') {
            $length = (int)$setting->productLengthDefault;
            $width = (int)$setting->productWidthDefault;
            $height = (int)$setting->productHeightDefault;
        } else {
            if ($length === 0) {
                $length = (int)$setting->productLengthDefault;
            }

            if ($width === 0) {
                $width = (int)$setting->productWidthDefault;
            }

            if ($height === 0) {
                $height = (int)$setting->productHeightDefault;
            }
        }


        return ['length' => $length, 'width' => $width, 'height' => $height, 'weight' => $totalWeight];
    }

    protected function getServicesList($services, $tariff): array
    {
        $servicesListForParam = [];
        if ($services !== "") {
            foreach ($services as $service) {
                if ($service === 'DELIV_RECEIVER' && $tariff == '62') {
                    $servicesListForParam['code'] = $service;
                }
            }
        }
        return $servicesListForParam;
    }

    protected function checkDeliveryResponse($delivery): bool
    {
        if (!property_exists($delivery, 'errors')) {
            return true;
        } else {
            return false;
        }
    }
}