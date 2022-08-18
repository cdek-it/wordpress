<?php

namespace Cdek;

use WP_Http;

class HttpClientWrapper
{

    public function sendCurl($url, $method, $token, $data = null)
    {
        $WP_Http = new WP_Http();
        $resp = $WP_Http->request( $url, [
            'method' => $method,
            'body' => $data,
            'headers' => [
                "Content-Type" => "application/json",
                "Authorization" => $token
            ],
        ] );

        return $resp['body'];
    }
}