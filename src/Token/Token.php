<?php

namespace Cdek\Token;

use Cdek\CdekApi;
use Cdek\Exceptions\CdekApiException;
use Cdek\Helper;

class Token extends TokenProcess
{
    private static string $tokenStatic = '';
    private static int $tokenExpStatic = 0;

    public function getToken(): string {
        $token = $this->getTokenFromCache();

        if (empty($token)) {
            $token = $this->getTokenFromSettings();
        }

        if (empty($token)) {
            $token = $this->updateToken();
        }

        return 'Bearer ' . $token;
    }

    private function getTokenFromCache(): string
    {
        if (!empty(self::$tokenStatic) && self::$tokenExpStatic > time()) {
            return self::$tokenStatic;
        }
        return '';
    }

    private function getTokenFromSettings(): string
    {
        $tokenSetting = Helper::getActualShippingMethod()->get_option('token');
        if (!empty($tokenSetting)) {
            $decryptToken =
                $this->decryptToken($tokenSetting, Helper::getActualShippingMethod()->get_option('client_id'));
            if ($decryptToken !== false) {
                $tokenExpSetting = $this->getTokenExp($decryptToken);
                if ($tokenExpSetting > time()) {
                    self::$tokenStatic = $decryptToken;
                    self::$tokenExpStatic = $tokenExpSetting;
                    return $decryptToken;
                }
            }
        }
        return '';
    }

    public function updateToken(): string {
        try {
            $tokenApi = $this->fetchTokenFromApi();
            $clientId = Helper::getActualShippingMethod()->get_option('client_id');
            $tokenEncrypt = $this->encryptToken($tokenApi, $clientId);
            Helper::getActualShippingMethod()->update_option('token', $tokenEncrypt);
            self::$tokenStatic = $tokenApi;
            self::$tokenExpStatic = $this->getTokenExp($tokenApi);
            return $tokenApi;
        } catch (CdekApiException $exception) {
            //
        }

        return '';
    }

    /**
     * @throws \Cdek\Exceptions\CdekApiException
     */
    public function fetchTokenFromApi() {
        $cdekApi = new CdekApi();
        $body = $cdekApi->fetchToken();
        if ($body === null || property_exists($body, 'error')) {
            throw new CdekApiException('[CDEKDelivery] Failed to get the token. ' . $body->error_description, 'cdek_error.token.auth', [], true);
        }
        return $body->access_token;
    }

    private function getTokenExp($token)
    {
        $tokenData = explode('.', $token)[1];
        $tokenObj = json_decode(base64_decode(strtr($tokenData, '-_', '+/')));
        return $tokenObj->exp;
    }
}
