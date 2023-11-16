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
            $api = new CdekApi;

            $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods')[0];

            if (strpos($shippingMethodIdSelected, 'official_cdek') === false) {
                return;
            }

            $city = CheckoutHelper::getValueFromCurrentSession('city');
            $state = CheckoutHelper::getValueFromCurrentSession('postcode');

            $cityCode = $api->getCityCode($city, $state);
            if ($cityCode === -1) {
                wc_add_notice(__('Не удалось определить населенный пункт.'), 'error');
            }

            $tariffCode = explode('_', $shippingMethodIdSelected)[2];
            if (Tariff::isTariffToOffice($tariffCode)) {
                $pvzCode = CheckoutHelper::getValueFromCurrentSession('pvz_code') ?? WC()->session->get('pvz_code');
                if (empty($pvzCode)) {
                    wc_add_notice(__('Не выбран пункт выдачи заказа.'), 'error');
                }
            } else {
                if (empty(CheckoutHelper::getValueFromCurrentSession('address_1'))) {
                    wc_add_notice(__('Нет адреса отправки.'), 'error');
                }
            }
        }
    }
}
