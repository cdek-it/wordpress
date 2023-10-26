<?php

namespace Cdek\Token;

abstract class TokenProcess
{
    const CIPHER = 'AES-256-CBC';

    abstract function getToken();

    abstract function updateToken();

    abstract function fetchTokenFromApi();

    protected function encryptToken($token, $clientId) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $encryptedToken = openssl_encrypt($token, self::CIPHER, $clientId, 0, $iv);
        return base64_encode($iv . $encryptedToken);
    }

    protected function decryptToken($token, $clientId) {
        $data = base64_decode($token);
        $iv = substr($data, 0, openssl_cipher_iv_length(self::CIPHER));
        $encryptedData = substr($data, openssl_cipher_iv_length(self::CIPHER));
        return openssl_decrypt($encryptedData, self::CIPHER, $clientId, 0, $iv);
    }
}
