<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Transport {

    use WP_Http;

    class HttpClient {
        public static function sendCdekRequest($url, $method, $token, $data = null, $plain = false) {
            return self::sendRequest($url, $method, [
                'body'    => $data,
                'headers' => [
                    "Content-Type"  => 'application/json',
                    "Authorization" => $token,
                ],
            ], $plain);
        }

        public static function sendRequest($url, $method, $config = [], $plain = false) {
            $resp = (new WP_Http())->request($url, array_merge($config, ['method' => $method]));

            if ($plain || is_array($resp)) {
                return $resp['body'];
            }

            return json_encode(['status' => 'error']);
        }
    }
}
