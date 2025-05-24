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

            if (empty($cityInput)) {
                return ['points' => '[]'];
            }

            $api = new CdekApi;

            try {
                $city   = $api->cityCodeGet($cityInput, $postcodeInput);
                $points = $city !== null ? $api->officeListRaw($city) : '[]';
            } catch (ExceptionContract $e) {
                $city   = null;
                $points = '[]';
            }

            return [
                'inputs' => [
                    'city'     => $cityInput,
                    'postcode' => $postcodeInput,
                ],
                'city'   => $city,
                'points' => $points,
            ];
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
            ];
        }

        public static function extend_checkout_data(): array
        {
            try {
                $request    = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
                $officeCode = $request['extensions'][Config::DELIVERY_NAME]['office_code'] ?? null;

                if ($officeCode !== null) {
                    try {
                        WC()->session->set(Config::DELIVERY_NAME.'_office_code', $officeCode);
                    } catch (Throwable $e) {
                    }

                    return [
                        'office_code' => $officeCode,
                    ];
                }
            } catch (JsonException $e) {
            }

            return [
                'office_code' => null,
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

            if (Tariff::isToOffice((int)$shipping->tariff)) {
                $shipping->office = $request['extensions'][Config::DELIVERY_NAME]['office_code'];
                $shipping->save();
            }
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
