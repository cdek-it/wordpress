<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Blocks {

    use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
    use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
    use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Contracts\ExceptionContract;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\Helpers\OfficeListCache;
    use Cdek\Helpers\UI;
    use Cdek\MetaKeys;
    use Cdek\Model\Order;
    use Cdek\Model\Tariff;
    use Cdek\ShippingMethod;
    use JsonException;
    use Throwable;
    use WC_Customer;
    use WC_Order;
    use WP_REST_Request;

    class CheckoutMapBlock implements IntegrationInterface
    {

        public static function addStoreApiData(): void
        {
            woocommerce_store_api_register_endpoint_data([
                'endpoint'        => CartSchema::IDENTIFIER,
                'namespace'       => Config::DELIVERY_NAME,
                'schema_callback' => [__CLASS__, 'extend_cart_schema'],
                'schema_type'     => ARRAY_A,
                'data_callback'   => [__CLASS__, 'extend_cart_data'],
            ]);
            woocommerce_store_api_register_endpoint_data([
                'endpoint'        => CheckoutSchema::IDENTIFIER,
                'namespace'       => Config::DELIVERY_NAME,
                'schema_callback' => [__CLASS__, 'extend_checkout_schema'],
                'schema_type'     => ARRAY_A,
                'data_callback'   => [__CLASS__, 'extend_checkout_data'],
            ]);
        }

        public static function extend_cart_data(): array
        {
            $cityInput     = CheckoutHelper::getCurrentValue('city');
            $postcodeInput = CheckoutHelper::getCurrentValue('postcode');
            $tariffMode    = self::getSelectedTariffMode();

            if (empty($cityInput)) {
                return ['points' => '[]', 'tariffMode' => $tariffMode];
            }

            $cached = OfficeListCache::get($cityInput, $postcodeInput);

            if ($cached === null) {
                $api = new CdekApi;

                try {
                    $city   = $api->cityCodeGet($cityInput, $postcodeInput);
                    $points = $city !== null ? $api->officeListRaw($city) : '[]';
                } catch (ExceptionContract $e) {
                    $city   = null;
                    $points = '[]';
                }

                $cached = ['city' => $city, 'points' => $points];

                // Ошибки не кешируем (город не резолвнулся/API упал): иначе временный
                // сбой держит пустую карту ПВЗ все TTL (300с), даже после восстановления.
                if ($city !== null) {
                    OfficeListCache::set($cityInput, $postcodeInput, $cached);
                }
            }

            return [
                'inputs' => [
                    'city'     => $cityInput,
                    'postcode' => $postcodeInput,
                ],
                'city'       => $cached['city'],
                'points'     => $cached['points'],
                'tariffMode' => $tariffMode,
            ];
        }

        private static function getSelectedTariffMode(): ?int
        {
            $rate = CheckoutHelper::getSelectedShippingRate();

            if ($rate === null || !CheckoutHelper::isShippingRateSuitable($rate)) {
                return null;
            }

            $meta = $rate->get_meta_data();

            return isset($meta[MetaKeys::TARIFF_MODE]) ? (int)$meta[MetaKeys::TARIFF_MODE] : null;
        }

        /** @noinspection PhpUnused */

        public static function extend_cart_schema(): array
        {
            return [
                'points' => [
                    'description' => esc_html__('JSONifiend array of available CDEK offices', 'cdekdelivery'),
                    'type'        => 'string',
                    'readonly'    => true,
                    'context'     => ['view', 'edit'],
                ],
                'tariffMode' => [
                    'description' => esc_html__('Delivery mode of the currently selected CDEK tariff', 'cdekdelivery'),
                    'type'        => ['integer', 'null'],
                    'readonly'    => true,
                    'context'     => ['view', 'edit'],
                ],
            ];
        }

        public static function extend_checkout_data(): array
        {
            try {
                $officeCode = WC()->session->get(Config::DELIVERY_NAME.'_office_code') ?: null;
            } catch (Throwable $e) {
                $officeCode = null;
            }

            return [
                'office_code' => $officeCode,
            ];
        }

        /** @noinspection PhpUnused */

        public static function extend_checkout_schema(): array
        {
            return [
                'office_code' => [
                    'description' => esc_html__('Code of selected CDEK office for delivery', 'cdekdelivery'),
                    'type'        => ['string', 'null'],
                    'readonly'    => true,
                    'context'     => ['view', 'edit'],
                ],
            ];
        }

        public static function saveOrderData(WC_Order $order, WP_REST_Request $request): void
        {
            $shipping = (new Order($order))->getShipping();

            if ($shipping === null) {
                return;
            }

            $officeCode = $request['extensions'][Config::DELIVERY_NAME]['office_code'] ?? null;

            try {
                WC()->session->set(Config::DELIVERY_NAME.'_office_code', $officeCode);
            } catch (Throwable $e) {
            }

            if (Tariff::isToOffice((int)$shipping->tariff)) {
                $shipping->office = $officeCode;
            } else {
                $shipping->office = null;
            }
            $shipping->save();
        }

        public function get_editor_script_handles(): array
        {
            return ['cdek-checkout-map-block-editor'];
        }

        public function get_name(): string
        {
            return Config::DELIVERY_NAME;
        }

        public function get_script_data(): array
        {
            return [
                'lang'                => (mb_strpos(get_user_locale(), 'en') === 0) ? 'eng' : 'rus',
                'apiKey'              => ShippingMethod::factory()->yandex_map_api_key,
                'officeDeliveryModes' => Tariff::listOfficeDeliveryModes(),
            ];
        }

        public function get_script_handles(): array
        {
            return ['cdek-checkout-map-block-frontend'];
        }

        public function initialize(): void
        {
            UI::enqueueScript('cdek-checkout-map-block-frontend', 'cdek-checkout-map-block-frontend', false, true);
            UI::enqueueScript('cdek-checkout-map-block-editor', 'cdek-checkout-map-block', false, true);
        }
    }
}
