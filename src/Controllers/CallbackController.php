<?php

declare(strict_types=1);

namespace Cdek\Controllers;

use Cdek\Commands\TokensSyncCommand;
use Cdek\Config;
use Cdek\Helpers\Tokens;
use Cdek\TaskManager;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class CallbackController
{
    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $command = $request->get_param('command');

        if ($command === 'tokens.refresh') {
            TokensSyncCommand::new()($request->get_json_params());

            return new WP_REST_Response(null, WP_Http::ACCEPTED);
        }

        if ($command === 'tasks') {
            TaskManager::executeNow();

            return new WP_REST_Response(null, WP_Http::ACCEPTED);
        }

        return new WP_REST_Response(null, WP_Http::BAD_REQUEST);
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
