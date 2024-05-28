<?php

namespace Cdek\Helpers;

use Cdek\CdekApi;
use Cdek\Contracts\TokenStorageContract;
use Cdek\Helper;
use Cdek\Exceptions\CdekApiException;

class DBTokenStorage extends TokenStorageContract
{
    private static string $tokenStatic = '';
    private static int $tokenExpStatic = 0;

    final public static function flushCache(): void
    {
        Helper::getActualShippingMethod()->update_option('token', null);
    }

    /**
     * @throws \JsonException
     * @throws \Cdek\Exceptions\CdekApiException
     */
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
        $tokenSetting = Helper::getActualShippingMethod()->get_option('token');
        if (empty($tokenSetting)) {
            return null;
        }
        $decryptToken = $this->decryptToken($tokenSetting, Helper::getActualShippingMethod()->get_option('client_id'));
        if (empty($decryptToken)) {
            return null;
        }
        $tokenExpSetting = $this->getTokenExp($decryptToken);
        if ($tokenExpSetting < time()) {
            return null;
        }
        self::$tokenStatic    = $decryptToken;
        self::$tokenExpStatic = $tokenExpSetting;
        return $decryptToken;
    }

    /**
     * @throws \JsonException
     */
    private function getTokenExp(string $token): int
    {
        return json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), false, 512, JSON_THROW_ON_ERROR)->exp;
    }

    /**
     * @throws \Cdek\Exceptions\CdekApiException
     */
    final public function updateToken(): string
    {
        $tokenApi     = $this->fetchTokenFromApi();
        $clientId     = Helper::getActualShippingMethod()->get_option('client_id');
        $tokenEncrypt = $this->encryptToken($tokenApi, $clientId);
        Helper::getActualShippingMethod()->update_option('token', $tokenEncrypt);
        self::$tokenStatic    = $tokenApi;
        self::$tokenExpStatic = $this->getTokenExp($tokenApi);
        return $tokenApi;
    }

    /**
     * @throws CdekApiException
     */
    final public function fetchTokenFromApi(): string
    {
        return (new CdekApi)->fetchToken();
    }
}
