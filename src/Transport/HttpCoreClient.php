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
        private array $addHeaders = [];

        public function addHeaders(array $addHeaders)
        {
            $this->addHeaders = $addHeaders;
        }

        public function sendCdekRequest(
            string $url,
            string $method,
            string $token,
            array  $data = null
        )
        {
            $config = [
                'headers' => [
                    'Authorization'    => $token,
                ],
                'timeout' => 60,
            ];


            if (!empty($data)) {
                $config['body'] = ($method === WP_REST_Server::READABLE) ? $data : json_encode($data);
            }

            return static::sendRequest($url, $method, $config);
        }

        public function sendRequest(string $url, string $method, array $config = [])
        {
            $resp = wp_remote_request(
                $url,
                array_merge(
                    $config,
                    [
                        'method'       => $method,
                        'Content-Type' => 'application/json',
                        'headers' => [
                                         'X-App-Name'       => 'wordpress',
                                         'X-App-Version'    => Loader::getPluginVersion(),
                                         'X-User-Locale'    => get_user_locale(),
                                         'X-Correlation-Id' => self::generateUuid(),
                                         'user-agent'       => Loader::getPluginName() . '_' . get_bloginfo('version'),
                                     ] + $this->addHeaders,
                        'timeout' => 60,
                    ],
                )
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
