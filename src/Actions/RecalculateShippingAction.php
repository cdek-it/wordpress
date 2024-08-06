<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\Exceptions\TariffNotAvailableException;
    use Cdek\MetaKeys;
    use Cdek\Model\OrderMetaData;
    use WC_Abstract_Order;
    use WC_Order;

    class RecalculateShippingAction
    {

        private static bool $addedError = false;

        public function __invoke(bool $and_taxes, WC_Abstract_Order $order): void
        {
            if (!isset($_POST['action']) ||
                $_POST['action'] !== 'woocommerce_calc_line_taxes' ||
                !($order instanceof WC_Order) ||
                !is_ajax() ||
                !is_admin()) {
                return;
            }

            try {
                foreach ($order->get_shipping_methods() as $shipping) {
                    $calculator = new CalculateDeliveryAction($shipping->get_instance_id());
                    $calculator([
                                               'contents'    => array_map(static fn($el) => [
                                                   'data'     => $el->get_product(),
                                                   'quantity' => $el->get_quantity(),
                                               ], $order->get_items()),
                                               'destination' => [
                                                   'city'     => $order->get_shipping_city(),
                                                   'country'  => $order->get_shipping_country(),
                                                   'postcode' => $order->get_shipping_postcode(),
                                               ],
                                           ], isset(OrderMetaData::getMetaByOrderId($order->get_id())['office_code']));

                    $rate = $calculator->getTariffRate((int) ($shipping->get_meta(MetaKeys::TARIFF_CODE) ?:
                        $shipping->get_meta('tariff_code')));
                    $shipping->set_total($rate['cost']);
                    $shipping->set_name($rate['label']);
                    $shipping->set_meta_data([
                                                 MetaKeys::WIDTH  => $rate['width'],
                                                 MetaKeys::HEIGHT => $rate['height'],
                                                 MetaKeys::LENGTH => $rate['length'],
                                             ]);
                }
            } catch (TariffNotAvailableException $e) {
                if (self::$addedError) {
                    return;
                }

                self::$addedError = true;
                $availableTariffs = implode(', ', $e->getData());
                echo '<div class="cdek-error">' .
                     sprintf(
                         /* translators: %s tariff codes  */
                         esc_html__('The selected CDEK tariff is not available with the specified parameters. Available tariffs with codes: %s', 'cdekdelivery'),
                         esc_html($availableTariffs)
                     ) .
                     '</div>';
            }
        }
    }
}
