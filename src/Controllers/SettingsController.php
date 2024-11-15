<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Actions\FlushTokenCacheAction;
    use Cdek\CdekApi;
    use Cdek\Commands\TokensSyncCommand;
    use Cdek\Config;
    use Cdek\Helpers\Tokens;
    use WP_Http;
    use WP_REST_Request;
    use WP_REST_Response;
    use WP_REST_Server;

    class SettingsController
    {
        public static function callback(WP_REST_Request $request): WP_REST_Response
        {
            switch ($request->get_param('command')) {
                case 'tokens.refresh':
                    TokensSyncCommand::new()($request->get_json_params());
                    break;
                default:
                    return new WP_REST_Response(['state' => 'unknown command'], WP_Http::BAD_REQUEST);
            }

            return new WP_REST_Response(['state' => 'OK'], WP_Http::ACCEPTED);
        }

        public static function checkAuth(): WP_REST_Response
        {
            return new WP_REST_Response(['state' => (new CdekApi)->checkAuth()], WP_Http::OK);
        }

        /**
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         */
        public static function cities(WP_REST_Request $request): WP_REST_Response
        {
            return new WP_REST_Response(
                (new CdekApi)->citySuggest($request->get_param('q'), get_option('woocommerce_default_country', 'RU')),
                WP_Http::OK,
            );
        }

        public static function resetCache(): WP_REST_Response
        {
            FlushTokenCacheAction::new()();

            return new WP_REST_Response(['state' => 'OK'], WP_Http::OK);
        }

        public function __invoke(): void
        {
            register_rest_route(Config::DELIVERY_NAME, '/cities', [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [__CLASS__, 'cities'],
                'permission_callback' => static fn() => current_user_can('manage_woocommerce'),
                'args'                => [
                    'q' => [
                        'description' => esc_html__('Request', 'cdekdelivery'),
                        'required'    => true,
                        'type'        => 'string',
                    ],
                ],
            ]);

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
        }
    }
}
