<?php
/**
 * Plugin Name: CDEKDelivery
 * Plugin URI: https://www.cdek.ru/ru/integration/modules/33
 * Description: Интеграция доставки CDEK
 * Version: dev
 * Requires at least: 6.0
 * Text Domain: official-cdek
 * Domain Path: /lang
 * Requires PHP: 7.4
 * Author: CDEK IT
 * WC requires at least: 6.9
 * WC tested up to: 8.0
 * License: GPLv3
 */

defined('ABSPATH') or exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

if (!class_exists(\Cdek\Loader::class)) {
    trigger_error('CDEKDelivery not fully installed! Please install with Composer or download full release archive.',
                  E_USER_ERROR);
}

(new \Cdek\Loader)(__FILE__);
