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

        private bool $needsCart = false;
        private bool $shippingCanBeEmpty = false;
        private ?string $shippingMethod = null;

        public function initShippingAndDetect(): ?bool
        {
            if($this->needsCart && WC()->cart === false) {
                return null;
            }

            if(WC()->cart->needs_shipping() === false) {
                return null;
            }

            $shippingMethods = WC()->session->get('chosen_shipping_methods');

            if ( empty($shippingMethods[0]) ) {
                return $this->shippingCanBeEmpty ? true : null;
            }

            $shippingMethodIdSelected = $shippingMethods[0];

            if ( strpos($shippingMethodIdSelected, Config::DELIVERY_NAME) === false ) {
                return false;
            }

            $this->shippingMethod = $shippingMethodIdSelected;

            return true;
        }

        public function getShipping(): ?string
        {
            if($this->shippingMethod !== null) {
                return $this->shippingMethod;
            }

            if($this->initShippingAndDetect()){
                return $this->shippingMethod;
            }

            return null;
        }

        public function onNeedsCartAndEmptyShipping(): self
        {
            $this->needsCart = true;
            $this->shippingCanBeEmpty = true;
            return $this;
        }
    }
}
