<?php

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Helpers {

    use Cdek\Cache\FileCache;
    use Cdek\CoreApi;
    use Cdek\Exceptions\AuthException;
    use Cdek\Exceptions\CdekApiException;
    use Cdek\Exceptions\ShopRegistrationException;

    class CoreTokenStorage
    {
        private static array $tokens = [];
        private static array $endpoints = [];

        /**
         * @throws CdekApiException
         * @throws \JsonException
         */
        public static function getToken(string $tokenType): ?string
        {
            if (empty(self::$tokens[$tokenType])) {
                self::loadCache();
            }

            if (empty(self::$tokens[$tokenType])) {
                self::tryExchangeLegacyToken();
            }

            return empty(self::$tokens[$tokenType]) ? null : 'Bearer '.self::$tokens[$tokenType];
        }

        private static function loadCache(): void
        {
            $cache = FileCache::getVars();

            if (empty($cache['tokens'])) {
                return;
            }

            self::$tokens = $cache['tokens'];
            self::$endpoints = $cache['endpoints'];
        }

        /**
         * @throws CdekApiException
         * @throws \JsonException
         */
        public static function tryExchangeLegacyToken(): void
        {
            $legacyStorage = new DBTokenStorage;

            try {
                $token = $legacyStorage->getToken();
            } catch (AuthException $e) {
                return;
            }

            try {
                $api          = new CoreApi;
                self::$tokens = $api->fetchShopTokens($token, $api->syncShop($token));
            } catch (ShopRegistrationException|AuthException $e) {
                return;
            }

            FileCache::putVars([
                'tokens' => self::$tokens,
                'endpoints' => array_combine(array_keys(self::$tokens), array_map(
                    static fn($token) => self::getBaseUrlFromToken($token),
                    self::$tokens,
                )),
            ]);
        }

        /**
         * @throws \JsonException
         */
        private static function getBaseUrlFromToken(string $token): ?string
        {
            $arToken = explode('.', $token);

            return json_decode(base64_decode(count($arToken) - 1), true, 512, JSON_THROW_ON_ERROR)['endpoint'] ?? null;
        }

        /**
         * @throws \JsonException
         * @throws \Cdek\Exceptions\CdekApiException
         */
        public static function getEndpoint(string $tokenType = 'common'): ?string
        {
            if (empty(self::$endpoints[$tokenType])) {
                self::loadCache();
            }

            if (empty(self::$tokens[$tokenType])) {
                self::tryExchangeLegacyToken();
            }

            return empty(self::$tokens[$tokenType]) ? null : self::$endpoints[$tokenType];
        }
    }
}
