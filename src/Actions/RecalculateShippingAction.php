<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\Model\Order;
    use WC_Abstract_Order;
    use WC_Order;

    class RecalculateShippingAction
    {

        private static bool $addedError = false;

        /**
         * @throws \Cdek\Exceptions\External\ApiException
         * @throws \Cdek\Exceptions\External\LegacyAuthException
         */
        public function __invoke(bool $and_taxes, WC_Abstract_Order $order): void
        {
            /** @noinspection GlobalVariableUsageInspection */
            if (!isset($_POST['action']) ||
                $_POST['action'] !== 'woocommerce_calc_line_taxes' ||
                !($order instanceof WC_Order) ||
                !is_ajax() ||
                !is_admin()) {
                return;
            }

            $orderModel = new Order($order);

            $shipping = $orderModel->getShipping();

            if ($shipping === null) {
                return;
            }

            $rates = CalculateDeliveryAction::new()([
                'contents'    => array_map(static fn($el)
                    => [
                    'data'     => $el->get_product(),
                    'quantity' => $el->get_quantity(),
                ], $order->get_items()),
                'destination' => [
                    'city'     => $order->get_shipping_city(),
                    'country'  => $order->get_shipping_country(),
                    'postcode' => $order->get_shipping_postcode(),
                ],
            ], $shipping->getInstanceId(), !empty($shipping->office ?: $orderModel->pvz_code));

            $tariff = $shipping->tariff;

            if (!array_key_exists($tariff, $rates)) {
                if (self::$addedError) {
                    return;
                }

                self::$addedError = true;
                $availableTariffs = implode(', ', array_keys($rates));
                echo '<div class="cdek-error">'.sprintf(
                    /* translators: %s tariff codes  */ esc_html__(
                        'The selected CDEK tariff is not available with the specified parameters. Available tariffs with codes: %s',
                        'cdekdelivery',
                    ),
                        esc_html($availableTariffs),
                    ).'</div>';

                return;
            }

            $shipping->updateTotal((float)$rates[$tariff]['cost']);
            $shipping->updateName($rates[$tariff]['label']);
            $shipping->width  = $rates[$tariff]['width'];
            $shipping->height = $rates[$tariff]['height'];
            $shipping->length = $rates[$tariff]['length'];
            $shipping->save();
        }
    }
}
