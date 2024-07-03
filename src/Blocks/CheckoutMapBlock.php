<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Blocks {

    use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
    use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
    use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Helper;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\MetaKeys;
    use Cdek\Model\Tariff;
    use WC_Order;
    use WC_Customer;
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
                                                         ]);
        }

        public static function extend_cart_data(): array
        {
            $cityInput     = CheckoutHelper::getValueFromCurrentSession('city');
            $postcodeInput = CheckoutHelper::getValueFromCurrentSession('postcode');

            if (empty($cityInput)) {
                return ['points' => '[]'];
            }

            $api = new CdekApi;

            $city = $api->getCityCode($cityInput, $postcodeInput);

            if ($city === -1) {
                $city = $api->getCityCode($cityInput, '');
            }

            return [
                'inputs' => [
                    'city'     => $cityInput,
                    'postcode' => $postcodeInput,
                ],
                'city'   => $city,
                'points' => $city !== -1 ? $api->getOffices([
                                                                'city_code' => $city,
                                                            ])['body'] : '[]',
            ];
        }

        public static function extend_checkout_data(): array
        {
            return [
                'office_code' => null,
            ];
        }

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

        public static function saveCustomerData(WC_Customer $customer, WP_REST_Request $request): void
        {
            if (
                array_key_exists(Config::DELIVERY_NAME, $request['extensions'])
                &&
                !empty($request['extensions'][Config::DELIVERY_NAME]['office_code'])
            ) {
                $customer->add_meta_data(Config::DELIVERY_NAME . '_office_code',
                                         $request['extensions'][Config::DELIVERY_NAME]['office_code']);
            }
        }

        public static function saveOrderData(WC_Order $order, WP_REST_Request $request): void
        {
            $shippingMethod = CheckoutHelper::getOrderShippingMethod($order);

            if (!CheckoutHelper::isCdekShippingMethod($order)) {
                return;
            }

            if (Tariff::isTariffToOffice((int) ($shippingMethod->get_meta(MetaKeys::TARIFF_CODE) ?:
                $shippingMethod->get_meta('tariff_code')))) {
                $shippingMethod->add_meta_data(MetaKeys::OFFICE_CODE,
                                               $request['extensions'][Config::DELIVERY_NAME]['office_code']);
            }
        }

        public function get_name(): string
        {
            return Config::DELIVERY_NAME;
        }

        public function initialize(): void
        {
            Helper::enqueueScript('cdek-checkout-map-block-frontend', 'cdek-checkout-map-block-frontend', false, true);
            Helper::enqueueScript('cdek-checkout-map-block-editor', 'cdek-checkout-map-block', false, true);
        }

        public function get_script_handles(): array
        {
            return ['cdek-checkout-map-block-frontend'];
        }

        public function get_editor_script_handles(): array
        {
            return ['cdek-checkout-map-block-editor'];
        }

        public function get_script_data(): array
        {
            return [
                'apiKey'              => Helper::getActualShippingMethod()->get_option('yandex_map_api_key'),
                'officeDeliveryModes' => Tariff::getDeliveryModesToOffice(),
            ];
        }
    }
}
