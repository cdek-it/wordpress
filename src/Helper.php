<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Throwable;
    use WC_Shipping_Method;
    use function WC;

    class Helper {
        public static function enqueueScript(string $handle, string $fileName, bool $hasStyles = false): void {
            $script_asset_path = Loader::getPluginPath()."/build/$fileName.asset.php";

            $script_asset = file_exists($script_asset_path) ? require $script_asset_path : [
                'dependencies' => [],
                'version'      => Loader::getPluginVersion(),
            ];

            wp_enqueue_script($handle, Loader::getPluginUrl()."build/$fileName.js", $script_asset['dependencies'],
                $script_asset['version'], true);

            if ($hasStyles) {
                wp_enqueue_style($handle, Loader::getPluginUrl()."build/$fileName.css", [], Loader::getPluginVersion());
            }
        }

        public static function getActualShippingMethod(?int $instanceId = null): WC_Shipping_Method {
            if (!is_null($instanceId)) {
                return new CdekShippingMethod($instanceId);
            }

            if (isset(WC()->cart)) {
                try{
                    $methods = wc_get_shipping_zone(WC()->cart->get_shipping_packages()[0])->get_shipping_methods(true);

                    foreach ($methods as $method) {
                        if ($method instanceof CdekShippingMethod) {
                            return $method;
                        }
                    }
                } catch (Throwable $e) {
                }
            }

            return WC()->shipping->load_shipping_methods()['official_cdek'];
        }
    }
}
