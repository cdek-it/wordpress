<?php

namespace Cdek\Helpers;

use Cdek\Cache\FileCache;
use Cdek\CdekCoreApi;
use Cdek\Contracts\TokenStorageContract;
use Cdek\Exceptions\CdekApiException;
use Cdek\Helper;

class DBCoreTokenStorage extends TokenStorageContract
{
    private static string $tokenStatic = '';
    private static string $apiUrlString = '';

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

    public function getPath()
    {
        if(isset(static::$apiUrlString)){
            return static::$apiUrlString;
        }

        $token = $this->getToken();

        $arToken = explode('.', $token);

        return json_decode(base64_decode($arToken[count($arToken) - 1]))['token'];
    }

    private function getTokenFromCache(): ?string
    {
        return !empty(self::$tokenStatic) ? self::$tokenStatic : null;
    }

    private function getTokenFromSettings(): ?string
    {
        $cache = (new FileCache(FileCache::CACHE_FILE_NAME))->getVars();

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

        $cache = new FileCache(FileCache::CACHE_FILE_NAME);
        $cache->putVars(
            [
                'token' => $tokenApi,
            ],
        );

        self::$tokenStatic    = $tokenApi;
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
