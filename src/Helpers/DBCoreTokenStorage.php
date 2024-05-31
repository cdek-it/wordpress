<?php

namespace Cdek\Helpers;

use Cdek\Cache\FileCache;
use Cdek\CdekCoreApi;
use Cdek\Contracts\TokenStorageContract;
use Cdek\Exceptions\CdekApiException;
use Cdek\Helper;

class DBCoreTokenStorage extends TokenStorageContract
{
    const CACHE_FILE_NAME = '.cache';
    private static string $tokenStatic = '';
    private static int $tokenExpStatic = 0;

    final public static function flushCache(): void
    {
        Helper::getActualShippingMethod()->update_option('token', null);
    }

    final public function getToken(): string
    {
        $token = $this->getTokenFromCache();

        if (empty($token)) {
            $token = $this->getTokenFromSettings();
        }

        if (empty($token)) {
            $token = $this->updateToken();
        }

        return 'Bearer ' . $token;
    }

    private function getTokenFromCache(): ?string
    {
        return !empty(self::$tokenStatic) && self::$tokenExpStatic > time() ? self::$tokenStatic : null;
    }

    private function getTokenFromSettings(): ?string
    {
        $cache = (new FileCache(self::CACHE_FILE_NAME))->getVars();

        if (empty($cache['token'])) {
            return null;
        }

        $decryptToken = $cache['token'];
        self::$tokenStatic = $decryptToken;
        return $decryptToken;
    }

    /**
     * @throws \JsonException
     */
    private function getTokenExp(string $token): int
    {
        return json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), false, 512, JSON_THROW_ON_ERROR)->exp;
    }

    final public function updateToken(): string
    {
        $tokenApi     = $this->fetchTokenFromApi();

        $cache = new FileCache(self::CACHE_FILE_NAME);
        $cache->putVars(
            [
                'token' => $tokenApi
            ]
        );

        self::$tokenStatic    = $tokenApi;
        self::$tokenExpStatic = $this->getTokenExp($tokenApi);
        return $tokenApi;
    }

    /**
     * @throws CdekApiException
     */
    final public function fetchTokenFromApi(): string
    {
        return (new CdekCoreApi)->fetchShopToken();
    }

}
