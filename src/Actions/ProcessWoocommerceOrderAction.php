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

            $api = new CdekApi;

            $shippingMethod = CheckoutHelper::getOrderShippingMethod($order);

            $pvzInfo = CheckoutHelper::getValueFromCurrentSession('pvz_code_address');
            $pvzCode = CheckoutHelper::getValueFromCurrentSession('pvz_code');
            $tariffId = $shippingMethod->get_meta('tariff_code');
            $cityCode = CheckoutHelper::getValueFromCurrentSession('city_code');

            $currency = function_exists('wcml_get_woocommerce_currency_option') ? get_woocommerce_currency() : 'RUB';

            if (empty($cityCode)) {
                $cityCode = $api->getCityCodeByCityName($order->get_billing_city(), $order->get_billing_city());
            }

            $cityData = $api->getCityByCode($cityCode);
            $order->set_shipping_city($cityData['city']);
            $order->set_shipping_state($cityData['region']);
            $order->save();

            if (Tariff::isTariffToOffice($tariffId)) {
                $shippingMethod->add_meta_data('pvz', $pvzCode . ' (' . $pvzInfo . ')');
                $shippingMethod->save_meta_data();
            }

            $data = [
                'pvz_code'  => $pvzCode,
                'currency'  => $currency,
            ];

            OrderMetaData::addMetaByOrderId($order->get_id(), $data);

            if (Helper::getActualShippingMethod($shippingMethod->get_data()['instance_id'])
                      ->get_option('automate_orders') === 'yes') {
                wp_schedule_single_event(time() + 1, Config::ORDER_AUTOMATION_HOOK_NAME, [$order->get_id(),
                                                                                                   1]);
            }
        }
    }
}
