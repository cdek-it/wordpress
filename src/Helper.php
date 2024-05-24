<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use Cdek\Exceptions\PhoneNotValidException;
    use Cdek\Model\Tariff;
    use DateTime;
    use libphonenumber\PhoneNumberUtil;
    use RuntimeException;
    use Throwable;
    use function WC;

    class Helper
    {
        public static function enqueueScript(
            string $handle,
            string $fileName,
            bool $hasStyles = false,
            bool $justRegister = false
        ): void {
            $script_asset_path = Loader::getPluginPath()."/build/$fileName.asset.php";

            $script_asset = file_exists($script_asset_path) ? require $script_asset_path : [
                'dependencies' => [],
                'version'      => Loader::getPluginVersion(),
            ];

            if ($justRegister) {
                wp_register_script($handle, Loader::getPluginUrl()."build/$fileName.js", $script_asset['dependencies'],
                                   $script_asset['version'], true);
            } else {
                wp_enqueue_script($handle, Loader::getPluginUrl()."build/$fileName.js", $script_asset['dependencies'],
                                  $script_asset['version'], true);
            }

            if ($hasStyles) {
                wp_enqueue_style($handle, Loader::getPluginUrl()."build/$fileName.css", [], Loader::getPluginVersion());
            }

            wp_set_script_translations($handle, 'cdekdelivery', Loader::getPluginPath().'lang');
        }

        public static function getActualShippingMethod(?int $instanceId = null): CdekShippingMethod
        {
            if (!is_null($instanceId)) {
                return new CdekShippingMethod($instanceId);
            }

            if (isset(WC()->cart)) {
                try {
                    $methods = wc_get_shipping_zone(WC()->cart->get_shipping_packages()[0])->get_shipping_methods(true);

                    foreach ($methods as $method) {
                        if ($method instanceof CdekShippingMethod) {
                            return $method;
                        }
                    }
                } catch (Throwable $e) {
                }
            }

            return WC()->shipping->load_shipping_methods()[Config::DELIVERY_NAME];
        }

        public static function getServices($deliveryMethod, $tariffId)
        {
            $serviceBanAttachmentInspectionEnabled
                              = $deliveryMethod->get_option('services_ban_attachment_inspection') === 'yes';
            $serviceTrying    = $deliveryMethod->get_option('services_trying_on') === 'yes';
            $servicePartDevil = $deliveryMethod->get_option('services_part_deliv') === 'yes';
            $serviceList      = [];

            if (!Tariff::isTariffToPostamat($tariffId) && Tariff::isTariffModeIM($tariffId)) {
                if ($serviceBanAttachmentInspectionEnabled && !$serviceTrying && !$servicePartDevil) {
                    $serviceList[] = [
                        'code' => 'BAN_ATTACHMENT_INSPECTION',
                    ];
                }

                if (!$serviceBanAttachmentInspectionEnabled && $serviceTrying) {
                    $serviceList[] = [
                        'code' => 'TRYING_ON',
                    ];
                }

                if (!$serviceBanAttachmentInspectionEnabled && $servicePartDevil) {
                    $serviceList[] = [
                        'code' => 'PART_DELIV',
                    ];
                }
            }

            return $serviceList;
        }

        public static function getCdekOrderStatuses(?string $uuid): array
        {
            if (!$uuid) {
                throw new RuntimeException('[CDEKDelivery] Статусы не найдены. Некорректный uuid заказа.');
            }
            $api           = new CdekApi;
            $orderInfoJson = $api->getOrder($uuid);
            $orderInfo     = json_decode($orderInfoJson, true);
            $statusName    = [];
            if (!isset($orderInfo['entity']['statuses'])) {
                throw new RuntimeException('[CDEKDelivery] Статусы не найдены. Заказ не найден.');
            }

            foreach ($orderInfo['entity']['statuses'] as $status) {
                $dateTime      = DateTime::createFromFormat('Y-m-d\TH:i:sO', $status['date_time']);
                $formattedDate = $dateTime->format('y.m.d H:i:s');
                $statusName[]  = ['time' => $formattedDate, 'name' => $status['name'], 'code' => $status['code']];
            }

            return $statusName;
        }

        public static function getCdekActionOrderAvailable(array $cdekStatuses): bool
        {
            return !($cdekStatuses[0]['code'] !== 'CREATED' && $cdekStatuses[0]['code'] !== 'INVALID');
        }

        public static function validateCdekPhoneNumber(string $shippingRecipientPhone, string $countryCode = null): void
        {
            $phoneNumUtil = PhoneNumberUtil::getInstance();
            if (!$phoneNumUtil->isValidNumber($phoneNumUtil->parse($shippingRecipientPhone, $countryCode))) {
                throw new PhoneNotValidException($shippingRecipientPhone, $countryCode);
            }
        }
    }
}
