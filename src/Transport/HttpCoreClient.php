<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Transport {

    use Cdek\Loader;
    use WP_Http;
    use WP_REST_Server;

    class HttpCoreClient
    {
        /**
         * @param string     $url
         * @param string     $method
         * @param string     $token - shop token
         * @param array|null $data
         *
         * @return array|false|string|\WP_Error
         */
        public static function sendCdekShopRequest(
            string $url,
            string $method,
            string $token,
            array  $data = null
        )
        {
            $pluginVersion = Loader::getPluginVersion();

            $config = [
                'headers' => [
                    'Content-Type'     => 'application/json',
                    'Authorization'    => $token,
                    'X-App-Name'       => 'wordpress',
                    'X-App-Version'    => $pluginVersion,
                    'X-User-Locale'    => get_user_locale(),
                    'X-Correlation-Id' => self::generateUuid(),
                    'user-agent'       => Loader::getPluginName() . ':' . get_bloginfo('version'),
                ],
                'timeout' => 60,
            ];

            if (!empty($data)) {
                $config['body'] = ($method === WP_REST_Server::READABLE) ? $data : wp_json_encode($data);
            }

            return self::sendRequest($url, $method, $config);
        }

        public static function sendCdekRequest(
            string $url,
            string $method,
            string $token,
            array  $data = null
        )
        {
            $config = [
                'headers' => [
                    'Content-Type'     => 'application/json',
                    'Authorization'    => $token
                ],
                'timeout' => 60,
            ];

            if (!empty($data)) {
                $config['body'] = ($method === WP_REST_Server::READABLE) ? $data : wp_json_encode($data);
            }

            return self::sendRequest($url, $method, $config);
        }

        public static function sendRequest(string $url, string $method, array $config = [])
        {
            $resp = wp_remote_request(
                $url,
                array_merge(
                    $config,
                    [
                        'method'       => $method,
                        'Content-Type' => 'application/json',
                        'headers' => [
                            'Content-Type'     => 'application/json',
                            'X-App-Name'       => 'wordpress',
                            'X-App-Version'    => Loader::getPluginVersion(),
                            'X-User-Locale'    => get_user_locale(),
                            'X-Correlation-Id' => self::generateUuid(),
                            'user-agent'       => Loader::getPluginName() . ':' . get_bloginfo('version'),
                        ],
                        'timeout' => 60,
                    ],
                ),
            );

            if (is_array($resp)) {
                return $resp;
            }

            $ip = @file_get_contents('https://ipecho.net/plain');

            if (!headers_sent()) {
                header("X-Requester-IP: $ip");
            }

            return wp_json_encode(['error' => true, 'ip' => $ip]);
        }

        private static function generateUuid(): string
        {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
            );
        }
    }

}
