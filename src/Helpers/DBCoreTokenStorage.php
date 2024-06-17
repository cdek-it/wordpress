<?php

namespace {
    defined('ABSPATH') or exit;
}


namespace Cdek\Helpers {

    use Cdek\Cache\FileCache;
    use Cdek\CdekCoreApi;
    use Cdek\Contracts\TokenStorageContract;
    use Cdek\Exceptions\CdekApiException;
    use Cdek\Exceptions\CdekScheduledTaskException;

    class DBCoreTokenStorage extends TokenStorageContract
    {
        private static ?string $tokenAdmin = null;
        private static ?string $tokenStatic = null;
        private static ?string $tokenFrontend = null;
        private static string $apiUrlString;
        private static string $frontendUrlString;
        private static string $adminUrlString;

        /**
         * @throws CdekApiException
         * @throws CdekScheduledTaskException
         * @throws \JsonException
         */
        final public function getToken(): string
        {
            $token = self::$tokenStatic;

            if (empty($token)) {
                $token = $this->getTokenFromCache();
            }

            if (empty($token)) {
                $token = $this->updateToken();
            }

            return 'Bearer ' . $token;
        }

        /**
         * @throws CdekApiException
         * @throws CdekScheduledTaskException
         * @throws \JsonException
         */
        public function getOrRefreshApiPath(): string
        {
            if(isset(static::$apiUrlString)){
                return static::$apiUrlString;
            }

            $cache = FileCache::getVars();

            if (!empty($cache['endpoint']['common'])) {
                return static::$apiUrlString = $cache['endpoint']['common'];
            }

            $this->updateToken();

            if(!isset(static::$apiUrlString)){
                throw new CdekScheduledTaskException(
                    '[CDEKDelivery] Failed to get token path',
                    'cdek_error.token.path'
                );
            }

            return static::$apiUrlString;
        }

        private function getTokenFromCache(): ?string
        {
            $cache = FileCache::getVars();

            if (empty($cache['tokens'])) {
                return null;
            }

            self::$tokenAdmin = $cache['tokens']['admin'];
            self::$tokenStatic = $cache['tokens']['common'];
            self::$tokenFrontend = $cache['tokens']['frontend'];
            return self::$tokenStatic;
        }

        /**
         * @throws CdekApiException
         * @throws CdekScheduledTaskException
         * @throws \JsonException
         */
        final public function updateToken(): string
        {
            $tokenApi = $this->fetchTokenFromApi();

            self::$tokenAdmin = $tokenApi['tokens']['admin'];
            self::$tokenStatic = $tokenApi['tokens']['common'];
            self::$tokenFrontend = $tokenApi['tokens']['frontend'];

            $tokenApi['endpoint']['admin'] = static::$adminUrlString = $this->getEndPointFromToken(self::$tokenAdmin);
            $tokenApi['endpoint']['common'] = static::$apiUrlString = $this->getEndPointFromToken(self::$tokenStatic);
            $tokenApi['endpoint']['frontend'] = static::$frontendUrlString = $this->getEndPointFromToken(self::$tokenFrontend);

            FileCache::putVars($tokenApi);

            return self::$tokenStatic;
        }

        /**
         * @throws CdekApiException
         * @throws CdekScheduledTaskException
         * @throws \JsonException
         */
        final public function fetchTokenFromApi(): array
        {
            return (new CdekCoreApi)->fetchShopToken();
        }

        private function getEndPointFromToken(string $token): ?string
        {
            $arToken = explode('.', $token);

            return json_decode(base64_decode($arToken[count($arToken) - 1]), true)['endpoint'] ?? null;
        }

    }
}
