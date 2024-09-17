<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Transport {

    use Cdek\Exceptions\CdekApiException;
    use Cdek\Exceptions\CdekClientException;
    use Cdek\Exceptions\CdekServerException;
    use Cdek\Loader;
    use WP_Error;
    use WP_REST_Server;

    class HttpClient
    {
        /**
         * @throws \Cdek\Exceptions\CdekApiException
         * @throws \Cdek\Exceptions\CdekClientException
         * @throws \Cdek\Exceptions\CdekServerException
         * @throws \JsonException
         */
        public static function sendJsonRequest(
            string $url,
            string $method,
            string $token,
            ?array $data = null,
            ?array $headers = []
        ): HttpResponse {
            $config = [
                'headers' => array_merge([
                    'Content-Type'  => 'application/json',
                    'Authorization' => $token,
                ], $headers),
                'timeout' => 60,
            ];

            if (!empty($data)) {
                $config['body'] = ($method === WP_REST_Server::READABLE) ? $data : wp_json_encode($data);
            }

            $result = self::processRequest($url, $method, $config);

            if ($result->isServerError()) {
                throw new CdekServerException('Server error', 'api.server', $result->error());
            }

            if ($result->isClientError()) {
                throw new CdekClientException('Server error', 'api.server', $result->error());
            }

            return $result;
        }

        /**
         * @throws CdekApiException
         */
        public static function processRequest(
            string $url,
            string $method,
            array $config = []
        ): HttpResponse {
            $resp = wp_remote_request($url, array_merge_recursive($config, [
                'headers'    => [
                    'X-App-Name'       => 'wordpress',
                    'X-App-Version'    => Loader::getPluginVersion(),
                    'X-User-Locale'    => get_user_locale(),
                    'X-Correlation-Id' => wp_generate_uuid4(),
                ],
                'method'     => $method,
                'user-agent' => 'wp/'.get_bloginfo('version'),
            ]));

            if (is_wp_error($resp)) {
                assert($resp instanceof WP_Error);
                throw new CdekApiException($resp->get_error_message(), 'api.general', [
                    'ip' => self::tryGetRequesterIp(),
                ]);
            }

            return new HttpResponse($resp['response']['code'], $resp['body'], $resp['headers']);
        }

        public static function tryGetRequesterIp(): ?string
        {
            $ip = wp_remote_retrieve_body(wp_remote_get('https://ipecho.net/plain'));

            if ($ip === '') {
                return null;
            }

            if (!headers_sent()) {
                header("X-Requester-IP: $ip");
            }

            return $ip;
        }
    }
}
