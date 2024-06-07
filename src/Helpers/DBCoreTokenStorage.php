<?php

namespace Cdek\Helpers;

use Cdek\Cache\FileCache;
use Cdek\CdekCoreApi;
use Cdek\Contracts\TokenStorageContract;
use Cdek\Exceptions\CdekApiException;
use Cdek\Helper;

class DBCoreTokenStorage extends TokenStorageContract
{
    private static string $tokenAdmin = '';
    private static string $tokenStatic = '';
    private static string $tokenFrontend = '';
    private static string $apiUrlString;
    private static string $frontendUrlString;
    private static string $adminUrlString;

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

        $cache = (new FileCache())->getVars();

        if (!empty($cache['end_point']['common'])) {
            return static::$apiUrlString = $cache['end_point']['common'];
        }

        return static::$apiUrlString = $this->getEndPointFromToken($this->getToken());
    }

    private function getTokenFromCache(): ?string
    {
        return !empty(self::$tokenStatic) ? self::$tokenStatic : null;
    }

    private function getTokenFromSettings(): ?string
    {
        $cache = (new FileCache())->getVars();

        if (empty($cache['tokens'])) {
            return null;
        }

        self::$tokenAdmin = $cache['tokens']['admin'];
        self::$tokenStatic = $cache['tokens']['common'];
        self::$tokenFrontend = $cache['tokens']['frontend'];
        return self::$tokenStatic;
    }

    final public function updateToken(): string
    {
        $tokenApi = $this->fetchTokenFromApi();

        self::$tokenAdmin = $tokenApi['tokens']['admin'];
        self::$tokenStatic = $tokenApi['tokens']['common'];
        self::$tokenFrontend = $tokenApi['tokens']['frontend'];

        $tokenApi['end_point']['admin'] = static::$adminUrlString = $this->getEndPointFromToken(self::$tokenAdmin);
        $tokenApi['end_point']['common'] = static::$apiUrlString = $this->getEndPointFromToken(self::$tokenStatic);
        $tokenApi['end_point']['frontend'] = static::$frontendUrlString = $this->getEndPointFromToken(self::$tokenFrontend);

        $cache = new FileCache();
        $cache->putVars($tokenApi);

        return self::$tokenStatic;
    }

    /**
     * @throws CdekApiException
     */
    final public function fetchTokenFromApi(): array
    {
        return (new CdekCoreApi)->fetchShopToken();
    }

    private function getEndPointFromToken($token)
    {
        $arToken = explode('.', $token);

        return json_decode(base64_decode($arToken[count($arToken) - 1]), true)['endpoint'];
    }

}
