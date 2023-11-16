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
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\Tariff;
    use Exception;
    use WC_Order;
    use WP_REST_Request;

    class CheckoutMapBlock implements IntegrationInterface {

        public static function addStoreApiData(): void {
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

        public static function extend_cart_data(): array {
            $api = new CdekApi;

            $city = $api->getCityCodeByCityName(CheckoutHelper::getValueFromCurrentSession('city'),
                CheckoutHelper::getValueFromCurrentSession('postcode'));

            return [
                'points' => $city !== -1 ? $api->getOffices([
                    'city_code' => $city,
                ]) : '[]',
            ];
        }

        public static function extend_cart_schema(): array {
            return [
                'points' => [
                    'description' => 'JSONifiend array of available CDEK offices',
                    'type'        => 'string',
                    'readonly'    => true,
                    'context'     => ['view', 'edit'],
                ],
            ];
        }

        public static function extend_checkout_schema(): array {
            return [
                'office' => [
                    'description' => 'Code of selected CDEK office for delivery',
                    'type'        => 'string',
                    'readonly'    => true,
                    'context'     => ['view', 'edit'],
                ],
            ];
        }

        public static function saveOrderData(WC_Order $order, WP_REST_Request $request): void {
            OrderMetaData::addMetaByOrderId($order->get_id(), [
                'currency' => function_exists('wcml_get_woocommerce_currency_option') ? get_woocommerce_currency() :
                    'RUB',
                'pvz_code' => $request['extensions'][Config::DELIVERY_NAME]['office'],
            ]);

            $shippingMethod = CheckoutHelper::getOrderShippingMethod($order);

            if (Tariff::isTariffToOffice($shippingMethod->get_meta('tariff_code'))) {
                $pvzAddress = (new CdekApi)->getOffices(['code' => $request['extensions'][Config::DELIVERY_NAME]['office']]);
                try {
                    $pvzArray = json_decode($pvzAddress, true, 512, JSON_THROW_ON_ERROR);
                    if (isset($pvzArray[0]['location']['address'])) {
                        $shippingMethod->add_meta_data('pvz', sprintf('%s (%s)', $request['extensions'][Config::DELIVERY_NAME]['office'], $pvzArray[0]['location']['address']));
                        $shippingMethod->save_meta_data();
                    }
                } catch (Exception $exception) {
                }
            }
        }

        public function get_name(): string {
            return Config::DELIVERY_NAME;
        }

        public function initialize(): void {
            Helper::enqueueScript('cdek-checkout-map-block-frontend', 'cdek-checkout-map-block-frontend');
            Helper::enqueueScript('cdek-checkout-map-block-editor', 'cdek-checkout-map-block', true);

            add_action('woocommerce_store_api_checkout_update_order_from_request', [__CLASS__, 'saveOrderData'], 10, 2);
        }

        public function get_script_handles(): array {
            return ['cdek-checkout-map-block-frontend'];
        }

        public function get_editor_script_handles(): array {
            return ['cdek-checkout-map-block-editor'];
        }

        public function get_script_data(): array {
            return [
                'apiKey'              => Helper::getActualShippingMethod()->get_option('yandex_map_api_key'),
                'officeDeliveryModes' => Tariff::getDeliveryModesToOffice(),
            ];
        }
    }
}
