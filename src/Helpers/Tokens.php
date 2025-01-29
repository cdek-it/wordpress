<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Helpers {

    use Cdek\Commands\TokensSyncCommand;
    use Cdek\Config;
    use Cdek\CoreApi;
    use Cdek\Exceptions\External\CoreAuthException;
    use Cdek\Exceptions\External\LegacyAuthException;
    use Cdek\Exceptions\ShopRegistrationException;
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
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\CoreAuthException
         */
        public static function checkIncomingRequest(WP_REST_Request $request): bool
        {
            if (!headers_sent()) {
                header('X-Robots-Tag: noindex, nofollow');
            }

            $token = $request->get_header('x_auth_token');

            if (empty($token)) {
                return false;
            }

            $kid = TokensSyncCommand::getTokenFooterArray($token)[PasetoBase::KEY_ID_FOOTER_CLAIM] ?? null;

            if ($kid === null) {
                return false;
            }

            $keyring = Cache::remember('keyring', static fn() => (new CoreApi)->keyringFetch());

            if (!isset($keyring[$kid])) {
                return false;
            }

            try {
                $request->set_param(
                    'command',
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
        public static function get(string $tokenType): ?string
        {
            return self::exchangeableGet($tokenType);
        }

        /**
         * @throws \Cdek\Exceptions\CacheException
         */
        private static function exchangeableGet(string $tokenType, bool $exchangeForbidden = false): ?string
        {
            $tokens = Cache::get('tokens');

            if (!empty($tokens[$tokenType])) {
                return "Bearer $tokens[$tokenType]";
            }

            if ($exchangeForbidden) {
                return null;
            }

            self::tryExchangeLegacyToken();

            return self::exchangeableGet($tokenType, true);
        }

        /**
         * @throws \Cdek\Exceptions\CacheException
         */
        public static function tryExchangeLegacyToken(): void
        {
            try {
                $token = (new LegacyTokenStorage)->getToken();
            } catch (LegacyAuthException $e) {
                return;
            }

            try {
                $api    = new CoreApi;
                $tokens = $api->shopTokensFetch(
                    $token,
                    $api->shopSync(
                        $token,
                        get_bloginfo('name'),
                        rest_url(Config::DELIVERY_NAME.'/cb'),
                        home_url(),
                        admin_url(),
                    ),
                );
            } catch (ShopRegistrationException|CoreAuthException $e) {
                return;
            }

            TokensSyncCommand::new()($tokens);
        }
    }
}
