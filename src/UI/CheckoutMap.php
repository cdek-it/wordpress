<?php

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

            $city = $api->getCityCode($cityInput, $postcodeInput);

            if($city === -1){
                $city = $api->getCityCode($cityInput, '');
            }

            $points = $city !== -1 ? $api->getOffices([
                                                          'city_code' => $city,
                                                      ])['body'] : '[]';

            $mapAutoClose = CheckoutHelper::getMapAutoClose();

            include __DIR__.'/../../templates/public/open-map.php';
        }

        private function isTariffDestinationCdekOffice($shippingMethodCurrent): bool
        {
            if ($shippingMethodCurrent->get_method_id() !== Config::DELIVERY_NAME) {
                return false;
            }

            $shippingMethodIdSelected = wc_get_chosen_shipping_method_ids()[0];

            if ($shippingMethodCurrent->get_id() !== $shippingMethodIdSelected) {
                return false;
            }

            $tariffCode = explode('_', $shippingMethodIdSelected)[2];

            return Tariff::isTariffToOffice($tariffCode);
        }
    }
}
