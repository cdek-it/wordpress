<?php

namespace Cdek\Helpers;

use Cdek\CdekApi;
use Cdek\Config;
use Cdek\Helper;
use Cdek\Model\Tariff;
use WC_Shipping_Method;

class DeliveryCalc {
    protected WC_Shipping_Method $method;
    private array $rates = [];

    public function __construct() {
        $this->method = Helper::getActualShippingMethod();
    }

    public function calculate($package, $id): bool {
        $api = new CdekApi();
        if (!$api->checkAuth()) {
            return false;
        }

        foreach ([Tariff::SHOP_TYPE, Tariff::DELIVERY_TYPE] as $deliveryType) {
            $deliveryParam['type']    = $deliveryType;
            $deliveryParam['address'] = $package['destination']['city'];

            $deliveryParam['package_data'] = $this->getPackagesData($package['contents']);

            $tariffList = $this->method->get_option('tariff_list');
            $weightInKg = $deliveryParam['package_data']['weight'] / 1000;

            if ($this->method->get_option('insurance') === 'yes') {
                $deliveryParam['selected_services'][0] = [
                    'code'      => 'INSURANCE',
                    'parameter' => (int) $package['cart_subtotal'],
                ];
            }

            $calcResult = $api->calculate($deliveryParam);

            if (empty($calcResult)) {
                continue;
            }

            $delivery = json_decode($calcResult, true);

            if (!$this->checkDeliveryResponse($delivery)) {
                continue;
            }

            foreach ($delivery['tariff_codes'] as $tariff) {
                if (isset($this->rates[$tariff['tariff_code']]) ||
                    !in_array((string) $tariff['tariff_code'], $tariffList ?: [], true)) {
                    continue;
                }

                $minDay = (int) $tariff['period_min'] + (int) $this->method->get_option('extra_day');
                $maxDay = (int) $tariff['period_max'] + (int) $this->method->get_option('extra_day');
                $cost   = (int) ($tariff['total_sum'] ?? $tariff['delivery_sum']) +
                          (int) $this->method->get_option('extra_cost');

                if ($this->method->get_option('percentprice_toggle') === 'yes') {
                    $cost = (int) (($this->method->get_option('percentprice') / 100) * $cost);
                }

                if ($this->method->get_option('fixprice_toggle') === 'yes') {
                    $cost = (int) $this->method->get_option('fixprice');
                }

                if (($this->method->get_option('stepprice_toggle') === 'yes') &&
                    (int) $package['cart_subtotal'] > (int) $this->method->get_option('stepprice')) {
                    $cost = 0;
                }

                if (function_exists('wcml_get_woocommerce_currency_option')) {
                    $costTMP = apply_filters('wcml_raw_price_amount', $cost, 'RUB');
                    $coef    = $costTMP / $cost;
                    $cost    /= $coef;
                }

                $this->rates[$tariff['tariff_code']] = [
                    'id'        => sprintf('%s_%s', $id, $tariff['tariff_code']),
                    'label'     => sprintf("CDEK: %s, (%s-%s дней)",
                        Tariff::getTariffUserNameByCode($tariff['tariff_code']), $minDay, $maxDay),
                    'cost'      => $cost,
                    'meta_data' => [
                        Config::ADDRESS_HASH_META_KEY => sha1($deliveryParam['address']),
                        'tariff_code'                 => $tariff['tariff_code'],
                        'total_weight_kg'             => $weightInKg,
                        'length'                      => $deliveryParam['package_data']['length'],
                        'width'                       => $deliveryParam['package_data']['width'],
                        'height'                      => $deliveryParam['package_data']['height'],
                    ],
                ];
            }
        }

        return !empty($this->rates);
    }

    protected function getPackagesData($contents): array {
        $totalWeight = 0;
        $lengthList  = [];
        $widthList   = [];
        $heightList  = [];
        foreach ($contents as $productGroup) {
            $quantity = $productGroup['quantity'];
            $weight   = $productGroup['data']->get_weight();

            $dimensions = get_option('woocommerce_dimension_unit') === 'mm' ? [
                (int) ((int) $productGroup['data']->get_length() / 10),
                (int) ((int) $productGroup['data']->get_width() / 10),
                (int) ((int) $productGroup['data']->get_height() / 10),
            ] : [
                (int) $productGroup['data']->get_length(),
                (int) $productGroup['data']->get_width(),
                (int) $productGroup['data']->get_height(),
            ];

            sort($dimensions);

            if ($quantity > 1) {
                $dimensions[0] = $quantity * $dimensions[0];

                sort($dimensions);
            }

            $lengthList[] = $dimensions[0];
            $heightList[] = $dimensions[1];
            $widthList[]  = $dimensions[2];

            $weightClass = new WeightCalc();
            $weight      = $weightClass->getWeight($weight);
            $totalWeight += $quantity * $weight;
        }

        foreach (['length', 'width', 'height'] as $dimension) {
            ${$dimension.'List'}[] = (int) $this->method->get_option("product_{$dimension}_default");
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

    protected function checkDeliveryResponse($delivery): bool {
        return !isset($delivery['errors']);
    }

    public function getRates(): array {
        return array_values($this->rates);
    }
}
