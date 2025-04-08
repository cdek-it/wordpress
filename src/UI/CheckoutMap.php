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
    use Throwable;

    class CheckoutMap
    {
        public function __invoke($shippingMethodCurrent): void
        {
            if (!is_checkout() || !$this->isTariffDestinationCdekOffice($shippingMethodCurrent)) {
                return;
            }

            $cityInput     = CheckoutHelper::getCurrentValue('city');
            $postcodeInput = CheckoutHelper::getCurrentValue('postcode');

            if (empty($cityInput)) {
                return;
            }

            $api = new CdekApi;

            $city = $api->cityCodeGet($cityInput, $postcodeInput);

            $selectedOffice = CheckoutHelper::getCurrentValue('office_code');

            try{
                $officeInfo = empty($selectedOffice) ? null : $api->officeGet($selectedOffice);
            }catch (Throwable $e) {
                $officeInfo = null;
            }

            if (!is_null($officeInfo)){
                printf(
                    '<div class="cdek-office-info">%s, %s, %s</div>',
                    esc_html($officeInfo['code']),
                    esc_html($officeInfo['location']['city']),
                    esc_html($officeInfo['location']['address']),
                );
            }

            printf(
                '<div class="open-pvz-btn" data-city="%s"><script type="application/cdek-offices">%s</script><a>%s</a></div><input name="office_code" class="cdek-office-code" type="hidden" value="%s"/>',
                esc_attr($cityInput),
                wc_esc_json($city !== null ? $api->officeListRaw($city) : '[]', true),
                is_null($officeInfo) ? esc_html__('Choose pick-up', 'cdekdelivery') : esc_html__('Re-select pick-up', 'cdekdelivery'),
                esc_attr($selectedOffice),
            );
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
