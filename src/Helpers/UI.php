<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Config;
    use Cdek\Loader;

    class UI
    {
        public static function enqueueScript(
            string $handle,
            string $fileName,
            bool $hasStyles = false,
            bool $justRegister = false,
            bool $needsNonce = false
        ): void {
            $script_asset_path = Loader::getPluginPath("build/$fileName.asset.php");

            $script_asset = file_exists($script_asset_path) ? require $script_asset_path : [
                'dependencies' => [],
                'version'      => Loader::getPluginVersion(),
            ];

            if ($justRegister) {
                wp_register_script(
                    $handle,
                    Loader::getPluginUrl("build/$fileName.js"),
                    $script_asset['dependencies'],
                    $script_asset['version'],
                    true,
                );
            } else {
                wp_enqueue_script(
                    $handle,
                    Loader::getPluginUrl("build/$fileName.js"),
                    $script_asset['dependencies'],
                    $script_asset['version'],
                    true,
                );
            }

            if ($hasStyles) {
                wp_enqueue_style($handle, Loader::getPluginUrl("build/$fileName.css"), [], $script_asset['version']);
            }

            wp_set_script_translations($handle, 'cdekdelivery', Loader::getPluginPath('lang'));

            if ($needsNonce) {
                wp_localize_script($handle, 'cdek', [
                    'nonce'  => wp_create_nonce(Config::DELIVERY_NAME),
                    'prefix' => Config::DELIVERY_NAME,
                ]);
            }
        }

    }
}
