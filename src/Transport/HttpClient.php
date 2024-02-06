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
        public static function sendCdekRequest(
            string $url,
            string $method,
            string $token,
            array $data = null,
            bool $plain = false
        ) {
            $config = [
                'headers' => [
                    'Content-Type'  => 'application/json',
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

            $resp = (new WP_Http)->request($url, array_merge($config, [
                                                                        'method'     => $method,
                                                                        'user-agent' => "wp/$pluginVersion",
                                                                    ]));

            if ($plain || is_array($resp)) {
                return is_array($resp) ? ['body' => $resp['body'], 'headers' => $resp['headers']] : $resp;
            }

            $ip = @file_get_contents('https://ipecho.net/plain');

            if (!headers_sent()) {
                header("X-Requester-IP: $ip");
            }

            return json_encode(['error' => true, 'ip' => $ip]);
        }
    }
}
