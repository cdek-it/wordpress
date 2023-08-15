<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Transport {

    use WP_Http;

    class HttpClient {
        public static function sendCdekRequest(
            string $url,
            string $method,
            string $token,
            array $data = null,
            bool $plain = false
        ) {
            $config = [
                'headers' => [
                    "Content-Type"  => 'application/json',
                    "Authorization" => $token,
                ],
            ];

            if (!empty($data)) {
                $config['body'] = json_encode($data);
            }

            return self::sendRequest($url, $method, $config, $plain);
        }

        public static function sendRequest(string $url, string $method, array $config = [], bool $plain = false) {
            $resp = (new WP_Http())->request($url, array_merge($config, ['method' => $method]));

            if ($plain || is_array($resp)) {
                return $resp['body'];
            }

            return json_encode(['status' => 'error']);
        }
    }
}
