<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Exceptions\CacheException;

    class Cache
    {
        private const CACHE_FILE_NAME = '.cdekdelivery.php';
        private static ?array $store = null;

        public static function clear(): void
        {
            unlink(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.self::CACHE_FILE_NAME);
            self::$store = null;
        }

        /**
         * @param  string  $key
         * @param  callable  $callback
         *
         * @return mixed|null
         *
         * @throws \Cdek\Exceptions\CacheException
         * @noinspection MissingReturnTypeInspection
         */
        public static function remember(string $key, callable $callback)
        {
            if (self::has($key)) {
                return self::get($key);
            }

            $value = $callback();

            self::put($key, $value);

            return $value;
        }

        public static function has(string $key): bool
        {
            if (self::$store === null && !self::loadCache()) {
                return false;
            }

            return isset(self::$store[$key]);
        }

        private static function loadCache(): bool
        {
            if (!file_exists(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.self::CACHE_FILE_NAME)) {
                return false;
            }

            self::$store = require(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.self::CACHE_FILE_NAME);

            return true;
        }

        /**
         * @param  string  $key
         *
         * @return mixed|null
         * @noinspection MissingReturnTypeInspection
         */
        public static function get(string $key)
        {
            if (self::$store === null && !self::loadCache()) {
                return null;
            }

            return self::$store[$key] ?? null;
        }

        /**
         * @param  string|array  $key
         * @param  mixed|null  $value
         *
         * @throws \Cdek\Exceptions\CacheException
         * @noinspection MissingParameterTypeDeclarationInspection
         */
        public static function put($key, $value = null): void
        {
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    self::$store[$k] = $v;
                }
            } else {
                self::$store[$key] = $value;
            }

            if (file_exists(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.self::CACHE_FILE_NAME)) {
                if (!is_writable(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.self::CACHE_FILE_NAME)) {
                    throw new CacheException(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.self::CACHE_FILE_NAME);
                }
            } elseif (!is_writable(WP_CONTENT_DIR)) {
                throw new CacheException(WP_CONTENT_DIR);
            }

            $fp = fopen(WP_CONTENT_DIR.DIRECTORY_SEPARATOR.self::CACHE_FILE_NAME, 'wb');

            fwrite($fp, '<?php defined("ABSPATH") or exit; return '.var_export(self::$store, true).';'.PHP_EOL);
            fclose($fp);
        }
    }
}
