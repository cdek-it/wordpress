<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Helpers {

    use Cdek\CoreApi;
    use Cdek\Exceptions\External\CoreAuthException;
    use Cdek\Exceptions\External\LegacyAuthException;
    use Cdek\Exceptions\ShopRegistrationException;
    use JsonException;
    use ParagonIE\Paseto\Exception\PasetoException;
    use ParagonIE\Paseto\Keys\Version4\AsymmetricPublicKey;
    use ParagonIE\Paseto\Parser;
    use ParagonIE\Paseto\PasetoBase;
    use ParagonIE\Paseto\Rules\ForAudience;
    use ParagonIE\Paseto\Rules\ValidAt;
    use WP_REST_Request;

    class Tokens
    {
        /**
         * @throws \Cdek\Exceptions\CacheException
         */
        public static function getEndpoint(string $tokenType = 'wordpress'): ?string
        {
            $endpoints = Cache::get('endpoints');

            if ($endpoints !== null && !empty($endpoints[$tokenType])) {
                return $endpoints[$tokenType];
            }

            return null;
        }

        /**
         * @throws \Cdek\Exceptions\CacheException
         */
        public static function get(string $tokenType, bool $exchangeForbidden = false): ?string
        {
            $tokens = Cache::get('tokens');

            if (!empty($tokens[$tokenType])) {
                return "Bearer $tokens[$tokenType]";
            }

            if ($exchangeForbidden) {
                return null;
            }

            self::tryExchangeLegacyToken();

            return self::get($tokenType, true);
        }

        /**
         * @throws \Cdek\Exceptions\CacheException
         */
        public static function tryExchangeLegacyToken(): void
        {
            try {
                $token = (new DBTokenStorage)->getToken();
            } catch (LegacyAuthException $e) {
                return;
            }

            try {
                $api    = new CoreApi;
                $tokens = $api->fetchShopTokens($token, $api->syncShop($token));
            } catch (ShopRegistrationException|CoreAuthException $e) {
                return;
            }

            Cache::put([
                'tokens'    => $tokens,
                'endpoints' => array_combine(
                    array_keys($tokens),
                    array_map(
                        static fn($token) => self::getTokenFooterArray($token)['endpoint'] ?: null,
                        $tokens,
                    ),
                ),
            ]);
        }

        private static function getTokenFooterArray(string $token): array
        {
            $arToken = explode('.', $token);

            try {
                return json_decode(
                    base64_decode(array_pop($arToken)),
                    true,
                    512,
                    JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
                );
            } catch (JsonException $e) {
                return [];
            }
        }

        /**
         * @throws \Cdek\Exceptions\CacheException
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\CoreAuthException
         */
        public static function checkIncomingRequest(WP_REST_Request $request): bool
        {
            $token = $request->get_header('x_auth_token');

            if (empty($token)) {
                return false;
            }

            $kid = self::getTokenFooterArray($token)[PasetoBase::KEY_ID_FOOTER_CLAIM] ?? null;

            if ($kid === null) {
                return false;
            }

            $keyring = Cache::remember('keyring', static fn() => (new CoreApi)->fetchKeyring());

            if (!isset($keyring[$kid])) {
                return false;
            }

            try {
                $request->set_param(
                    'action',
                    Parser::getPublic(AsymmetricPublicKey::fromEncodedString($keyring[$kid]))
                          ->addRule(new ValidAt)
                          ->addRule(new ForAudience(parse_url(rest_url(), PHP_URL_HOST)))
                          ->parse($token)
                          ->getSubject(),
                );

                return true;
            } catch (PasetoException $e) {
                return false;
            }
        }
    }
}
