<?php

namespace Cdek;

use Cdek\Model\Tariff;
use WC_Shipping_Method;

class DeliveryCalc {
    public array $rates = [];

    protected WC_Shipping_Method $method;

    public function __construct() {
        $this->method = Helper::getActualShippingMethod();
    }

    public function calculate($package, $id): bool {
        $api = new CdekApi();
        if (!$api->checkAuth()) {
            return false;
        }

        $cityName = $package["destination"]['city'];
        if (!$cityName) {
            return $this->plug();
        }

        $stateName                 = $this->getState($package["destination"]);
        $deliveryParam['cityCode'] = $api->getCityCodeByCityName($cityName, $stateName);
        if ($deliveryParam['cityCode'] === -1) {
            return $this->plug();
        }

        $deliveryParam['package_data'] = $this->getPackagesData($package['contents']);
        $pvz                           = $api->getPvz($deliveryParam['cityCode'],
            $deliveryParam['package_data']['weight'] / 1000);

        $tariffList = $this->method->get_option('tariff_list');
        $weightInKg = $deliveryParam['package_data']['weight'] / 1000;

        foreach ($tariffList as $tariff) {
            $weightLimit = (int) Tariff::getTariffWeightByCode($tariff);
            if ($weightInKg > $weightLimit) {
                continue;
            }

            if (!$pvz['success'] && Tariff::isTariffToStoreByCode($tariff)) {
                continue;
            }

            if ($this->method->get_option('insurance') === 'yes') {
                $deliveryParam['selected_services'][0] = [
                    'code'      => 'INSURANCE',
                    'parameter' => (int) $package['cart_subtotal'],
                ];
            }

            $calcResult = $api->calculate($deliveryParam, $tariff);

            if (empty($calcResult)) {
                continue;
            }

            $delivery = json_decode($calcResult);

            if (!$this->checkDeliveryResponse($delivery)) {
                continue;
            }

            $minDay = (int) $delivery->period_min + (int) $this->method->get_option('extra_day');
            $maxDay = (int) $delivery->period_max + (int) $this->method->get_option('extra_day');
            $cost   = (int) $delivery->total_sum + (int) $this->method->get_option('extra_cost');

            if ($this->method->get_option('percentprice_toggle') === 'yes') {
                $cost = (int) (($this->method->get_option('percentprice') / 100) * $cost);
            }

            if ($this->method->get_option('fixprice_toggle') === 'yes') {
                $cost = (int) $this->method->get_option('fixprice');
            }

            if (($this->method->get_option('stepprice_toggle') === 'yes') && (int) $package['cart_subtotal'] > (int) $this->method->get_option('stepprice')) {
                $cost = 0;
            }

            if (function_exists('wcml_get_woocommerce_currency_option')) {
                $costTMP = apply_filters('wcml_raw_price_amount', $cost, 'RUB');
                $coef    = $costTMP / $cost;
                $cost    /= $coef;
            }

            $this->rates[] = [
                'id'        => $id.'_'.$tariff,
                'label'     => sprintf("CDEK: %s, (%s-%s дней)", Tariff::getTariffNameByCode($tariff), $minDay,
                    $maxDay),
                'cost'      => $cost,
                'meta_data' => [
                    'tariff_code'     => $tariff,
                    'total_weight_kg' => $weightInKg,
                ],
            ];
        }

        return true;
    }

    protected function plug(): bool {
        $this->rates[] = [
            'id'    => 'official_cdek_plug',
            'label' => Helper::getTariffPlugName(),
            'cost'  => 0,
        ];

        return true;
    }

    protected function getState($destination): string {
        $state = '';
        if (array_key_exists('state', $destination)) {
            $state = $destination['state'];
        }

        return $state;
    }

    protected function getPackagesData($contents): array {
        $totalWeight = 0;
        $lengthList  = [];
        $widthList   = [];
        $heightList  = [];
        foreach ($contents as $productGroup) {
            $quantity  = $productGroup['quantity'];
            $weight    = $productGroup['data']->get_weight();
            $dimension = get_option('woocommerce_dimension_unit');
            if ($dimension === 'mm') {
                $lengthList[] = (int) ((int) $productGroup['data']->get_length() / 10);
                $widthList[]  = (int) ((int) $productGroup['data']->get_width() / 10);
                $heightList[] = (int) ((int) $productGroup['data']->get_height() / 10);
            } else {
                $lengthList[] = (int) $productGroup['data']->get_length();
                $widthList[]  = (int) $productGroup['data']->get_width();
                $heightList[] = (int) $productGroup['data']->get_height();
            }

            $weightClass = new WeightCalc();
            $weight      = $weightClass->getWeight($weight);
            $totalWeight += $quantity * $weight;
        }

        rsort($lengthList);
        rsort($widthList);
        rsort($heightList);

        $length = $lengthList[0];
        $width  = $widthList[0];
        $height = $heightList[0];

        $useDefaultValue = $this->method->get_option('product_package_default_toggle') === 'yes';
        foreach (['length', 'width', 'height'] as $dimension) {
            if ($$dimension === 0 || $useDefaultValue) {
                $$dimension = (int) $this->method->get_option("product_{$dimension}_default");
            }
        }

        return ['length' => $length, 'width' => $width, 'height' => $height, 'weight' => $totalWeight];
    }

    protected function checkDeliveryResponse($delivery): bool
    {
        return !property_exists($delivery, 'errors');
    }
}
