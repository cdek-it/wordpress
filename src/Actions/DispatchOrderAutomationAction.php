<?php

namespace Cdek\Actions;

use Cdek\Config;
use Cdek\Exceptions\ShippingMethodNotFoundException;
use Cdek\Helper;
use Cdek\Helpers\CheckoutHelper;
use WC_Order;

class DispatchOrderAutomationAction
{

    /**
     * @param int|WC_Order $orderId
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
        if (Helper::getActualShippingMethod($shipping->get_instance_id())->get_option('automate_orders') !== 'yes') {
            return;
        }

        if ($order->get_payment_method() !== 'cod' && !$order->is_paid()) {
            return;
        }

        as_schedule_single_action(time() + 60 * 5, Config::ORDER_AUTOMATION_HOOK_NAME, [
            $order->get_id(),
            1,
        ], 'cdekdelivery');
    }
}
