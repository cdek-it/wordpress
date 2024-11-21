<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Contracts\ExceptionContract;
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
    use Cdek\Helpers\StringHelper;
    use Cdek\Helpers\WeightConverter;
    use Cdek\Loader;
    use Cdek\Model\Order;
    use Cdek\Model\Service;
    use Cdek\Model\ShippingItem;
    use Cdek\Model\Tariff;
    use Cdek\Note;
    use Cdek\Traits\CanBeCreated;
    use Cdek\Validator\PhoneValidator;
    use Exception;
    use Throwable;

    class OrderCreateAction
    {
        use CanBeCreated;

        private const ALLOWED_PRODUCT_TYPES = ['variation', 'simple'];
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
        public function __invoke(int $orderId, int $attempt = 0, array $packages = null): array
        {
            $this->api   = new CdekApi;
            $this->order = new Order($orderId);

            $shipping = $this->order->getShipping();

            if ($shipping === null) {
                throw new ShippingNotFoundException;
            }

            $this->shipping = $shipping;
            $this->tariff   = $shipping->tariff ?: $this->order->tariff_id;

            try {
                $coreApi = new CoreApi;

                $existingOrder = $coreApi->orderGet($orderId);

                try {
                    $historyCdekStatuses = $coreApi->orderHistory($existingOrder['uuid']);
                } catch (ExceptionContract $e) {
                    $historyCdekStatuses = [];
                }

                return [
                    'state'     => true,
                    'code'      => $existingOrder['track'],
                    'statuses'  => $this->outputStatusList(
                        !empty($historyCdekStatuses),
                        array_merge(
                            [$existingOrder['status']],
                            $historyCdekStatuses,
                        ),
                    ),
                    'available' => !empty($historyCdekStatuses),
                    'door'      => Tariff::isFromDoor($this->tariff),
                ];
            } catch (CoreAuthException|ApiException|CacheException $e) {
                //Do nothing
            }

            try {
                return $this->createOrder($packages);
            } catch (InvalidPhoneException $e) {
                Note::send(
                    $this->order->id,
                    sprintf(
                        esc_html__(/* translators: 1: error message */ 'Cdek shipping error: %1$s', 'cdekdelivery'),
                        $e->getMessage(),
                    ),
                );

                return [
                    'state'   => false,
                    'message' => $e->getMessage(),
                ];
            } catch (Throwable $e) {
                if ($attempt < 1 || $attempt > 5) {
                    throw $e;
                }

                wp_schedule_single_event(time() + 60 * 5, Config::ORDER_AUTOMATION_HOOK_NAME, [$orderId, $attempt + 1]);

                return [
                    'state'   => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        private function outputStatusList(bool $actionAvailable, array $statuses = []): string
        {
            $cdekStatuses         = $statuses;
            $actionOrderAvailable = $actionAvailable;
            ob_start();

            include(Loader::getPluginPath('templates/admin/status_list.php'));

            return ob_get_clean();
        }

        /**
         * @throws LegacyAuthException
         * @throws ApiException
         * @throws HttpClientException
         * @throws HttpServerException
         * @throws InvalidPhoneException
         * @throws InvalidRequestException
         */
        private function createOrder(array $packages): array
        {
            $param             = $this->buildRequestData();
            $param['packages'] = $this->buildPackagesData($packages);

            $orderData = $this->api->orderCreate($param);

            sleep(5);

            $cdekNumber = $this->getCdekOrderNumber($orderData['entity']['uuid']);

            $this->order->number = $cdekNumber;
            $this->order->uuid   = $orderData['entity']['uuid'];
            $this->order->save();

            try {
                $cdekStatuses         = $this->order->loadLegacyStatuses();
                $actionOrderAvailable = $this->order->isLocked();
            } catch (Exception $e) {
                $cdekStatuses         = [];
                $actionOrderAvailable = true;
            }

            if (!empty($cdekNumber)) {
                Note::send(
                    $this->order->id,
                    sprintf(
                        esc_html__(/* translators: 1: tracking number */ 'Tracking number: %1$s',
                            'cdekdelivery',
                        ),
                        $cdekNumber,
                    ),
                    true,
                );
            }

            return [
                'state'     => true,
                'code'      => $cdekNumber,
                'statuses'  => $this->outputStatusList($actionOrderAvailable, $cdekStatuses),
                'available' => $actionOrderAvailable,
                'door'      => Tariff::isFromDoor($this->tariff),
            ];
        }

        /**
         * @throws InvalidPhoneException
         */
        private function buildRequestData(): array
        {
            $countryCode     = $this->order->country ?: 'RU';
            $recipientNumber = PhoneValidator::new()($this->order->phone, $countryCode);

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
                    'city'    => $deliveryMethod->city_code,
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

            if ($this->order->shouldBePaidUponDelivery()) {
                $param['delivery_recipient_cost'] = [
                    'value' => $this->order->shipping_total,
                ];
            }

            return $param;
        }

        private function buildPackagesData(array $packages = null): array
        {
            $items = array_filter(
                $this->order->items,
                static fn($el) => in_array($el->get_product()->get_type(), self::ALLOWED_PRODUCT_TYPES, true),
            );

            if ($packages === null) {
                $packageItems = array_map(static function ($item) {
                    $product = $item->get_product();

                    return [
                        'id'       => $product->get_id(),
                        'name'     => $product->get_name(),
                        'weight'   => $product->get_weight(),
                        'quantity' => $item->get_quantity(),
                        'price'    => $product->get_price(),
                    ];
                }, $items);

                return [
                    $this->buildItemsData(
                        (int)$this->shipping->length,
                        (int)$this->shipping->width,
                        (int)$this->shipping->height,
                        $packageItems,
                    ),
                ];
            }

            $output = [];

            foreach ($packages as $package) {
                if (empty($package['items'])) {
                    $package['items'] = array_map(static function ($item) {
                        $product = $item->get_product();

                        return [
                            'id'       => $product->get_id(),
                            'name'     => $product->get_name(),
                            'weight'   => $product->get_weight(),
                            'quantity' => $item->get_quantity(),
                            'price'    => $product->get_price(),
                        ];
                    }, $items);
                } else {
                    $package['items'] = array_map(static function ($el) {
                        $product = wc_get_product($el['id']);

                        return [
                            'id'       => $product->get_id(),
                            'name'     => $product->get_name(),
                            'weight'   => $product->get_weight(),
                            'quantity' => $el['quantity'],
                            'price'    => $product->get_price(),
                        ];
                    }, $package['items']);
                }
                $output[] = $this->buildItemsData(
                    (int)$package['length'],
                    (int)$package['width'],
                    (int)$package['height'],
                    $package['items'],
                );
            }

            return $output;
        }

        private function buildItemsData(
            int $length,
            int $width,
            int $height,
            array $items
        ): array {
            $deliveryMethod = $this->shipping->getMethod();

            $totalWeight = 0;
            $itemsData   = [];

            foreach ($items as $item) {
                $weight      = WeightConverter::getWeightInGrams($item['weight']);
                $quantity    = (int)$item['quantity'];
                $totalWeight += $quantity * $weight;
                $cost        = $item['price'];

                if ($this->order->currency !== 'RUB' && function_exists('wcml_get_woocommerce_currency_option')) {
                    $cost = $this->convertCurrencyToRub($cost, $this->order->currency);
                }

                if ($this->order->shouldBePaidUponDelivery()) {
                    $percentCod = (int)$deliveryMethod->get_option('percentcod');

                    if ($percentCod !== 0) {
                        $paymentValue = (int)(($percentCod / 100) * $cost);
                    } else {
                        $paymentValue = $cost;
                    }
                } else {
                    $paymentValue = 0;
                }

                $itemsData[] = [
                    'ware_key'     => $item['id'],
                    'payment'      => ['value' => $paymentValue],
                    'name'         => $item['name'],
                    'cost'         => $cost,
                    'amount'       => $item['quantity'],
                    'weight'       => $weight,
                    'weight_gross' => $weight + 1,
                ];
            }

            $package = [
                'number'  => sprintf('%s_%s', $this->order->id, StringHelper::generateRandom(5)),
                'length'  => $length,
                'width'   => $width,
                'height'  => $height,
                'weight'  => $totalWeight,
                'comment' => __('inventory attached', 'cdekdelivery'),
            ];

            if (Tariff::availableForShops($this->tariff)) {
                $package['items'] = $itemsData;
            }

            return $package;
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

        /**
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         * @throws \Cdek\Exceptions\External\ApiException
         */
        private function getCdekOrderNumber(string $orderUuid, int $iteration = 1): ?string
        {
            sleep(1);

            if ($iteration === 5) {
                return null;
            }

            $orderInfo = $this->api->orderGet($orderUuid);

            return $orderInfo->entity()['cdek_number'] ?? $this->getCdekOrderNumber($orderUuid, $iteration + 1);
        }
    }
}
