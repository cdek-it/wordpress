<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {
    use DateTime;
    use RuntimeException;
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

        public static function getCdekOrderStatuses(mixed $uuid): array
        {
            if (!$uuid) {
                throw new RuntimeException('[CDEKDelivery] Статусы не найдены. Некорректный uuid заказа.');
            }
            $api = new CdekApi;
            $orderInfoJson = $api->getOrder($uuid);
            $orderInfo     = json_decode($orderInfoJson, true);
            $statusName = [];
            if (!isset($orderInfo['entity']['statuses'])) {
                throw new RuntimeException('[CDEKDelivery] Статусы не найдены. Заказ не найден.');
            }
            $statusName[] = ['time' => '12312', 'name' => 'qweqwe', 'code' => 'asdas'];
            foreach ($orderInfo['entity']['statuses'] as $status) {
                $dateTime = DateTime::createFromFormat('Y-m-d\TH:i:sO', $status['date_time']);
                $formattedDate = $dateTime->format('y.m.d H:i:s');
                $statusName[] = ['time' => $formattedDate, 'name' => $status['name'], 'code' => $status['code']];
            }

            return $statusName;
        }

        public static function getCdekActionOrderAvailable(array $cdekStatuses): bool
        {
            if ($cdekStatuses[0]['code'] !== 'CREATED') {
                return false;
            }
            return true;
        }

    }
}
