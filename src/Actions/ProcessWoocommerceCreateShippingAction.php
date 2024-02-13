<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\Config;
    use Cdek\Helper;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\MetaKeys;
    use Cdek\Model\Tariff;
    use WC_Order;
    use WC_Order_Item_Shipping;

    class ProcessWoocommerceCreateShippingAction
    {
        /**
         * @throws \WC_Data_Exception
         * @throws \Exception
         */
        public function __invoke(WC_Order_Item_Shipping $shipping, $package_key, $package, WC_Order $order): void
        {
            if ($shipping->get_method_id() !== Config::DELIVERY_NAME) {
                return;
            }

            $pvzCode  = CheckoutHelper::getValueFromCurrentSession('pvz_code');
            $tariffId = $shipping->get_meta(MetaKeys::TARIFF_CODE) ?: $shipping->get_meta('tariff_code');

            if (Tariff::isTariffToOffice($tariffId)) {
                $shipping->add_meta_data(MetaKeys::OFFICE_CODE, $pvzCode);
                $shipping->save_meta_data();
            }

            if (Helper::getActualShippingMethod($shipping->get_instance_id())->get_option('automate_orders') ===
                'yes') {
                wp_schedule_single_event(time() + 1, Config::ORDER_AUTOMATION_HOOK_NAME, [
                    $order->get_id(),
                    1,
                ]);
            }
        }
    }
}
