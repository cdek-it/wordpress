<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\CdekApi;
    use Cdek\Exceptions\External\LegacyAuthException;
    use Cdek\ShippingMethod;
    use JsonException;

    /**
     * @deprecated use CoreTokenStorage instead
     */
    class LegacyTokenStorage
    {
        private const CIPHER = 'AES-256-CBC';
        private static string $token = '';
        private static int $tokenExp = 0;

        final public static function flushCache(): void
        {
            ShippingMethod::factory()->update_option('token', null);
        }

        /**
         * @throws LegacyAuthException
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

            return "Bearer $token";
        }

        private function getTokenFromCache(): ?string
        {
            return !empty(self::$token) && self::$tokenExp > time() ? self::$token : null;
        }

        private function getTokenFromSettings(): ?string
        {
            $shipping = ShippingMethod::factory();
            $tokenSetting = $shipping->token;
            if (empty($tokenSetting)) {
                return null;
            }
            $decryptToken = $this->decryptToken(
                $tokenSetting,
                $shipping->client_id,
            );
            if (empty($decryptToken)) {
                return null;
            }
            $tokenExpSetting = $this->getTokenExp($decryptToken);
            if ($tokenExpSetting < time()) {
                return null;
            }
            self::$token    = $decryptToken;
            self::$tokenExp = $tokenExpSetting;

            return $decryptToken;
        }

        private function getTokenExp(string $token): int
        {
            try {
                return json_decode(
                           base64_decode(strtr(explode('.', $token)[1], '-_', '+/')),
                           true,
                           512,
                           JSON_THROW_ON_ERROR,
                       )['exp'];
            } catch (JsonException $e) {
                return 0;
            }
        }

        /**
         * @throws LegacyAuthException
         */
        final public function updateToken(): string
        {
            $tokenApi       = (new CdekApi)->fetchToken();

            $shippingMethod = ShippingMethod::factory();
            $clientId       = $shippingMethod->client_id;
            $tokenEncrypt   = $this->encryptToken($tokenApi, $clientId);
            $shippingMethod->update_option('token', $tokenEncrypt);

            self::$token    = $tokenApi;
            self::$tokenExp = $this->getTokenExp($tokenApi);

            return $tokenApi;
        }

        private function encryptToken(string $token, string $clientId): string
        {
            $iv             = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
            $encryptedToken = openssl_encrypt($token, self::CIPHER, $clientId, 0, $iv);

            return base64_encode($iv.$encryptedToken);
        }

        private function decryptToken(string $token, string $clientId): ?string
        {
            $data          = base64_decode($token);
            $iv            = substr($data, 0, openssl_cipher_iv_length(self::CIPHER));
            $encryptedData = substr($data, openssl_cipher_iv_length(self::CIPHER));

            $dt = openssl_decrypt($encryptedData, self::CIPHER, $clientId, 0, $iv);

            if(!empty($dt)){
                return $dt;
            }

            return null;
        }
    }
}
