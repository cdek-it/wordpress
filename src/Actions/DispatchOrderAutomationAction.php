<?php

namespace Cdek\Actions;

use ActionScheduler_Lock;
use Cdek\CoreApi;
use Cdek\Config;
use Cdek\Exceptions\AuthException;
use Cdek\Exceptions\CdekApiException;
use Cdek\Exceptions\CdekClientException;
use Cdek\Exceptions\CdekServerException;
use Cdek\Exceptions\ShippingMethodNotFoundException;
use Cdek\Helper;
use Cdek\Helpers\CheckoutHelper;
use Cdek\Note;
use JsonException;
use WC_Order;

class DispatchOrderAutomationAction
{
    const LOCK_TYPE = 'cdek_dispatch_order_automation_lock';

    /**
     * @param int|WC_Order   $orderId
     *
     * @throws CdekApiException
     * @throws JsonException
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

        if (
            !empty($awaitingGateways)
            &&
            in_array($order->get_payment_method(), $awaitingGateways, true)
            &&
            !$order->is_paid()
        ) {
            return;
        }

        $lock = ActionScheduler_Lock::instance();

        if ($lock->is_locked(self::LOCK_TYPE)) {
            return;
        }

        if (!$lock->set(self::LOCK_TYPE)) {
            return;
        }

        $hooks = as_get_scheduled_actions(
            [
                'hook' => Config::ORDER_AUTOMATION_HOOK_NAME,
                'args' => [
                    $order->get_id(),
                    1,
                ],
            ],
        );

        if($hooks){
            return;
        }


        try {
            (new CoreApi('common'))->getOrderById($orderId);
        } catch (AuthException|CdekServerException $e) {
            Note::send($orderId, $e->getCode() . ': ' . $e->getMessage(), true);
        } catch (CdekClientException $e) {
            if($e->getCode() === 404){
                if (as_schedule_single_action(time() + 60 * 5, Config::ORDER_AUTOMATION_HOOK_NAME, [
                    $order->get_id(),
                    1,
                ],                            'cdekdelivery')) {
                    Note::send($order->get_id(), esc_html__('Created order automation task', 'cdekdelivery'));
                }
            }else{
                Note::send($orderId, $e->getMessage(), true);
            }
        }
    }
}
