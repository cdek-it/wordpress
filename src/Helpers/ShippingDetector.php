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

        public function isCdekShipping(): bool
        {
            if($this->needsCart && WC()->cart === false) {
                return false;
            }

            if(WC()->cart->needs_shipping() === false) {
                return false;
            }

            $shippingMethods = WC()->session->get('chosen_shipping_methods');

            if ( empty($shippingMethods[0]) ) {
                return $this->shippingCanBeEmpty;
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

            if($this->isCdekShipping()){
                return $this->shippingMethod;
            }

            return null;
        }

        public function setNeedsCart(bool $apply = true): self
        {
            $this->needsCart = $apply;
            return $this;
        }

        public function setShippingCanBeEmpty(bool $apply = true): self
        {
            $this->shippingCanBeEmpty = $apply;
            return $this;
        }
    }
}
