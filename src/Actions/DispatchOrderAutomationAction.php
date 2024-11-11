<?php

declare(strict_types=1);

namespace Cdek\Actions;

use Cdek\Config;
use Cdek\CoreApi;
use Cdek\Exceptions\External\ApiException;
use Cdek\Exceptions\External\CoreAuthException;
use Cdek\Exceptions\External\HttpClientException;
use Cdek\Exceptions\External\HttpServerException;
use Cdek\Exceptions\ShippingMethodNotFoundException;
use Cdek\Helper;
use Cdek\Helpers\CheckoutHelper;
use Cdek\Helpers\ScheduleLocker;
use Cdek\Note;
use WC_Order;

class DispatchOrderAutomationAction
{

    /**
     * @param  int|WC_Order  $orderId
     *
     * @throws ApiException
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

        $actualShippingMethod = Helper::getActualShippingMethod((int)$shipping->get_instance_id());

        if ($actualShippingMethod->get_option('automate_orders') !== 'yes') {
            return;
        }

        $awaitingGateways = $actualShippingMethod->get_option('automate_wait_gateways', []);

        if (!empty($awaitingGateways) &&
            in_array($order->get_payment_method(), $awaitingGateways, true) &&
            !$order->is_paid()) {
            return;
        }

        if (!ScheduleLocker::instance()->set($order->get_id())) {
            return;
        }

        try {
            (new CoreApi)->getOrderById($orderId);
        } catch (CoreAuthException|HttpServerException $e) {
            Note::send($orderId, $e->getCode().': '.$e->getMessage(), true);
        } catch (HttpClientException $e) {
            if ($e->getCode() === 404) {
                if (as_schedule_single_action(time() + 60 * 5, Config::ORDER_AUTOMATION_HOOK_NAME, [
                    $order->get_id(),
                    1,
                ], 'cdekdelivery')) {
                    Note::send($order->get_id(), esc_html__('Created order automation task', 'cdekdelivery'));
                }
            } else {
                Note::send($orderId, $e->getMessage(), true);
            }
        }
    }
}
