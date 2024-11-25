<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\Model\Tariff;

    class CheckoutMap
    {
        public function __invoke($shippingMethodCurrent): void
        {
            if (!is_checkout() || !$this->isTariffDestinationCdekOffice($shippingMethodCurrent)) {
                return;
            }

            $cityInput     = CheckoutHelper::getValueFromCurrentSession('city');
            $postcodeInput = CheckoutHelper::getValueFromCurrentSession('postcode');

            if (empty($cityInput)) {
                return;
            }

            $api = new CdekApi;

            $city = $api->cityCodeGet($cityInput, $postcodeInput);

            echo '<div class="open-pvz-btn" data-city="'.
                 esc_attr($cityInput).
                 '">'.
                 '<script type="application/cdek-offices">'.
                 wc_esc_json($city !== null ? $api->officeListRaw($city) : '[]', true).
                 '</script><a>'.
                 esc_html__('Choose pick-up', 'cdekdelivery').
                 '</a></div><input name="office_code" class="cdek-office-code" type="hidden">';
        }

        private function isTariffDestinationCdekOffice($shippingMethodCurrent): bool
        {
            if ($shippingMethodCurrent->get_method_id() !== Config::DELIVERY_NAME) {
                return false;
            }

            $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods', []);

            if (empty($shippingMethodIdSelected[0]) ||
                $shippingMethodCurrent->get_id() !== $shippingMethodIdSelected[0]) {
                return false;
            }

            $tariffCode = explode(':', $shippingMethodIdSelected[0])[1];

            return Tariff::isToOffice((int)$tariffCode);
        }
    }
}
