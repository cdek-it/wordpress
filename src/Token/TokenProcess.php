<?php

namespace Cdek\Token;

abstract class TokenProcess
{
    const CIPHER = 'AES-256-CBC';

    protected abstract function getToken();

    protected abstract function updateToken();

    protected abstract function fetchTokenFromApi();

    protected function encryptToken($token, $clientId) {
        return openssl_encrypt($token, self::CIPHER, $clientId);
    }

    protected function decryptToken($token, $clientId) {
        return openssl_decrypt($token, self::CIPHER, $clientId);
    }
}
