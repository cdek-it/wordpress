<?php

namespace Cdek;

use WP_Http;

class HttpClientWrapper {

    public function sendRequest($url, $method, $token, $data = null) {
        $WP_Http = new WP_Http();
        $resp    = $WP_Http->request($url, [
            'method'  => $method,
            'body'    => $data,
            'headers' => [
                "Content-Type"  => "application/json",
                "Authorization" => $token,
            ],
        ]);

        if (is_array($resp)) {
            return $resp['body'];
        }

        return json_encode(['status' => 'error']);
    }
}
