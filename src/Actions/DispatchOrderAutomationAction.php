<?php

namespace Cdek\Actions;

use Cdek\Config;
use Cdek\Helper;
use Cdek\Helpers\CheckoutHelper;
use Cdek\Note;
use WC_Order;

class DispatchOrderAutomationAction
{
    public function __invoke(int $orderId, $posted_data, WC_Order $order): void
    {
        $shipping = CheckoutHelper::getOrderShippingMethod($order);
        if ($shipping->get_method_id() !== Config::DELIVERY_NAME) {
            return;
        }

        if (Helper::getActualShippingMethod($shipping->get_instance_id())->get_option('automate_orders') !== 'yes') {
            return;
        }

        $result = wp_schedule_single_event(time() + 60 * 5, Config::ORDER_AUTOMATION_HOOK_NAME, [
            $orderId,
            1,
        ]);

        if ($result) {
            Note::send($orderId, __('Order automation has been scheduled', 'cdek_official'));
        } else {
            Note::send($orderId,
                       __('Order automation failed with error ', 'cdek_official').$result->get_error_message());
        }
    }
}
