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
        private ?array $shippingMethods = null;

        public function __construct()
        {
            if(empty(WC()->cart) || !WC()->cart->needs_shipping()) {
                return;
            }

            $this->shippingMethods = WC()->session->get('chosen_shipping_methods', []);
        }

        public function needShipping(): bool
        {
            return $this->shippingMethods !== null;
        }

        public function getShipping(): ?string
        {
            if($this->isShippingEmpty()){
                return null;
            }

            $shippingMethodIdSelected = $this->shippingMethods[0];

            if ( strpos($shippingMethodIdSelected, Config::DELIVERY_NAME) === false ) {
                return null;
            }

            return $shippingMethodIdSelected;
        }

        public function isShippingEmpty(): bool
        {
            return empty($this->shippingMethods[0]);
        }
    }
}
