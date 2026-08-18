<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    class ShippingRatesCache
    {
        private const TTL         = 10;
        private const SESSION_KEY = 'official_cdek_rates_cache';

        final public static function get(int $instanceId, array $destination): ?array
        {
            $session = WC()->session;

            if (is_null($session)) {
                return null;
            }

            $cache = $session->get(self::SESSION_KEY);

            if (empty($cache) ||
                $cache['key'] !== self::buildKey($instanceId, $destination) ||
                $cache['expires'] < time()) {
                return null;
            }

            return $cache['rates'];
        }

        final public static function set(int $instanceId, array $destination, array $rates): void
        {
            $session = WC()->session;

            if (is_null($session)) {
                return;
            }

            $session->set(self::SESSION_KEY, [
                'key'     => self::buildKey($instanceId, $destination),
                'expires' => time() + self::TTL,
                'rates'   => $rates,
            ]);
        }

        private static function buildKey(int $instanceId, array $destination): string
        {
            return $instanceId.':'.md5((string)wp_json_encode($destination));
        }
    }
}
