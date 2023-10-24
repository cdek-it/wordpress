<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek {

    use WC_Shipping_Method;
    use function WC;

    class Helper
    {
        public static function getActualShippingMethod(?int $instanceId = null): WC_Shipping_Method
        {
            if (!is_null($instanceId)) {
                return new CdekShippingMethod($instanceId);
            }

            if (isset(WC()->cart)) {
                $methods = wc_get_shipping_zone(WC()->cart->get_shipping_packages()[0])->get_shipping_methods(true);

                foreach ($methods as $method) {
                    if ($method instanceof CdekShippingMethod) {
                        return $method;
                    }
                }
            }

            return WC()->shipping->load_shipping_methods()['official_cdek'];
        }
    }
}
