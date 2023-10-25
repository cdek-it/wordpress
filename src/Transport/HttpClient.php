<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Transport {

    use Cdek\Loader;
    use WP_Http;
    use WP_REST_Server;

    class HttpClient
    {
        public static function sendCdekRequest(string $url,
                                               string $method,
                                               string $token,
                                               array  $data = null,
                                               bool   $plain = false)
        {
            $config = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => $token,
                ],
                'timeout' => 60,
            ];

            if (!empty($data)) {
                $config['body'] = ($method === WP_REST_Server::READABLE) ? $data : json_encode($data);
            }

            return self::sendRequest($url, $method, $config, $plain);
        }

        public static function sendRequest(string $url, string $method, array $config = [], bool $plain = false)
        {
            $pluginVersion = Loader::getPluginVersion();

            $resp =
                (new WP_Http())->request($url,
                                         array_merge($config,
                                                     ['method' => $method, 'user-agent' => "wp/$pluginVersion"]));

            if ($plain || is_array($resp)) {
                return is_array($resp) ? $resp['body'] : $resp;
            }

            return json_encode(['status' => 'error']);
        }
    }
}
