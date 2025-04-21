<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\CoreApi;
    use Cdek\Exceptions\CacheException;
    use Cdek\Exceptions\External\ApiException;
    use Cdek\Exceptions\External\CoreAuthException;
    use Cdek\Exceptions\External\HttpClientException;
    use Cdek\Exceptions\External\HttpServerException;
    use Cdek\Exceptions\External\InvalidRequestException;
    use Cdek\Exceptions\External\LegacyAuthException;
    use Cdek\Exceptions\InvalidPhoneException;
    use Cdek\Exceptions\ShippingNotFoundException;
    use Cdek\Helpers\Logger;
    use Cdek\Helpers\StringHelper;
    use Cdek\Helpers\WeightConverter;
    use Cdek\MetaKeys;
    use Cdek\Model\Order;
    use Cdek\Model\Service;
    use Cdek\Model\ShippingItem;
    use Cdek\Model\Tariff;
    use Cdek\Model\Tax;
    use Cdek\Model\ValidationResult;
    use Cdek\Note;
    use Cdek\Traits\CanBeCreated;
    use Cdek\Validator\PhoneValidator;
    use Throwable;
    use WC_Order_Item_Product;

    class OrderCreateAction
    {
        use CanBeCreated;

        private int $tariff;
        private CdekApi $api;
        private ShippingItem $shipping;
        private Order $order;

        /**
         * @throws \Cdek\Exceptions\CacheException
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\CoreAuthException
         * @throws \Cdek\Exceptions\ShippingNotFoundException
         * @throws \Cdek\Exceptions\OrderNotFoundException
         */
        public function __invoke(int $orderId, int $attempt = 0, ?array $packages = null): ValidationResult
        {
            $this->api   = new CdekApi;
            $this->order = new Order($orderId);

            $shipping = $this->order->getShipping();

            if ($shipping === null) {
                throw new ShippingNotFoundException;
            }

            $this->shipping = $shipping;
            $this->tariff   = (int)($shipping->tariff ?: $this->order->tariff_id);

            try {
                $coreApi = new CoreApi;

                $existingOrder = $coreApi->orderGet($orderId);

                $this->order->number = $existingOrder['track'];
                $this->order->save();

                return new ValidationResult(true);
            } catch (CoreAuthException|ApiException|CacheException $e) {
                //Do nothing
            }

            try {
                $track = $this->createOrder($this->buildPackagesData($packages));

                if (!empty($track)) {
                    if ($attempt > 0) {
                        Note::send(
                            $this->order->id,
                            sprintf(
                                esc_html__(/* translators: 1: attempt number */
                                    'Order created automatically after %1$s attempts',
                                    'cdekdelivery',
                                ),
                                $attempt,
                            ),
                        );
                    }

                    Note::send(
                        $this->order->id,
                        sprintf(
                            esc_html__(/* translators: 1: tracking number */ 'Tracking number: %1$s',
                                'cdekdelivery',
                            ),
                            $track,
                        ),
                        true,
                    );
                }

                return new ValidationResult(true);
            } catch (InvalidPhoneException $e) {
                Note::send(
                    $this->order->id,
                    sprintf(
                        esc_html__(/* translators: 1: error message */ 'Cdek shipping error: %1$s', 'cdekdelivery'),
                        $e->getMessage(),
                    ),
                );

                return new ValidationResult(false, $e->getMessage());
            } catch (Throwable $e) {
                Logger::warning(
                    "Error while order create",
                    $e,
                );

                if ($attempt < 1 || $attempt > 5) {
                    throw $e;
                }

                wp_schedule_single_event(time() + 60 * 5, Config::ORDER_AUTOMATION_HOOK_NAME, [$orderId, $attempt + 1]);

                return new ValidationResult(false, $e->getMessage());
            }
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         * @throws HttpClientException
         * @throws HttpServerException
         * @throws InvalidPhoneException
         * @throws InvalidRequestException
         */
        private function createOrder(array $packages): ?string
        {
            $param             = $this->buildRequestData();
            $param['packages'] = $packages;

            $uuid = $this->api->orderCreate($param);

            sleep(5);

            $track = $this->getTrack($uuid);

            $this->order->number = $track;
            $this->order->save();

            return $track;
        }

        /**
         * @throws InvalidPhoneException
         */
        private function buildRequestData(): array
        {
            $countryCode     = $this->order->country ?: 'RU';
            $recipientNumber = $this->order->phone ?: '';

            try {
                $recipientNumber = PhoneValidator::new()($recipientNumber, $countryCode);
            } catch (CoreAuthException|ApiException|CacheException $e) {
                Logger::warning(
                    'Phone validation fail',
                    $e,
                );
            }

            $deliveryMethod = $this->shipping->getMethod();

            $param = [
                'type'            => Tariff::getType($this->tariff),
                'tariff_code'     => $this->tariff,
                'date_invoice'    => gmdate('Y-m-d'),
                'number'          => $this->order->id,
                'shipper_name'    => $deliveryMethod->shipper_name,
                'shipper_address' => $deliveryMethod->shipper_address,
                'sender'          => [
                    'passport_series'        => $deliveryMethod->passport_series,
                    'passport_number'        => $deliveryMethod->passport_number,
                    'passport_date_of_issue' => $deliveryMethod->passport_date_of_issue,
                    'passport_organization'  => $deliveryMethod->passport_organization,
                    'passport_date_of_birth' => $deliveryMethod->passport_date_of_birth,
                    'tin'                    => $deliveryMethod->tin,
                    'name'                   => $deliveryMethod->seller_name,
                    'company'                => $deliveryMethod->seller_company,
                    'email'                  => $deliveryMethod->seller_email,
                    'phones'                 => [
                        'number' => $deliveryMethod->seller_phone,
                    ],
                ],
                'seller'          => [
                    'address' => $deliveryMethod->seller_address,
                    'phones'  => [
                        'number' => $deliveryMethod->seller_phone,
                    ],
                ],
                'recipient'       => [
                    'name'   => "{$this->order->first_name} {$this->order->last_name}",
                    'email'  => $this->order->billing_email,
                    'phones' => [
                        'number' => $recipientNumber,
                    ],
                ],
                'from_location'   => [
                    'code'    => $deliveryMethod->city_code,
                    'address' => $deliveryMethod->address,
                ],
                'developer_key'   => Config::DEV_KEY,
            ];

            if (Tariff::isToOffice($this->tariff)) {
                $param['delivery_point'] = $this->shipping->office ?: $this->order->pvz_code ?: null;
            } else {
                $param['to_location'] = [
                    'city'         => $this->order->city,
                    'postal_code'  => $this->order->postcode,
                    'country_code' => $countryCode,
                    'address'      => $this->order->address_1,
                ];
            }

            if ($deliveryMethod->international_mode) {
                $param['recipient'] = array_merge($param['recipient'], [
                    'passport_date_of_birth' => $this->order->meta('_passport_date_of_birth'),
                    'tin'                    => $this->order->meta('_tin'),
                    'passport_organization'  => $this->order->meta('_passport_organization'),
                    'passport_date_of_issue' => $this->order->meta('_passport_date_of_issue'),
                    'passport_number'        => $this->order->meta('_passport_number'),
                    'passport_series'        => $this->order->meta('_passport_series'),
                ]);
            }

            $serviceList = Service::factory($deliveryMethod, $param['tariff_code']);
            if (!empty($serviceList)) {
                $param['services'] = $serviceList;
            }

            if ($this->order->shipping_total > 0 && $this->order->shouldBePaidUponDelivery()) {
                $param['delivery_recipient_cost'] = [
                    'value' => $this->order->shipping_total,
                ];
            }

            return $param;
        }

        /**
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         * @throws \Cdek\Exceptions\External\ApiException
         */
        private function getTrack(string $uuid, int $iteration = 1): ?string
        {
            sleep(1);

            if ($iteration === 5) {
                return null;
            }

            return $this->api->orderGet($uuid)->entity()['cdek_number'] ?? $this->getTrack($uuid, $iteration + 1);
        }

        private function buildPackagesData(?array $packages = null): array
        {
            $packages = $packages ?: [
                [
                    'length' => (int)$this->shipping->length,
                    'width'  => (int)$this->shipping->width,
                    'height' => (int)$this->shipping->height,
                    'items'  => null,
                ],
            ];

            $orderItems = $this->order->getItems();

            $shouldPay     = $this->order->shouldBePaidUponDelivery() ? (int)$this->shipping->getMethod()->percentcod :
                null;
            $shouldConvert = $this->order->currency !== 'RUB' &&
                             function_exists('wcml_get_woocommerce_currency_option') ? $this->order->currency : null;

            return array_map(function (array $p) use ($shouldConvert, $orderItems, $shouldPay) {
                $weight = 0;

                $items = array_values(
                    array_filter(
                        array_map(
                            function($item) use ($shouldConvert, $shouldPay, $orderItems, &$weight){
                                if ($item instanceof WC_Order_Item_Product) {
                                    $qty = (int)$item->get_quantity();
                                } else {
                                    $qty  = (int)$item['qty'];
                                    $item = $orderItems[$item['id']] ?? null;
                                }

                                if ($item === null) {
                                    return null;
                                }

                                assert($item instanceof WC_Order_Item_Product);

                                return $this->buildItemData($item, $qty, $shouldConvert, $shouldPay, $weight);
                            },
                            $p['items'] ?: $orderItems
                        )
                    )
                );

                $package = [
                    'number'  => sprintf('%s_%s', $this->order->id, StringHelper::generateRandom(5)),
                    'length'  => $p['length'],
                    'width'   => $p['width'],
                    'height'  => $p['height'],
                    'weight'  => $weight,
                    'comment' => __('inventory attached', 'cdekdelivery'),
                ];

                if (Tariff::availableForShops($this->tariff)) {
                    $package['items'] = $items;
                }

                return $package;
            }, $packages);
        }

        private function buildItemData(
            WC_Order_Item_Product $item,
            int $qty,
            ?string $shouldConvert,
            ?int $shouldPay,
            int &$weight
        ): array
        {
            $product = $item->get_product();

            $w      = WeightConverter::getWeightInGrams($product->get_weight());
            $weight += $qty * $w;
            $cost   = $shouldConvert === null ? (float)wc_get_price_including_tax($product) :
                $this->convertCurrencyToRub(
                    (float)wc_get_price_including_tax($product),
                    $shouldConvert,
                );

            $payment = ['value' => 0];

            if ($shouldPay !== null) {
                if ($shouldPay !== 0) {
                    $payment['value'] = (int)(($shouldPay / 100) * $cost);

                    if($product->is_taxable()){
                        $taxCost = $shouldConvert === null ? (float)$item->get_total_tax() :
                            $this->convertCurrencyToRub(
                                (float)$item->get_total_tax(),
                                $shouldConvert,
                            );

                        $payment['vat_rate'] = Tax::getTax($product->get_tax_class());

                        if($taxCost > 0 && $payment['vat_rate'] !== null){
                            $payment['vat_sum'] = (float)(($shouldPay / 100) * $taxCost);
                        }else{
                            $payment['vat_sum'] = 0;
                        }
                    }
                } else {
                    $payment['value'] = $cost;
                }
            }

            $jewelUin = $item->get_meta(MetaKeys::JEWEL_UIN);

            $orderItem = [
                'ware_key'     => $product->get_sku() ?: $product->get_id(),
                'payment'      => $payment,
                'name'         => $item->get_name(),
                'cost'         => $cost,
                'amount'       => $qty,
                'weight'       => $w,
                'weight_gross' => $w + 1,
            ];

            if(!empty($jewelUin)){
                $orderItem['jewel_uin'] = $jewelUin;
            }

            return $orderItem;
        }

        private function convertCurrencyToRub(float $cost, string $currency): float
        {
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
                $cost = round($cost * (float)$rates['RUB'], 2);
            } else {
                $costConvertToDefault = round($cost / (float)$rates[$currency], 2);
                $cost                 = round($costConvertToDefault * (float)$rates['RUB'], 2);
            }

            return $cost;
        }
    }
}
