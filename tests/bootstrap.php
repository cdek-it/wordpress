<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Каждый файл в src/ начинается с `defined('ABSPATH') or exit;` в глобальном
// namespace-блоке — это выполняется уже при автозагрузке класса. Без константы
// PHP просто завершит процесс PHPUnit через exit().
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Для тестирования WooCommerce
if (!class_exists('WC_Abstract_Order', false)) {
    abstract class WC_Abstract_Order
    {
    }
}

if (!class_exists('WC_Order', false)) {
    class WC_Order extends WC_Abstract_Order
    {
    }
}
