<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Exceptions\TariffNotAvailableException;
    use Cdek\Helper;
    use Cdek\Model\Tariff;
    use WC_Shipping_Method;

    class DeliveryCalc
    {
        private WC_Shipping_Method $method;
        private array $rates = [];
        private CdekApi $api;

        public function __construct(int $instanceID = null)
        {
            $this->method = Helper::getActualShippingMethod($instanceID);
            $this->api = new CdekApi;
        }

        final public function calculate(array $package, bool $addTariffsToOffice = true): bool
        {
            if (!$this->api->checkAuth()) {
                return false;
            }

            $officeData = json_decode($this->method->get_option('pvz_code'), true);
            $doorData = json_decode($this->method->get_option('address'), true);

            $deliveryParam['from'] = [
                'postal_code'  => $officeData['postal'] ?? $doorData['postal'],
                'city'         => $officeData['city'] ?? $doorData['city'],
                'country_code' => $officeData['country'] ?? $doorData['country'] ?? 'RU',
            ];

            if (!isset($deliveryParam['from']['postal_code'])) {
                return false;
            }

            $deliveryParam['to'] = [
                'postal_code'  => $package['destination']['postcode'],
                'city'         => $package['destination']['city'],
                'country_code' => $package['destination']['country'],
            ];
            $deliveryParam['packages'] = $this->getPackagesData($package['contents']);
            $weightOrigUnit = $deliveryParam['packages']['weight_orig_unit'];
            unset($deliveryParam['packages']['weight_orig_unit']);

            if ($this->method->get_option('insurance') === 'yes') {
                $deliveryParam['selected_services'][0] = [
                    'code'      => 'INSURANCE',
                    'parameter' => (int)$package['cart_subtotal'],
                ];
            }

            $tariffList = $this->method->get_option('tariff_list');

            foreach ([Tariff::SHOP_TYPE, Tariff::DELIVERY_TYPE] as $deliveryType) {
                $deliveryParam['type'] = $deliveryType;

                $calcResult = $this->api->calculateTariffList($deliveryParam);

                if (empty($calcResult)) {
                    continue;
                }

                $delivery = json_decode($calcResult, true);

                if (!$this->checkDeliveryResponse($delivery)) {
                    continue;
                }

                foreach ($delivery['tariff_codes'] as $tariff) {
                    if (isset($this->rates[$tariff['tariff_code']]) ||
                        !in_array((string)$tariff['tariff_code'], $tariffList ?: [], true)) {
                        continue;
                    }

                    if (!$addTariffsToOffice && Tariff::isTariffToOffice($tariff['tariff_code'])) {
                        continue;
                    }

                    $minDay = (int)$tariff['period_min'] + (int)$this->method->get_option('extra_day');
                    $maxDay = (int)$tariff['period_max'] + (int)$this->method->get_option('extra_day');
                    $cost = (int)$tariff['delivery_sum'];

                    $measurement = get_option('woocommerce_weight_unit');
                    $this->rates[$tariff['tariff_code']] = [
                        'id'        => sprintf('%s_%s', Config::DELIVERY_NAME, $tariff['tariff_code']),
                        'label'     => sprintf("CDEK: %s, (%s-%s дней)",
                                               Tariff::getTariffUserNameByCode($tariff['tariff_code']),
                                               $minDay,
                                               $maxDay),
                        'cost'      => $cost,
                        'meta_data' => [
                            Config::ADDRESS_HASH_META_KEY => sha1($deliveryParam['to']['postal_code'] .
                                                                  $deliveryParam['to']['city'] .
                                                                  $deliveryParam['to']['country_code']),
                            'tariff_code'                 => $tariff['tariff_code'],
                            "weight ($measurement)"       => $weightOrigUnit,
                            'tariff_type'                 => $deliveryType,
                            'length'                      => $deliveryParam['packages']['length'],
                            'width'                       => $deliveryParam['packages']['width'],
                            'height'                      => $deliveryParam['packages']['height'],
                        ],
                    ];
                }
            }

            $fixedPrice =
                ($this->method->get_option('fixprice_toggle') ===
                 'yes') ? (int)$this->method->get_option('fixprice') : null;

            if (($this->method->get_option('stepprice_toggle') === 'yes') &&
                (int)$package['cart_subtotal'] > (int)$this->method->get_option('stepprice')) {
                $fixedPrice = 0;
            }

            $extraCost = (int)$this->method->get_option('extra_cost');

            $percentPrice =
                $this->method->get_option('percentprice_toggle') ===
                'yes' ? ($this->method->get_option('percentprice') / 100) : null;

            $api = $this->api;
            $this->rates = array_map(static function ($tariff) use (
                $fixedPrice,
                $extraCost,
                $api,
                $deliveryParam,
                $percentPrice
            ) {
                if ($fixedPrice !== null) {
                    $tariff['cost'] = $fixedPrice;

                    return $tariff;
                }

                $deliveryParam['type'] = $tariff['meta_data']['tariff_type'];
                $deliveryParam['tariff_code'] = $tariff['meta_data']['tariff_code'];

                $tariffInfo = $api->calculateTariff($deliveryParam);

                if (empty($tariffInfo)) {
                    return $tariff;
                }

                $delivery = json_decode($tariffInfo, true);

                $cost = $delivery['total_sum'];

                $cost += $extraCost;

                if ($percentPrice !== null) {
                    $cost *= $percentPrice;
                }

                if (function_exists('wcml_get_woocommerce_currency_option')) {
                    $costTMP = apply_filters('wcml_raw_price_amount', $cost, 'RUB');
                    $coef = $costTMP / $cost;
                    $cost /= $coef;
                }

                $tariff['cost'] = ceil($cost);

                return $tariff;
            }, $this->rates);

            return !empty($this->rates);
        }

        private function getPackagesData(array $contents): array
        {
            $totalWeight = 0;
            $lengthList = [];
            $widthList = [];
            $heightList = [];
            $weightClass = new WeightCalc();
            foreach ($contents as $productGroup) {
                $quantity = $productGroup['quantity'];
                $weight = $productGroup['data']->get_weight();

                $dimensions = get_option('woocommerce_dimension_unit') === 'mm' ? [
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
                $widthList[] = $dimensions[2];

                $weight = $weightClass->getWeight($weight);
                $totalWeight += $quantity * $weight;
            }

            foreach (['length', 'width', 'height'] as $dimension) {
                ${$dimension . 'List'}[] = (int)$this->method->get_option("product_{$dimension}_default");
            }

            rsort($lengthList);
            rsort($widthList);
            rsort($heightList);

            $length = $lengthList[0];
            $width = $widthList[0];
            $height = $heightList[0];

            $useDefaultValue = $this->method->get_option('product_package_default_toggle') === 'yes';
            foreach (['length', 'width', 'height'] as $dimension) {
                if ($$dimension === 0 || $useDefaultValue) {
                    $$dimension = (int)$this->method->get_option("product_{$dimension}_default");
                }
            }

            return [
                'length'           => $length,
                'width'            => $width,
                'height'           => $height,
                'weight'           => $weightClass->getWeightInGrams($totalWeight),
                'weight_orig_unit' => $totalWeight,
            ];
        }

        private function checkDeliveryResponse(array $delivery): bool
        {
            return !isset($delivery['errors']);
        }

        final public function getRates(): array
        {
            return array_values($this->rates);
        }

        /**
         * @throws \Cdek\Exceptions\TariffNotAvailableException
         */
        final public function getTariffRate(int $code): array
        {
            if (!isset($this->rates[$code])) {
                throw new TariffNotAvailableException(array_keys($this->rates));
            }

            return $this->rates[$code];
        }
    }
}
