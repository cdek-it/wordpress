<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Helpers\WeightConverter;
    use Cdek\MetaKeys;
    use Cdek\Model\Service;
    use Cdek\Model\Tariff;
    use Cdek\ShippingMethod;
    use Cdek\Traits\CanBeCreated;
    use Throwable;

    class CalculateDeliveryAction
    {
        use CanBeCreated;

        private ShippingMethod $method;
        private array $rates = [];

        /**
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         */
        public function __invoke(array $package, int $instanceID, bool $addTariffsToOffice = true): array
        {
            $this->method = ShippingMethod::factory($instanceID);
            $api          = new CdekApi($instanceID);

            if (empty($this->method->city_code) || $api->authGetError() !== null) {
                return [];
            }

            $deliveryParam = [
                'from' => [
                    'code' => $this->method->city_code,
                ],
                'to'   => [
                    'postal_code'  => trim($package['destination']['postcode']),
                    'city'         => trim($package['destination']['city']),
                    'address'      => trim($package['destination']['city']),
                    'country_code' => trim($package['destination']['country']),
                ],
                'packages' => $this->getPackagesData($package['contents']),
            ];

            try {
                WC()->session->set(Config::DELIVERY_NAME.'_postcode', $deliveryParam['to']['postal_code']);
                WC()->session->set(Config::DELIVERY_NAME.'_city', $deliveryParam['to']['city']);
            } catch (Throwable $e) {
                // do nothing
            }

            if ($this->method->insurance) {
                $deliveryParam['services'][] = [
                    'code'      => 'INSURANCE',
                    'parameter' => (int)$package['contents_cost'],
                ];
            }

            $priceRules = json_decode($this->method->delivery_price_rules, true) ?: [
                'office' => [['type' => 'percentage', 'to' => null, 'value' => 100]],
                'door'   => [['type' => 'percentage', 'to' => null, 'value' => 100]],
            ];

            foreach (['office', 'door'] as $ruleType) {
                $priceRules[$ruleType] = array_reduce(
                    $priceRules[$ruleType] ?? [],
                    static function ($carry, $item) use ($package) {
                        if ($carry !== null) {
                            return $carry;
                        }

                        if ($item['to'] >= $package['contents_cost'] || $item['to'] === null) {
                            return $item;
                        }

                        return null;
                    },
                );
            }

            foreach ([Tariff::SHOP_TYPE, Tariff::DELIVERY_TYPE] as $deliveryType) {
                $deliveryParam['type'] = $deliveryType;

                $calcResult = $api->calculateList($deliveryParam);

                if (empty($calcResult)) {
                    continue;
                }

                foreach ($calcResult['tariff_codes'] as $tariff) {
                    if (isset($this->rates[$tariff['tariff_code']]) ||
                        !in_array((string)$tariff['tariff_code'], $this->method->tariff_list ?: [], true)) {
                        continue;
                    }

                    if (!$addTariffsToOffice && Tariff::isToOffice((int)$tariff['tariff_code'])) {
                        continue;
                    }

                    $minDay = (int)$tariff['period_min'] + (int)$this->method->extra_day;
                    $maxDay = (int)$tariff['period_max'] + (int)$this->method->extra_day;
                    $cost   = (int)$tariff['delivery_sum'];

                    if ($maxDay < $minDay) {
                        $maxDay = $minDay;
                    }

                    $this->rates[$tariff['tariff_code']] = [
                        'id'        => sprintf('%s:%s', Config::DELIVERY_NAME, $tariff['tariff_code']),
                        'label'     => ($minDay === $maxDay) ? sprintf(
                            esc_html__('%s, (%s day)', 'cdekdelivery'),
                            Tariff::getName($tariff['tariff_code'], $tariff['tariff_name']),
                            $minDay,
                        ) : sprintf(
                            esc_html__('%s, (%s-%s days)', 'cdekdelivery'),
                            Tariff::getName($tariff['tariff_code'], $tariff['tariff_name']),
                            $minDay,
                            $maxDay,
                        ),
                        'cost'      => max($cost, 0),
                        'meta_data' => [
                            MetaKeys::ADDRESS_HASH => sha1(
                                $deliveryParam['to']['postal_code'].
                                $deliveryParam['to']['city'].
                                $deliveryParam['to']['country_code'],
                            ),
                            MetaKeys::TARIFF_CODE  => $tariff['tariff_code'],
                            MetaKeys::TARIFF_MODE  => $tariff['delivery_mode'],
                            MetaKeys::WEIGHT       => $deliveryParam['packages']['weight'],
                            MetaKeys::LENGTH       => $deliveryParam['packages']['length'],
                            MetaKeys::WIDTH        => $deliveryParam['packages']['width'],
                            MetaKeys::HEIGHT       => $deliveryParam['packages']['height'],
                        ],
                    ];
                }
            }

            return array_map(function ($tariff) use (
                $priceRules,
                $api,
                $deliveryParam
            ) {
                $rule = Tariff::isToOffice((int)$tariff['meta_data'][MetaKeys::TARIFF_CODE]) ? $priceRules['office'] :
                    $priceRules['door'];

                if (isset($rule['type'])) {
                    if ($rule['type'] === 'free') {
                        $tariff['cost'] = 0;

                        return $tariff;
                    }

                    if ($rule['type'] === 'fixed') {
                        $tariff['cost'] = max(
                            function_exists('wcml_get_woocommerce_currency_option') ?
                                apply_filters('wcml_raw_price_amount', $rule['value'], 'RUB') : $rule['value'],
                            0,
                        );

                        return $tariff;
                    }
                }

                $deliveryParam['tariff_code'] = $tariff['meta_data'][MetaKeys::TARIFF_CODE];
                $deliveryParam['type']        = Tariff::getType((int)$deliveryParam['tariff_code']);

                $serviceList = Service::factory($this->method, $deliveryParam['tariff_code']);

                if (!empty($serviceList)) {
                    $deliveryParam['services'] = array_merge($serviceList, $deliveryParam['services'] ?? []);
                }

                $cost = $api->calculateGet($deliveryParam) ?? $tariff['cost'];

                if (isset($rule['type'])) {
                    if ($rule['type'] === 'amount') {
                        $cost += $rule['value'];
                    } elseif ($rule['type'] === 'percentage') {
                        $cost *= $rule['value'] / 100;
                    }
                }

                if (function_exists('wcml_get_woocommerce_currency_option')) {
                    $cost /= apply_filters('wcml_raw_price_amount', $cost, 'RUB') / $cost;
                }

                $tariff['cost'] = max(ceil($cost), 0);

                return $tariff;
            }, $this->rates);
        }

        private function getPackagesData(array $contents): array
        {
            $totalWeight = 0;
            $lengthList  = [];
            $widthList   = [];
            $heightList  = [];

            $dimensionsInMM = get_option('woocommerce_dimension_unit') === 'mm';

            foreach ($contents as $productGroup) {
                $quantity = $productGroup['quantity'];
                $weight   = $productGroup['data']->get_weight();

                $dimensions = $dimensionsInMM ? [
                    (int)((int)$productGroup['data']->get_length() / 10),
                    (int)((int)$productGroup['data']->get_width() / 10),
                    (int)((int)$productGroup['data']->get_height() / 10),
                ] : [
                    (int)$productGroup['data']->get_length(),
                    (int)$productGroup['data']->get_width(),
                    (int)$productGroup['data']->get_height(),
                ];

                sort($dimensions);

                if ($quantity > 1) {
                    $dimensions[0] = $quantity * $dimensions[0];

                    sort($dimensions);
                }

                $lengthList[] = $dimensions[0];
                $heightList[] = $dimensions[1];
                $widthList[]  = $dimensions[2];

                $weight      = WeightConverter::applyFallback($weight);
                $totalWeight += $quantity * $weight;
            }

            foreach (['length', 'width', 'height'] as $dimension) {
                $predefinedDimensions[] = (int)$this->method->get_option("product_{$dimension}_default");
            }

            sort($predefinedDimensions);
            $lengthList[] = $predefinedDimensions[0];
            $heightList[] = $predefinedDimensions[1];
            $widthList[]  = $predefinedDimensions[2];

            rsort($lengthList);
            rsort($widthList);
            rsort($heightList);

            $length = $lengthList[0];
            $width  = $widthList[0];
            $height = $heightList[0];

            $useDefaultValue = $this->method->product_package_default_toggle;
            foreach (['length', 'width', 'height'] as $dimension) {
                if ($$dimension === 0 || $useDefaultValue) {
                    $$dimension = (int)$this->method->get_option("product_{$dimension}_default");
                }
            }

            return [
                'length' => $length,
                'width'  => $width,
                'height' => $height,
                'weight' => WeightConverter::getWeightInGrams($totalWeight),
            ];
        }
    }
}
