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
        /**
         * @param string     $url
         * @param string     $method
         * @param string     $token
         * @param array|null $data
         * @param bool       $plain
         *
         * @return array|string
         */
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
                $config['body'] = ($method === WP_REST_Server::READABLE) ? $data : wp_json_encode($data);
            }

            return self::sendRequest($url, $method, $config, $plain);
        }

        /**
         * @param string $url
         * @param string $method
         * @param array  $config
         * @param bool   $plain
         *
         * @return array|string
         */
        public static function sendRequest(string $url, string $method, array $config = [], bool $plain = false)
        {
            $pluginVersion = Loader::getPluginVersion();

            $resp = wp_remote_request($url, array_merge($config, [
                'method'     => $method,
                'user-agent' => "wp/$pluginVersion",
            ]));

            if ($plain || is_array($resp)) {
                if ($plain) {
                    return is_array($resp) ? ['body' => $resp['body'], 'headers' => $resp['headers']] : $resp;
                }

                return is_array($resp) ? $resp['body'] : $resp;
            }

            $ip = @file_get_contents('https://ipecho.net/plain');

            if (!headers_sent()) {
                header("X-Requester-IP: $ip");
            }

            return wp_json_encode(['error' => true, 'ip' => $ip]);
        }
    }
}
