<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\Helpers\ShippingDetector;
    use Cdek\Model\Order;
    use Cdek\ShippingMethod;
    use WC_Cart;
    use WC_Product;

    class CheckoutItemPriceAction
    {
        private static bool $mutex = false;

        public function __invoke(WC_Cart $cart)
        {
            if ( is_admin() && !defined( 'DOING_AJAX' ) ){
                return;
            }

            $shippingDetector = ShippingDetector::new();

            if( $shippingDetector->getShipping() === null ) {
                return;
            }

            $method = ShippingMethod::factory();

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
