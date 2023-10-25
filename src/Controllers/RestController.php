<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Actions\GenerateBarcodeAction;
    use Cdek\Actions\GenerateWaybillAction;
    use Cdek\CdekApi;
    use Cdek\Config;
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

        public static function getWaybill(WP_REST_Request $request): void
        {
            (new GenerateWaybillAction)(OrderMetaData::getMetaByOrderId($request->get_param('id'))['order_uuid']
                                        ??
                                        '');
        }

        public static function getBarcode(WP_REST_Request $request): void
        {
            (new GenerateBarcodeAction)(OrderMetaData::getMetaByOrderId($request->get_param('id'))['order_uuid']
                                        ??
                                        '');
        }

        public function __invoke(): void
        {
            register_rest_route(Config::DELIVERY_NAME, '/check-auth', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'checkAuth'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route(Config::DELIVERY_NAME, '/order/(?P<id>\d+)/waybill', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'getWaybill'],
                'permission_callback' => static fn() => current_user_can('edit_posts'),
                'show_in_index'       => true,
                'args'                => [
                    'id' => [
                        'description' => 'CDEK Order ID',
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
                        'description' => 'CDEK Order ID',
                        'required'    => true,
                        'type'        => 'number',
                    ],
                ],
            ]);
        }
    }

}
