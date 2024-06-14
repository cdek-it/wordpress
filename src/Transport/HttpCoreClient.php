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
         * @param string     $token
         * @param array|null $data
         * @param array      $headers
         */
        public function sendCdekRequest(
            string $url,
            string $method,
            string $token,
            array  $data = null,
            array  $headers = []
        ): array
        {
            $config = [];

            $headers['Authorization'] = $token;

            if (!empty($data)) {
                $config['body'] = ($method === WP_REST_Server::READABLE) ? $data : json_encode($data);
            }

            return self::sendRequest($url, $method, $config, $headers);
        }

        /**
         * @param string $url
         * @param string $method
         * @param array  $config
         * @param array  $headers
         */
        public function sendRequest(string $url, string $method, array $config = [], array $headers = []): array
        {
            $resp = wp_remote_request(
                $url,
                [
                    'method'  => $method,
                    'headers' => [
                                     'Content-Type'     => 'application/json',
                                     'X-App-Name'       => 'wordpress',
                                     'X-App-Version'    => Loader::getPluginVersion(),
                                     'X-User-Locale'    => get_user_locale(),
                                     'X-Correlation-Id' => self::generateUuid(),
                                     'user-agent'       => 'wp/' . get_bloginfo('version'),
                                 ] + $headers,
                    'timeout' => 60,
                ] + $config,
            );

            if (is_array($resp)) {
                return $resp;
            }

            $ip = @file_get_contents('https://ipecho.net/plain');

            if (!headers_sent()) {
                header("X-Requester-IP: $ip");
            }

            return ['error' => true, 'ip' => $ip];
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
