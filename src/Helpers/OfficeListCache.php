<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    /**
     * Кэш city/points из CheckoutMapBlock::extend_cart_data() на сессию покупателя.
     * extend_cart_data() зарегистрирован на CartSchema и поэтому вызывается на КАЖДЫЙ
     * ответ Store API
     */
    class OfficeListCache
    {
        private const TTL         = 300;
        private const SESSION_KEY = 'official_cdek_office_list_cache';

        final public static function get(string $city, ?string $postcode): ?array
        {
            $session = WC()->session;

            if (is_null($session)) {
                return null;
            }

            $cache = $session->get(self::SESSION_KEY);

            if (!is_array($cache) ||
                !isset($cache['key'], $cache['expires'], $cache['data']) ||
                $cache['key'] !== self::buildKey($city, $postcode) ||
                $cache['expires'] < time()) {
                return null;
            }

            return $cache['data'];
        }

        final public static function set(string $city, ?string $postcode, array $data): void
        {
            $session = WC()->session;

            if (is_null($session)) {
                return;
            }

            $session->set(self::SESSION_KEY, [
                'key'     => self::buildKey($city, $postcode),
                'expires' => time() + self::TTL,
                'data'    => $data,
            ]);
        }

        private static function buildKey(string $city, ?string $postcode): string
        {
            return md5($city . '|' . ($postcode ?? ''));
        }
    }
}
