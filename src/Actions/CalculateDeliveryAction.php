<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Exceptions\External\ApiException;
    use Cdek\Exceptions\External\LegacyAuthException;
    use Cdek\Helpers\Logger;
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
         * @throws ApiException
         * @throws LegacyAuthException
         */
        public function __invoke(array $package, ShippingMethod $method, bool $addTariffsToOffice = true): array
        {
            $this->method = $method;
            $api          = new CdekApi($this->method);

            if (empty($this->method->city_code)) {
                Logger::debug("Calculate: empty city code");

                return [];
            }

            if ($api->authGetError() !== null) {
                Logger::warning("Calculate: auth error");

                return [];
            }

            $deliveryParam = [
                'from'     => [
                    'code' => $this->method->city_code,
                ],
                'to'       => [
                    'postal_code'  => trim($package['destination']['postcode']),
                    'city'         => trim($package['destination']['city']),
                    'address'      => trim($package['destination']['city']),
                    'country_code' => trim($package['destination']['country']),
                ],
                'packages' => $this->getPackagesData($package['contents']),
            ];

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

            Logger::debug("Calculate: delivery params before calculate list", $deliveryParam);

            foreach ([Tariff::SHOP_TYPE, Tariff::DELIVERY_TYPE] as $deliveryType) {
                $deliveryParam['type'] = $deliveryType;

                $calcResult = $api->calculateList($deliveryParam);

                Logger::debug("Calculate: calculate list result", $calcResult);

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
                            /* translators: 1: Tariff name 2: Delivery period in days */
                            esc_html__('%1$s, (%2$s day)', 'cdekdelivery'),
                            Tariff::getName($tariff['tariff_code'], $tariff['tariff_name']),
                            $minDay,
                        ) : sprintf(
                            /* translators: 1: Tariff name 2: Minimum delivery days 3: Maximum delivery days */
                            esc_html__('%1$s, (%2$s-%3$s days)', 'cdekdelivery'),
                            Tariff::getName($tariff['tariff_code'], $tariff['tariff_name']),
                            $minDay,
                            $maxDay,
                        ),
                        'cost'      => max($cost, 0),
                        'meta_data' => [
                            MetaKeys::ADDRESS_HASH => sha1(
                                $deliveryParam['to']['postal_code'] .
                                $deliveryParam['to']['city'] .
                                $deliveryParam['to']['country_code'],
                            ),
                            MetaKeys::POSTAL       => $deliveryParam['to']['postal_code'],
                            MetaKeys::CITY         => $deliveryParam['to']['city'],
                            MetaKeys::TARIFF_CODE  => $tariff['tariff_code'],
                            MetaKeys::TARIFF_MODE  => $tariff['delivery_mode'],
                            MetaKeys::WEIGHT       => $deliveryParam['packages']['weight'],
                            MetaKeys::LENGTH       => $deliveryParam['packages']['length'],
                            MetaKeys::WIDTH        => $deliveryParam['packages']['width'],
                            MetaKeys::HEIGHT       => $deliveryParam['packages']['height'],
                            MetaKeys::OFFICE_CODE  => $package['destination'][MetaKeys::OFFICE_CODE] ?? null,
                        ],
                    ];
                }
            }

            Logger::debug("Calculate: rates", $this->rates);

            return array_map(
                function ($tariff) use (
                    $priceRules,
                    $api,
                    $deliveryParam
                ) {
                    $rule = Tariff::isToOffice((int)$tariff['meta_data'][MetaKeys::TARIFF_CODE]) ?
                        $priceRules['office'] : $priceRules['door'];

                    if (isset($rule['type'])) {
                        if ($rule['type'] === 'free') {
                            $tariff['cost'] = 0;

                            Logger::debug("Calculate: type free", $tariff);

                            return $tariff;
                        }

                        if ($rule['type'] === 'fixed') {
                            $tariff['cost'] = max(
                                function_exists('wcml_get_woocommerce_currency_option') ?
                                    apply_filters('wcml_raw_price_amount', $rule['value'], 'RUB') : $rule['value'],
                                0,
                            );

                            Logger::debug("Calculate: type fixed", $tariff);

                            return $tariff;
                        }
                    }

                    $deliveryParam['tariff_code'] = $tariff['meta_data'][MetaKeys::TARIFF_CODE];
                    $deliveryParam['type']        = Tariff::getType((int)$deliveryParam['tariff_code']);

                    $serviceList = Service::factory($this->method, $deliveryParam['tariff_code']);

                    if (!empty($serviceList)) {
                        $deliveryParam['services'] = array_merge($serviceList, $deliveryParam['services'] ?? []);
                    }

                    Logger::debug(
                        "Calculate: delivery params before tariff calculate",
                        $deliveryParam,
                    );

                    if ($cost = $api->calculateGet($deliveryParam)) {
                        Logger::debug("Calculate: Got total for tariff {$deliveryParam['tariff_code']}: $cost");
                    } else {
                        Logger::debug(
                            "Calculate: Got tariff cost total for tariff {$deliveryParam['tariff_code']}: {$tariff['cost']}",
                        );

                        $cost = $tariff['cost'];
                    }

                    if (isset($rule['type']) && is_numeric($rule['value'])) {
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

                    Logger::debug("Calculate: tariff", $tariff);

                    return $tariff;
                },
                $this->rates,
            );
        }

        private function getPackagesData(array $contents): array
        {
            $totalWeight = 0;
            $totalVolume = 0;
            $lengthList  = [];
            $widthList   = [];

            $dimensionsInMM  = get_option('woocommerce_dimension_unit') === 'mm';
            $useDefaultValue = $this->method->product_package_default_toggle;

            if ($useDefaultValue) {
                foreach (['length', 'width', 'height'] as $dimension) {
                    $forcedDimensions[] = (int)$this->method->get_option("product_{$dimension}_default");
                }

                sort($forcedDimensions);
            }

            foreach ($contents as $productGroup) {
                $quantity = $productGroup['quantity'];
                $weight   = $productGroup['data']->get_weight();

                if ($useDefaultValue) {
                    $dimensions = $forcedDimensions;
                } elseif ($dimensionsInMM) {
                    $dimensions = [
                        (int)((int)$productGroup['data']->get_length() / 10),
                        (int)((int)$productGroup['data']->get_width() / 10),
                        (int)((int)$productGroup['data']->get_height() / 10),
                    ];
                } else {
                    $dimensions = [
                        (int)$productGroup['data']->get_length(),
                        (int)$productGroup['data']->get_width(),
                        (int)$productGroup['data']->get_height(),
                    ];
                }

                sort($dimensions);

                if ($quantity > 1) {
                    $dimensions[0] = $quantity * $dimensions[0];

                    sort($dimensions);
                }

                $lengthList[] = $dimensions[0];
                $widthList[]  = $dimensions[2];

                // Произведение трёх граней после поправки на quantity уже равно
                // quantity * объём одной штуки - отдельно домножать на quantity не нужно.
                $totalVolume += $dimensions[0] * $dimensions[1] * $dimensions[2];

                $weight      = WeightConverter::applyFallback($weight);
                $totalWeight += $quantity * $weight;
            }

            // Дефолтные габариты из настроек не должны конкурировать с реальными размерами товаров.
            // Если пустой $contents, то не обращаться к несуществующему $lengthList[0]/$widthList[0]
            if (empty($lengthList)) {
                if (!$useDefaultValue) {
                    foreach (['length', 'width', 'height'] as $dimension) {
                        $forcedDimensions[] = (int)$this->method->get_option("product_{$dimension}_default");
                    }

                    sort($forcedDimensions);
                }

                $lengthList[] = $forcedDimensions[0];
                $widthList[]  = $forcedDimensions[2];
            }

            rsort($lengthList);
            rsort($widthList);

            $length = $lengthList[0];
            $width  = $widthList[0];

            // Высоту не выбираем по рангу среди позиций (это и занижало объём при 3+ разных
            // товарах), а досчитываем от суммарного объёма всех позиций заказа, чтобы
            // итоговый объём грузоместа не мог оказаться меньше суммы объёмов товаров.
            // k - коэффициент запаса на свободное пространство в упаковке (admin-настройка).
            $volumeRatio = (float)str_replace(
                ',',
                '.',
                $this->method->get_option('product_package_volume_ratio'),
            );

            $height = ($length > 0 && $width > 0)
                ? (int)ceil($totalVolume / ($length * $width) * $volumeRatio)
                : 0;

            if ($length === 0) {
                $length = (int)$this->method->get_option('product_length_default');
            }

            if ($width === 0) {
                $width = (int)$this->method->get_option('product_width_default');
            }

            if ($height === 0) {
                $height = (int)$this->method->get_option('product_height_default');
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
