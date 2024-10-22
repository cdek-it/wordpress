<?php
declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Exceptions\CdekApiException;
    use Cdek\Exceptions\TariffNotAvailableException;
    use Cdek\Helper;
    use Cdek\Helpers\WeightCalc;
    use Cdek\MetaKeys;
    use Cdek\Model\Tariff;
    use Throwable;
    use WC_Shipping_Method;

    final class CalculateDeliveryAction
    {
        private WC_Shipping_Method $method;
        private array $rates = [];
        private CdekApi $api;

        public function __construct(int $instanceID = null)
        {
            $this->method = Helper::getActualShippingMethod($instanceID);
            $this->api    = new CdekApi;
        }

        /**
         * @throws CdekApiException
         * @throws \JsonException
         */
        public function __invoke(array $package, bool $addTariffsToOffice = true): bool
        {
            if (!$this->api->checkAuth()) {
                return false;
            }

            $officeData = json_decode($this->method->get_option('pvz_code'), true) ?: [];
            $doorData   = json_decode($this->method->get_option('address'), true) ?: [];

            $deliveryParam['from'] = [
                'postal_code'  => $officeData['postal'] ?? $doorData['postal'] ?? '',
                'city'         => $officeData['city'] ?? $doorData['city'],
                'address'      => $officeData['city'] ?? $doorData['city'],
                'country_code' => $officeData['country'] ?? $doorData['country'] ?? 'RU',
            ];

            if (!isset($deliveryParam['from']['postal_code'])) {
                return false;
            }

            $deliveryParam['to'] = [
                'postal_code'  => trim($package['destination']['postcode']),
                'city'         => trim($package['destination']['city']),
                'address'      => trim($package['destination']['city']),
                'country_code' => trim($package['destination']['country']),
            ];

            try {
                WC()->session->set(Config::DELIVERY_NAME.'_postcode', $deliveryParam['to']['postal_code']);
                WC()->session->set(Config::DELIVERY_NAME.'_city', $deliveryParam['to']['city']);
            } catch (Throwable $e) {
                // do nothing
            }

            $deliveryParam['packages'] = $this->getPackagesData($package['contents']);
            unset($deliveryParam['packages']['weight_orig_unit']);

            if ($this->method->get_option('insurance') === 'yes') {
                $deliveryParam['services'][] = [
                    'code'      => 'INSURANCE',
                    'parameter' => (int) $package['contents_cost'],
                ];
            }

            $tariffList = $this->method->get_option('tariff_list');

            $priceRules = json_decode($this->method->get_option('delivery_price_rules'), true) ?: [
                'office' => [['type' => 'percentage', 'to' => null, 'value' => 100]],
                'door'   => [['type' => 'percentage', 'to' => null, 'value' => 100]],
            ];

            foreach (['office', 'door'] as $ruleType) {
                $priceRules[$ruleType] = array_reduce($priceRules[$ruleType] ?? [],
                    static function ($carry, $item) use ($package) {
                        if ($carry !== null) {
                            return $carry;
                        }

                        if ($item['to'] >= $package['contents_cost'] || $item['to'] === null) {
                            return $item;
                        }

                        return null;
                    });
            }

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
                        !in_array((string) $tariff['tariff_code'], $tariffList ?: [], true)) {
                        continue;
                    }

                    if (!$addTariffsToOffice && Tariff::isTariffToOffice($tariff['tariff_code'])) {
                        continue;
                    }

                    $minDay = (int) $tariff['period_min'] + (int) $this->method->get_option('extra_day');
                    $maxDay = (int) $tariff['period_max'] + (int) $this->method->get_option('extra_day');
                    $cost   = (int) $tariff['delivery_sum'];

                    if ((!isset($officeData['city']) && Tariff::isTariffFromOffice($tariff['tariff_code'])) ||
                        (!isset($doorData['city']) && Tariff::isTariffFromDoor($tariff['tariff_code']))) {
                        continue;
                    }

                    $this->rates[$tariff['tariff_code']] = [
                        'id'        => sprintf('%s:%s', Config::DELIVERY_NAME, $tariff['tariff_code']),
                        'label'     => sprintf(esc_html__('CDEK: %s, (%s-%s days)', 'cdekdelivery'),
                                               Tariff::getTariffUserNameByCode($tariff['tariff_code']), $minDay,
                                               $maxDay),
                        'cost'      => max($cost, 0),
                        'meta_data' => [
                            MetaKeys::ADDRESS_HASH => sha1($deliveryParam['to']['postal_code'].
                                                           $deliveryParam['to']['city'].
                                                           $deliveryParam['to']['country_code']),
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

            $api            = $this->api;
            $deliveryMethod = $this->method;

            $this->rates    = array_map(static function ($tariff) use (
                $priceRules,
                $api,
                $deliveryParam,
                $deliveryMethod
            ) {
                $rule = Tariff::isTariffToOffice($tariff['meta_data'][MetaKeys::TARIFF_CODE]) ? $priceRules['office'] :
                    $priceRules['door'];
                if (isset($rule['type']) && $rule['type'] === 'free') {
                    $tariff['cost'] = 0;

                    return $tariff;
                }

                if (isset($rule['type']) && $rule['type'] === 'fixed') {
                    $tariff['cost'] = max(function_exists('wcml_get_woocommerce_currency_option') ?
                        apply_filters('wcml_raw_price_amount', $rule['value'], 'RUB') : $rule['value'], 0);

                    return $tariff;
                }

                $deliveryParam['tariff_code'] = $tariff['meta_data'][MetaKeys::TARIFF_CODE];
                $deliveryParam['type']        = Tariff::getTariffType($deliveryParam['tariff_code']);

                $serviceList = Helper::getServices($deliveryMethod, $deliveryParam['tariff_code']);

                if (!empty($serviceList)) {
                    $deliveryParam['services'] = array_merge($serviceList, $deliveryParam['services'] ?? []);
                }

                $tariffInfo = $api->calculateTariff($deliveryParam);

                if (empty($tariffInfo)) {
                    return $tariff;
                }

                $delivery = json_decode($tariffInfo, true);

                $cost = $delivery['total_sum'];

                if (isset($rule['type']) && $rule['type'] === 'amount') {
                    $cost += $rule['value'];
                } elseif (isset($rule['type']) && $rule['type'] === 'percentage') {
                    $cost *= $rule['value'] / 100;
                }

                if (function_exists('wcml_get_woocommerce_currency_option')) {
                    $costTMP = apply_filters('wcml_raw_price_amount', $cost, 'RUB');
                    $coef    = $costTMP / $cost;
                    $cost    /= $coef;
                }

                $tariff['cost'] = max(ceil($cost), 0);

                return $tariff;
            }, $this->rates);

            return !empty($this->rates);
        }

        private function getPackagesData(array $contents): array
        {
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

                $weight      = WeightCalc::getWeight($weight);
                $totalWeight += $quantity * $weight;
            }

            foreach (['length', 'width', 'height'] as $dimension) {
                $predefinedDimensions[] = (int) $this->method->get_option("product_{$dimension}_default");
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

            $useDefaultValue = $this->method->get_option('product_package_default_toggle') === 'yes';
            foreach (['length', 'width', 'height'] as $dimension) {
                if ($$dimension === 0 || $useDefaultValue) {
                    $$dimension = (int) $this->method->get_option("product_{$dimension}_default");
                }
            }

            return [
                'length' => $length,
                'width'  => $width,
                'height' => $height,
                'weight' => WeightCalc::getWeightInGrams($totalWeight),
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
         * @throws TariffNotAvailableException
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
