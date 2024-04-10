<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Validator {

    use Cdek\CdekApi;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\Model\Tariff;

    class CheckoutProcessValidator
    {

        public function __invoke(): void
        {
            if (!WC()->cart->needs_shipping()) {
                return;
            }

            $api = new CdekApi;

            $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods')[0];

            if (strpos($shippingMethodIdSelected, 'official_cdek') === false) {
                return;
            }

            $city  = CheckoutHelper::getValueFromCurrentSession('city');
            $state = CheckoutHelper::getValueFromCurrentSession('postcode');

            $cityCode = $api->getCityCode($city, $state);
            if ($cityCode === -1) {
                wc_add_notice(__('Failed to determine locality. ' . $city . ' ' . $state, 'official-cdek'), 'error');
            }

            $tariffCode = explode('_', $shippingMethodIdSelected)[2];
            if (Tariff::isTariffToOffice($tariffCode)) {
                $pvzCode = CheckoutHelper::getValueFromCurrentSession('pvz_code');
                if (empty($pvzCode)) {
                    wc_add_notice(__('Order pickup point not selected.', 'official_cdek'), 'error');
                }
            } elseif (empty(CheckoutHelper::getValueFromCurrentSession('address_1'))) {
                wc_add_notice(__('No shipping address.', 'official_cdek'), 'error');
            }
        }
    }
}
