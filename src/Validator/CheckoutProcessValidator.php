<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Validator {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Helper;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\Model\Tariff;
    use Throwable;

    class CheckoutProcessValidator
    {

        public function __invoke(): void
        {
            if (!WC()->cart->needs_shipping()) {
                return;
            }

            $api = new CdekApi;

            $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods')[0];

            if (strpos($shippingMethodIdSelected, Config::DELIVERY_NAME) === false) {
                return;
            }

            $city    = CheckoutHelper::getValueFromCurrentSession('city');
            $state   = CheckoutHelper::getValueFromCurrentSession('postcode');
            $country = CheckoutHelper::getValueFromCurrentSession('country');
            $phone   = CheckoutHelper::getValueFromCurrentSession('phone');

            $cityCode = $api->getCityCode($city, $state);
            if ($cityCode === -1) {
                wc_add_notice(      sprintf(/* translators: 1: Name of a city 2: ZIP code */ esc_html__('Failed to determine locality in %1$s %2$s',
                                                                                                        'cdekdelivery'),
                    $city, $state), 'error');
            }

            $tariffCode = explode(':', $shippingMethodIdSelected)[1];
            if (Tariff::isTariffToOffice($tariffCode)) {
                $pvzCode = CheckoutHelper::getValueFromCurrentSession('pvz_code');
                if (empty($pvzCode)) {
                    wc_add_notice(esc_html__('Order pickup point not selected.', 'cdekdelivery'), 'error');
                }
            } elseif (empty(CheckoutHelper::getValueFromCurrentSession('address_1'))) {
                wc_add_notice(esc_html__('No shipping address.', 'cdekdelivery'), 'error');
            }

            try {
                Helper::validateCdekPhoneNumber($phone, $country);
            } catch (Throwable $e) {
                wc_add_notice($e->getMessage(), 'error');
            }
        }
    }
}
