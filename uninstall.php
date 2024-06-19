<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

if (!class_exists(\Cdek\Uninstaller::class)) {
    trigger_error('CDEKDelivery not fully installed! Please install with Composer or download full release archive.',
                  E_USER_ERROR);
}

(new \Cdek\Uninstaller)();
