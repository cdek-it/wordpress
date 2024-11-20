<?php

declare(strict_types=1);

namespace Cdek\Controllers;

use Cdek\Commands\TokensSyncCommand;
use Cdek\Config;
use Cdek\Helpers\Tokens;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class CallbackController
{
    public static function handle(WP_REST_Request $request): WP_REST_Response
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

    public function __invoke(): void
    {
        register_rest_route(Config::DELIVERY_NAME, '/cb', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle'],
            'permission_callback' => [Tokens::class, 'checkIncomingRequest'],
        ]);
    }
}
