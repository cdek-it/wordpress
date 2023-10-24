<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\CdekApi;
    use Cdek\Config;
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

            $pvzInfo = CheckoutHelper::getValueFromCurrentSession('pvz_info');
            $pvzCode = CheckoutHelper::getValueFromCurrentSession('pvz_code');
            $tariffId = $shippingMethod->get_meta('tariff_code');
            $cityCode = CheckoutHelper::getValueFromCurrentSession('city_code');

            $currency = function_exists('wcml_get_woocommerce_currency_option') ? get_woocommerce_currency() : 'RUB';

            if (empty($cityCode)) {
                $pvzInfo = $order->get_billing_address_1();
                $cityCode = $api->getCityCodeByCityName($order->get_billing_city(), $order->get_billing_city());
            }
            if (empty($pvzInfo) && Tariff::isTariffToOffice($tariffId)) {
                $pvzInfo = $order->get_billing_address_1();
            }
            $cityData = $api->getCityByCode($cityCode);
            $order->set_shipping_address_1($pvzInfo);
            $order->set_shipping_city($cityData['city']);
            $order->set_shipping_state($cityData['region']);
            $order->save();

            if (Tariff::isTariffToOffice($tariffId)) {
                $shippingMethod->add_meta_data('pvz', $pvzCode . ' (' . $pvzInfo . ')');
                $shippingMethod->save_meta_data();
            }

            $data = [
                'pvz_code'     => $pvzCode,
                'city_code'    => $cityCode,
                'currency'     => $currency,
                'order_number' => '',
                'order_uuid'   => '',
            ];

            OrderMetaData::addMetaByOrderId($order->get_id(), $data);

            if ($shippingMethod->get_meta('automate_orders') === 'yes') {
                wp_schedule_single_event(time() + 1, Config::ORDER_AUTOMATION_HOOK_NAME, [$order->get_id(), 0]);
            }
        }
    }
}
