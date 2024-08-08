<?php

namespace Cdek\Actions;

use Cdek\Config;
use Cdek\Exceptions\ShippingMethodNotFoundException;
use Cdek\Helper;
use Cdek\Helpers\CheckoutHelper;
use Cdek\Note;
use WC_Order;

class DispatchOrderAutomationAction
{

    /**
     * @param  int|WC_Order  $orderId
     */
    public function __invoke($orderId, $postedData = null, ?WC_Order $originalOrder = null): void
    {
        $order = $originalOrder ?? (is_int($orderId) ? wc_get_order($orderId) : $orderId);

        assert($order instanceof WC_Order, 'Order must be instance of WC_Order');

        try {
            $shipping = CheckoutHelper::getOrderShippingMethod($order);
        } catch (ShippingMethodNotFoundException $exception) {
            return;
        }

        if ($shipping->get_method_id() !== Config::DELIVERY_NAME) {
            return;
        }

        $actualShippingMethod = Helper::getActualShippingMethod($shipping->get_instance_id());

        if ($actualShippingMethod->get_option('automate_orders') !== 'yes') {
            return;
        }

        $awaitingGateways = $actualShippingMethod->get_option('automate_wait_gateways', []);

        if (!empty($awaitingGateways) &&
            in_array($order->get_payment_method(), $awaitingGateways, true) &&
            !$order->is_paid()) {
            return;
        }

        if (as_schedule_single_action(time() + 60 * 5, Config::ORDER_AUTOMATION_HOOK_NAME, [
            $order->get_id(),
            1,
        ],                            'cdekdelivery')) {
            Note::send($order->get_id(), esc_html__('Created order automation task', 'cdekdelivery'));
        }
    }
}
