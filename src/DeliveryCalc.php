<?php

namespace Cdek;

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

        $cityName = $package["destination"]['city'];
        if (!$cityName) {
            return $this->plug();
        }

        $stateName = $this->getState($package["destination"]);
        $deliveryParam['cityCode'] = $api->getCityCodeByCityName($cityName, $stateName);
        if ($deliveryParam['cityCode'] === -1) {
            return $this->plug();
        }

        $deliveryParam['package_data'] = $this->getPackagesData($package['contents']);
        $pvz = json_decode($api->getPvz($deliveryParam['cityCode'], $deliveryParam['package_data']['weight'] / 1000));
        $setting = Helper::getSettingDataPlugin();
        $tariffList = $setting['tariff_list'];
        $weightInKg = $deliveryParam['package_data']['weight'] / 1000;

        foreach ($tariffList as $tariff) {
            $weightLimit = (int) Tariff::getTariffWeightByCode($tariff);
            if ($weightInKg > $weightLimit) {
                continue;
            }

            if (!$pvz->success && Tariff::isTariffToStoreByCode($tariff)) {
                continue;
            }

            if ($setting['insurance'] === 'yes') {
                $deliveryParam['selected_services'][0] = ['code' => 'INSURANCE', 'parameter' => (int)$package['cart_subtotal']];
            }

            $calcResult = $api->calculate($deliveryParam, $tariff);

            if (empty($calcResult)) {
                continue;
            }

            $delivery = json_decode($calcResult);

            if (!$this->checkDeliveryResponse($delivery)) {
                continue;
            }

            $minDay = (int)$delivery->period_min + (int)$setting['extra_day'];
            $maxDay = (int)$delivery->period_max + (int)$setting['extra_day'];
            $cost = (int)$delivery->total_sum + (int)$setting['extra_cost'];

            if ($setting['percentprice_toggle'] === 'yes') {
                $cost = (int) (($setting['percentprice'] / 100) * $cost);
            }

            if ($setting['fixprice_toggle'] === 'yes') {
                $cost = (int)$setting['fixprice'];
            }

            if ($setting['stepprice_toggle'] === 'yes') {
                if ((int)$package['cart_subtotal'] > (int)$setting['stepprice']) {
                    $cost = 0;
                }
            }

            if (function_exists( 'wcml_get_woocommerce_currency_option' )) {
                $costTMP = apply_filters( 'wcml_raw_price_amount', $cost, 'RUB');
                $coef = $costTMP / $cost;
                $cost = $cost / $coef;
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

        $setting = Helper::getSettingDataPlugin();

        if ($setting['product_package_default_toggle'] === 'yes') {
            $length = (int)$setting['product_length_default'];
            $width = (int)$setting['product_width_default'];
            $height = (int)$setting['product_height_default'];
        } else {
            if ($length === 0) {
                $length = (int)$setting['product_length_default'];
            }

            if ($width === 0) {
                $width = (int)$setting['product_width_default'];
            }

            if ($height === 0) {
                $height = (int)$setting['product_height_default'];
            }
        }


        return ['length' => $length, 'width' => $width, 'height' => $height, 'weight' => $totalWeight];
    }

    protected function checkDeliveryResponse($delivery): bool
    {
        if (!property_exists($delivery, 'errors')) {
            return true;
        } else {
            return false;
        }
    }


    protected function plug(): bool
    {
        $this->rates[] = [
            'id' => 'official_cdek_plug',
            'label' => Helper::getTariffPlugName(),
            'cost' => 0
        ];
        return true;
    }
}