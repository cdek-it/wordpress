<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Actions\GenerateBarcodeAction;
    use Cdek\Actions\GenerateWaybillAction;
    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Helpers\DBTokenStorage;
    use Cdek\Model\OrderMetaData;
    use WP_REST_Request;
    use WP_REST_Response;
    use WP_REST_Server;

    class RestController
    {
        public static function checkAuth(): WP_REST_Response
        {
            return new WP_REST_Response(['state' => (new CdekApi)->checkAuth()], 200);
        }

        public static function resetCache(): WP_REST_Response
        {
            DBTokenStorage::flushCache();

            return new WP_REST_Response(['state' => 'OK'], 200);
        }

        /**
         * @throws \JsonException
         */
        public static function getWaybill(WP_REST_Request $request): WP_REST_Response
        {
            return new WP_REST_Response((new GenerateWaybillAction)(OrderMetaData::getMetaByOrderId($request->get_param('id'))['order_uuid']
                                                                    ??
                                                                    ''));
        }

        public static function getBarcode(WP_REST_Request $request): WP_REST_Response
        {
            return new WP_REST_Response((new GenerateBarcodeAction)(OrderMetaData::getMetaByOrderId($request->get_param('id'))['order_uuid']
                                                                    ??
                                                                    ''));
        }

        public function __invoke(): void
        {
            register_rest_route(Config::DELIVERY_NAME, '/check-auth', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'checkAuth'],
                'permission_callback' => static fn() => current_user_can('manage_woocommerce'),
            ]);

            register_rest_route(Config::DELIVERY_NAME, '/flush-cache', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'resetCache'],
                'permission_callback' => static fn() => current_user_can('manage_woocommerce'),
            ]);

            register_rest_route(Config::DELIVERY_NAME, '/order/(?P<id>\d+)/waybill', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getWaybill'],
                'permission_callback' => static fn() => current_user_can('edit_posts'),
                'show_in_index'       => true,
                'args'                => [
                    'id' => [
                        'description' => esc_html__('CDEK Order ID', 'cdekdelivery'),
                        'required'    => true,
                        'type'        => 'number',
                    ],
                ],
            ]);

            register_rest_route(Config::DELIVERY_NAME, '/order/(?P<id>\d+)/barcode', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getBarcode'],
                'permission_callback' => static fn() => current_user_can('edit_posts'),
                'show_in_index'       => true,
                'args'                => [
                    'id' => [
                        'description' => esc_html__('CDEK Order ID', 'cdekdelivery'),
                        'required'    => true,
                        'type'        => 'number',
                    ],
                ],
            ]);
        }
    }

}
