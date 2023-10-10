<?php

namespace Cdek\Validator;

use Cdek\CdekApi;
use Cdek\Helpers\CheckoutHelper;
use Cdek\Model\Tariff;

class CheckoutProcessValidator {

    public function __invoke() {
        $shippingMethodIdSelected = WC()->session->get('chosen_shipping_methods')[0];

        if (strpos($shippingMethodIdSelected, 'official_cdek') !== false) {
            $city     = CheckoutHelper::getValueFromCurrentSession('city');
            $state    = CheckoutHelper::getValueFromCurrentSession('state');
            $api      = new CdekApi;
            $cityCode = $api->getCityCodeByCityName($city, $state);
            if ($cityCode === -1) {
                wc_add_notice(__('Не удалось определить населенный пункт.'), 'error');
            }

            $tariffCode = self::getTariffCodeByShippingMethodId($shippingMethodIdSelected);
            if (Tariff::isTariffToOffice($tariffCode)) {
                $pvzCode = CheckoutHelper::getValueFromCurrentSession('pvz_code') ?? WC()->session->get('pvz_code');
                if (empty($pvzCode)) {
                    wc_add_notice(__('Не выбран пункт выдачи заказа.'), 'error');
                }
            } elseif (empty(CheckoutHelper::getValueFromCurrentSession('address_1'))) {
                wc_add_notice(__('Нет адреса отправки.'), 'error');
            }
        }
    }

    private static function getTariffCodeByShippingMethodId($shippingMethodId) {
        return explode('_', $shippingMethodId)[2];
    }
}
