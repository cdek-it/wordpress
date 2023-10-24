<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\Exceptions\TariffNotAvailableException;
    use Cdek\Helpers\DeliveryCalc;
    use Cdek\Model\OrderMetaData;
    use WC_Order;

    class RecalculateShippingAction
    {

        private static bool $addedError = false;

        public function __invoke(bool $and_taxes, WC_Order $order): void
        {
            if (!isset($_POST['action']) ||
                $_POST['action'] !== 'woocommerce_calc_line_taxes' ||
                !is_ajax() ||
                !is_admin()) {
                return;
            }

            try {
                foreach ($order->get_shipping_methods() as $shipping) {
                    $calculator = new DeliveryCalc($shipping->get_instance_id());
                    $calculator->calculate([
                                               'contents'    => array_map(static fn($el) => [
                                                   'data'     => $el->get_product(),
                                                   'quantity' => $el->get_quantity(),
                                               ], $order->get_items()),
                                               'destination' => [
                                                   'city' => $order->get_shipping_city(),
                                               ],
                                           ], isset(OrderMetaData::getMetaByOrderId($order->get_id())['pvz_code']));

                    $rate = $calculator->getTariffRate($shipping->get_meta('tariff_code'));
                    $shipping->set_total($rate['cost']);
                    $shipping->set_name($rate['label']);
                    $shipping->set_meta_data([
                                                 'width'  => $rate['width'],
                                                 'height' => $rate['height'],
                                                 'length' => $rate['length'],
                                             ]);
                }
            } catch (TariffNotAvailableException $e) {
                if (self::$addedError) {
                    return;
                }

                self::$addedError = true;
                $availableTariffs = implode(', ', $e->getData());
                echo "<div class='cdek-error'>Выбранный тариф СДЭК при заданных параметрах недоступен. Доступны тарифы с кодами: $availableTariffs</div>";
            }
        }
    }
}
