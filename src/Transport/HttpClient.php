<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Transport {

    use WP_Http;

    class HttpClient {
        public static function sendCdekRequest($url, $method, $token, $data = null) {
            $resp = (new WP_Http())->request($url, [
                'method'  => $method,
                'body'    => $data,
                'headers' => [
                    "Content-Type"  => 'application/json',
                    "Authorization" => $token,
                ],
            ]);

            if (is_array($resp)) {
                return $resp['body'];
            }

            return json_encode(['status' => 'error']);
        }
    }
}
