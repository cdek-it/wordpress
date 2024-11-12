<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Actions\FlushTokenCacheAction;
    use Cdek\Actions\GenerateBarcodeAction;
    use Cdek\Actions\GenerateWaybillAction;
    use Cdek\CdekApi;
    use Cdek\Commands\TokensSyncCommand;
    use Cdek\Config;
    use Cdek\Helpers\Tokens;
    use Cdek\Model\OrderMetaData;
    use WP_Http;
    use WP_REST_Request;
    use WP_REST_Response;
    use WP_REST_Server;

    class RestController
    {
        public static function checkAuth(): WP_REST_Response
        {
            (new CdekApi)->checkAuth();
            return new WP_REST_Response(['state' => 'OK'], WP_Http::OK);
        }

        public static function resetCache(): WP_REST_Response
        {
            FlushTokenCacheAction::new()();

            return new WP_REST_Response(['state' => 'OK'], WP_Http::OK);
        }

        /**
         * @throws \JsonException
         */
        public static function getWaybill(WP_REST_Request $request): WP_REST_Response
        {
            return new WP_REST_Response(
                GenerateWaybillAction::new()(
                    OrderMetaData::getMetaByOrderId($request->get_param('id'))['order_uuid'] ?? '',
                ), WP_Http::OK,
            );
        }

        public static function getBarcode(WP_REST_Request $request): WP_REST_Response
        {
            return new WP_REST_Response(
                GenerateBarcodeAction::new()(
                    OrderMetaData::getMetaByOrderId($request->get_param('id'))['order_uuid'] ?? '',
                ), WP_Http::OK,
            );
        }

        public static function callback(WP_REST_Request $request): WP_REST_Response
        {
            switch ($request->get_param('command')){
                case 'tokens.refresh':
                    TokensSyncCommand::new()($request->get_json_params());
                    break;
                default:
                    return new WP_REST_Response(['state' => 'unknown command'], WP_Http::BAD_REQUEST);
            }

            return new WP_REST_Response(['state' => 'OK'], WP_Http::ACCEPTED);
        }

        public function __invoke(): void
        {
            register_rest_route(Config::DELIVERY_NAME, '/cb', [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [__CLASS__, 'callback'],
                'permission_callback' => [Tokens::class, 'checkIncomingRequest'],
            ]);

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
