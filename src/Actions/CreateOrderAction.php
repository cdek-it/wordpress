<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Helper;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\Helpers\StringHelper;
    use Cdek\Helpers\WeightCalc;
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\Tariff;
    use Throwable;
    use WC_Order;

    class CreateOrderAction {
        private CdekApi $api;

        /**
         * @throws \Cdek\Exceptions\RestApiInvalidRequestException|\Throwable|\JsonException
         */
        public function __invoke(int $orderId, int $attempt = 0, array $packages = null): array {
            $this->api                    = new CdekApi;
            $order                        = wc_get_order($orderId);
            $postOrderData                = OrderMetaData::getMetaByOrderId($orderId);
            $postOrderData['tariff_code'] = CheckoutHelper::getOrderShippingMethod($order)->get_meta('tariff_code') ?:
                $postOrderData['tariff_id'];
            $postOrderData['type']        = Tariff::getTariffType($postOrderData['tariff_code']);

            OrderMetaData::updateMetaByOrderId($orderId, $postOrderData);

            $param             = $this->buildRequestData($order);
            $param['packages'] = $this->buildPackagesData($order, $packages);

            try {
                $orderData = $this->api->createOrder($param);

                sleep(1);

                $cdekNumber                    = $this->getCdekOrderNumber($orderData['entity']['uuid']);
                $postOrderData['order_number'] = $cdekNumber ?? $orderData['entity']['uuid'];
                $postOrderData['order_uuid']   = $orderData['entity']['uuid'];
                OrderMetaData::updateMetaByOrderId($orderId, $postOrderData);

                return [
                    'state' => true,
                    'code'  => $cdekNumber,
                    'door'  => Tariff::isTariffFromDoor($postOrderData['tariff_code']),
                ];
            } catch (Throwable $e) {
                if ($attempt < 1 || $attempt > 5) {
                    throw $e;
                }

                wp_schedule_single_event(time() + 60, Config::ORDER_AUTOMATION_HOOK_NAME, [$orderId, $attempt + 1]);

                return [
                    'state'   => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        private function buildRequestData(WC_Order $order): array {
            $postOrderData  = OrderMetaData::getMetaByOrderId($order->get_id());
            $deliveryMethod = Helper::getActualShippingMethod(CheckoutHelper::getOrderShippingMethod($order)
                                                                            ->get_data()['instance_id']);

            $param = [
                'type'            => $postOrderData['type'],
                'tariff_code'     => $postOrderData['tariff_code'],
                'date_invoice'    => date('Y-m-d'),
                'number'          => $order->get_id(),
                'shipper_name'    => $deliveryMethod->get_option('shipper_name'),
                'shipper_address' => $deliveryMethod->get_option('shipper_address'),
                'sender'          => [
                    'passport_series'        => $deliveryMethod->get_option('passport_series'),
                    'passport_number'        => $deliveryMethod->get_option('passport_number'),
                    'passport_date_of_issue' => $deliveryMethod->get_option('passport_date_of_issue'),
                    'passport_organization'  => $deliveryMethod->get_option('passport_organization'),
                    'passport_date_of_birth' => $deliveryMethod->get_option('passport_date_of_birth'),
                    'tin'                    => $deliveryMethod->get_option('tin'),
                    'name'                   => $deliveryMethod->get_option('seller_name'),
                    'company'                => $deliveryMethod->get_option('seller_company'),
                    'email'                  => $deliveryMethod->get_option('seller_email'),
                    'phones'                 => [
                        'number' => $deliveryMethod->get_option('seller_phone'),
                    ],
                ],
                'seller'          => [
                    'address' => $deliveryMethod->get_option('seller_address'),
                    'phones'  => [
                        'number' => $deliveryMethod->get_option('seller_phone'),
                    ],
                ],
                'recipient'       => [
                    'name'   => $order->get_shipping_first_name()
                                ?:
                                $order->get_billing_first_name().' '.$order->get_shipping_last_name()
                                ?:
                                $order->get_billing_last_name(),
                    'email'  => $order->get_billing_email(),
                    'phones' => [
                        'number' => $order->get_shipping_phone() ?: $order->get_billing_phone(),
                    ],
                ],
            ];

            if (Tariff::isTariffToOffice($postOrderData['tariff_code'])) {
                $param['delivery_point'] = $postOrderData['pvz_code'];
            } else {
                $param['to_location'] = [
                    'city'         => $order->get_shipping_city(),
                    'postal_code'  => $order->get_shipping_postcode(),
                    'country_code' => $order->get_shipping_country() ?? 'RU',
                    'address'      => $order->get_shipping_address_1(),
                ];
            }

            if (Tariff::isTariffFromOffice($param['tariff_code'])) {
                $office                  = json_decode($deliveryMethod->get_option('pvz_code'), true);
                $param['shipment_point'] = $office['address'];
            } else {
                $address = json_decode($deliveryMethod->get_option('address'), true);

                $param['from_location'] = [
                    'postal_code'  => $address['postal'] ?? null,
                    'city'         => $address['city'],
                    'address'      => $address['city'],
                    'country_code' => $address['country'] ?? 'RU',
                ];
            }

            if ($deliveryMethod->get_option('international_mode') === 'yes') {
                $param['recipient'] = array_merge($param['recipient'], [
                    'passport_date_of_birth' => $order->get_meta('_passport_date_of_birth'),
                    'tin'                    => $order->get_meta('_tin'),
                    'passport_organization'  => $order->get_meta('_passport_organization'),
                    'passport_date_of_issue' => $order->get_meta('_passport_date_of_issue'),
                    'passport_number'        => $order->get_meta('_passport_number'),
                    'passport_series'        => $order->get_meta('_passport_series'),
                ]);
            }

            if ($order->get_payment_method() === 'cod') {
                $codPriceThreshold = (int) $deliveryMethod->get_option('stepcodprice');
                $total             = number_format($order->get_subtotal(), wc_get_price_decimals(), '.', '');
                if ($codPriceThreshold === 0 || $codPriceThreshold > $total) {
                    $param['delivery_recipient_cost'] = [
                        'value' => $order->get_shipping_total(),
                    ];
                }
            }

            return $param;
        }

        private function buildPackagesData(WC_Order $order, array $packages = null): array {
            $items = $order->get_items();

            if ($packages === null) {
                $deliveryMethod = CheckoutHelper::getOrderShippingMethod($order);

                return [
                    $this->buildItemsData($order, $deliveryMethod->get_meta('length'),
                        $deliveryMethod->get_meta('width'), $deliveryMethod->get_meta('height'), $items),
                ];
            }

            $output = [];

            foreach ($packages as $package) {
                $output[] = $this->buildItemsData($order, $package['length'], $package['width'], $package['height'],
                    $package['items'] ?? $items);
            }

            return $output;
        }

        private function buildItemsData(
            WC_Order $order,
            int $length,
            int $width,
            int $height,
            array $items
        ): array {
            $postOrderData  = OrderMetaData::getMetaByOrderId($order->get_id());
            $deliveryMethod = Helper::getActualShippingMethod(CheckoutHelper::getOrderShippingMethod($order)
                                                                            ->get_data()['instance_id']);

            $totalWeight = 0;
            $itemsData   = [];

            foreach ($items as $item) {
                $product     = $item->get_product();
                $weight      = WeightCalc::getWeightInGrams($product->get_weight());
                $quantity    = (int) $item->get_quantity();
                $totalWeight += $quantity * $weight;
                $cost        = $product->get_price();

                if ($postOrderData['currency'] !== 'RUB' && function_exists('wcml_get_woocommerce_currency_option')) {
                    $cost = $this->convertCurrencyToRub($cost, $postOrderData['currency']);
                }

                $selectedPaymentMethodId = $order->get_payment_method();
                $percentCod              = (int) $deliveryMethod->get_option('percentcod');
                if ($selectedPaymentMethodId === 'cod') {
                    if ($percentCod !== 0) {
                        $paymentValue = (int) (($percentCod / 100) * $cost);
                    } else {
                        $paymentValue = $cost;
                    }
                } else {
                    $paymentValue = 0;
                }

                $itemsData[] = [
                    'ware_key'     => $product->get_id(),
                    'payment'      => ['value' => $paymentValue],
                    'name'         => $product->get_name(),
                    'cost'         => $cost,
                    'amount'       => $item->get_quantity(),
                    'weight'       => $weight,
                    'weight_gross' => $weight + 1,
                ];
            }

            $package = [
                'number'  => sprintf('%s_%s', $order->get_id(), StringHelper::generateRandom(5)),
                'length'  => $length,
                'width'   => $width,
                'height'  => $height,
                'weight'  => $totalWeight,
                'comment' => 'приложена опись',
            ];

            if ($postOrderData['type'] === Tariff::SHOP_TYPE) {
                $package['items'] = $itemsData;
            }

            return $package;
        }

        private function convertCurrencyToRub(float $cost, string $currency): float {
            global $woocommerce_wpml;

            $multiCurrency = $woocommerce_wpml->get_multi_currency();
            $rates         = $multiCurrency->get_exchange_rates();

            if (!array_key_exists('RUB', $rates)) {
                return $cost;
            }

            $defaultCurrency = '';
            foreach ($rates as $key => $rate) {
                if ($rate === 1) {
                    $defaultCurrency = $key;
                    break;
                }
            }

            if ($currency === $defaultCurrency) {
                $cost = round($cost * (float) $rates['RUB'], 2);
            } else {
                $costConvertToDefault = round($cost / (float) $rates[$currency], 2);
                $cost                 = round($costConvertToDefault * (float) $rates['RUB'], 2);
            }

            return $cost;
        }

        private function getCdekOrderNumber(string $orderUuid, int $iteration = 1): ?string {
            sleep(1);

            if ($iteration === 5) {
                return null;
            }

            $orderInfoJson = $this->api->getOrder($orderUuid);
            $orderInfo     = json_decode($orderInfoJson, true);

            return $orderInfo['entity']['cdek_number'] ?? $this->getCdekOrderNumber($orderUuid, $iteration + 1);
        }
    }
}
