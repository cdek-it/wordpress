<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Config;

    class UrlHelper {
        public static function buildRest(
            string $route,
            array $args = [],
            string $prefix = Config::DELIVERY_NAME
        ): string {
            $prefix = substr($prefix, -1) === '/' ? $prefix : "$prefix/";

            $args['_wpnonce'] = wp_create_nonce('wp_rest');

            return add_query_arg($args, rest_url($prefix.$route));
        }
    }

}
