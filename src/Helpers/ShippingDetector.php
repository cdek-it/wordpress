<?php

declare(strict_types=1);

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Config;
    use Cdek\Traits\CanBeCreated;

    class ShippingDetector
    {
        use CanBeCreated;

        private bool $shippingEmpty = false;
        private ?string $shippingMethod = null;

        public function __construct()
        {
            if(WC()->cart === false || WC()->cart->needs_shipping() === false) {
                return;
            }

            $shippingMethods = WC()->session->get('chosen_shipping_methods');

            if ( empty($shippingMethods[0]) ) {
                $this->shippingEmpty = true;
                return;
            }

            $shippingMethodIdSelected = $shippingMethods[0];

            if ( strpos($shippingMethodIdSelected, Config::DELIVERY_NAME) === false ) {
                return;
            }

            $this->shippingMethod = $shippingMethodIdSelected;
        }

        public function getShipping(): ?string
        {
            return $this->shippingMethod;
        }

        public function isShippingEmpty(): bool
        {
            return $this->shippingEmpty;
        }
    }
}
