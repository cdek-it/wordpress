<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\Helpers\CheckoutHelper;
    use Cdek\ShippingMethod;
    use WC_Cart;
    use WC_Product;

    class CheckoutItemPriceAction
    {
        private static bool $mutex = false;

        public function __invoke(WC_Cart $cart): void
        {
            $rate = CheckoutHelper::getSelectedShippingRate($cart);

            if(is_null($rate)) {
                return;
            }

            $session = WC()->session;

            if (is_null($session)) {
                return;
            }

            if ($session->get('chosen_payment_method') !== 'cod') {
                return;
            }

            $method = ShippingMethod::factory($rate->get_instance_id());

            $shouldPay = $method->percentcod;

            if (empty($shouldPay) || self::$mutex) {
                return;
            }

            self::$mutex = true;

            foreach ( $cart->get_cart() as $cartItem ){
                /** @var WC_Product $product */
                $product = $cartItem['data'];

                $product->set_price(
                    (float)(($shouldPay / 100) * $product->get_price())
                );
            }
        }
    }
}
