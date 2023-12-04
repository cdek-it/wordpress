<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Helper;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\MetaKeys;
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\Tariff;
    use Exception;
    use WC_Order;

    class ProcessWoocommerceOrderAction
    {
        /**
         * @throws \WC_Data_Exception
         */
        public function __invoke(int $orderId, WC_Order $order): void
        {
            if (!CheckoutHelper::isCdekShippingMethod($order)) {
                return;
            }

            $shippingMethod = CheckoutHelper::getOrderShippingMethod($order);
            $pvzCode = CheckoutHelper::getValueFromCurrentSession('pvz_code');
            $tariffId = $shippingMethod->get_meta(MetaKeys::TARIFF_CODE) ?: $shippingMethod->get_meta('tariff_code');

            if (Tariff::isTariffToOffice($tariffId)) {
                $shippingMethod->add_meta_data(MetaKeys::OFFICE_CODE, $pvzCode);
                $shippingMethod->save_meta_data();
            }

            $instance = $shippingMethod->get_data()['instance_id'];
            $instance = empty($instance) ? null : $instance;

            if (Helper::getActualShippingMethod($instance)
                      ->get_option('automate_orders') === 'yes') {
                wp_schedule_single_event(time() + 1, Config::ORDER_AUTOMATION_HOOK_NAME, [$order->get_id(),
                                                                                                   1]);
            }
        }
    }
}
