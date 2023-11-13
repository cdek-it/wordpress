<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Helper;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\Model\OrderMetaData;
    use Cdek\Model\Tariff;
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
            $tariffId = $shippingMethod->get_meta('tariff_code');
            $currency = function_exists('wcml_get_woocommerce_currency_option') ? get_woocommerce_currency() : 'RUB';

            if (Tariff::isTariffToOffice($tariffId)) {
                $api = new CdekApi;
                $pvzAddress = $api->getOffices(['code' => $pvzCode]);
                $shippingMethod->add_meta_data('pvz', $pvzCode . ' (' . json_decode($pvzAddress)[0]->location->address . ')');
                $shippingMethod->save_meta_data();
            }

            $data = [
                'pvz_code'  => $pvzCode,
                'currency'  => $currency,
            ];

            OrderMetaData::addMetaByOrderId($order->get_id(), $data);

            $instance = $shippingMethod->get_data()['instance_id'];
            if (empty($instance)) {
                $instance = null;
            }

            if (Helper::getActualShippingMethod($instance)
                      ->get_option('automate_orders') === 'yes') {
                wp_schedule_single_event(time() + 1, Config::ORDER_AUTOMATION_HOOK_NAME, [$order->get_id(),
                                                                                                   1]);
            }
        }
    }
}
