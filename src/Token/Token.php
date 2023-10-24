<?php

namespace Cdek\Token;

use Cdek\CdekApi;
use Cdek\Exceptions\ValidationException;
use Cdek\Helper;

class Token extends TokenProcess
{
    private string $clientIdFromSetting;
    private string $tokenFromSetting;
    private CdekApi $cdekApi;
    const TOKEN_EXP = 3598;

    public function __construct() {
        $this->clientIdFromSetting = Helper::getActualShippingMethod()->get_option('client_id');
        $this->tokenFromSetting = Helper::getActualShippingMethod()->get_option('token');
        $this->cdekApi = new CdekApi();
    }

    public function getToken(): string {
        $tokenExp = (int)Helper::getActualShippingMethod()->get_option('token_exp');
        if ($this->tokenFromSetting === '' || $tokenExp < time()) {
            $this->tokenFromSetting = $this->updateToken();
        }
        return 'Bearer ' . $this->decryptToken($this->tokenFromSetting, $this->clientIdFromSetting);
    }

    protected function updateToken(): string {
        $token = $this->fetchTokenFromApi();
        $tokenEncrypt = $this->encryptToken($token, $this->clientIdFromSetting);
        Helper::getActualShippingMethod()->update_option('token', $tokenEncrypt);
        Helper::getActualShippingMethod()->update_option('token_exp', time() + self::TOKEN_EXP);
        return $tokenEncrypt;
    }

    protected function fetchTokenFromApi() {
        $body = $this->cdekApi->fetchToken();
        if ($body === null || property_exists($body, 'error')) {
            throw new ValidationException('Failed to get the token');
        }
        return $body->access_token;
    }
}
