<?php

declare(strict_types=1);

namespace Cdek\Actions;

use Cdek\Config;
use Cdek\CoreApi;
use Cdek\Exceptions\External\ApiException;
use Cdek\Exceptions\External\CoreAuthException;
use Cdek\Exceptions\External\HttpClientException;
use Cdek\Exceptions\External\HttpServerException;
use Cdek\Helper;
use Cdek\Helpers\ScheduleLocker;
use Cdek\Model\Order;
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
        $order = new Order($originalOrder ?? $orderId);

        $shipping = $order->getShipping();

        if ($shipping === null) {
            return;
        }

        $actualShippingMethod = $shipping->getMethod();

        if (!$actualShippingMethod->automate_orders) {
            return;
        }

        $awaitingGateways = $actualShippingMethod->automate_wait_gateways;

        if (!empty($awaitingGateways) &&
            in_array($order->payment_method, $awaitingGateways, true) &&
            !$order->isPaid()) {
            return;
        }

        if (!ScheduleLocker::instance()->set($order->id)) {
            return;
        }

        try {
            (new CoreApi)->orderGet($order->id);
        } catch (CoreAuthException|HttpServerException $e) {
            Note::send($orderId, $e->getCode().': '.$e->getMessage(), true);
        } catch (HttpClientException $e) {
            if ($e->getCode() === 404) {
                if (as_schedule_single_action(time() + 60 * 5, Config::ORDER_AUTOMATION_HOOK_NAME, [
                    $order->id,
                    1,
                ], 'cdekdelivery')) {
                    Note::send($order->id, esc_html__('Created order automation task', 'cdekdelivery'));
                }
            } else {
                Note::send($order->id, $e->getMessage(), true);
            }
        }
    }
}
