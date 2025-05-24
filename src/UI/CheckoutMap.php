<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\UI {

    use Cdek\CdekApi;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\MetaKeys;
    use Cdek\Model\Tariff;
    use Throwable;
    use WC_Shipping_Rate;

    class CheckoutMap
    {
        public function __invoke(WC_Shipping_Rate $rate): void
        {
            if (!is_checkout() || !CheckoutHelper::isShippingRateSuitable($rate)) {
                return;
            }

            $selectedRate = CheckoutHelper::getSelectedShippingRate();

            if (is_null($selectedRate) || $selectedRate->get_id() !== $rate->get_id()) {
                return;
            }

            $meta = $rate->get_meta_data();

            if (!in_array((int)$meta[MetaKeys::TARIFF_MODE], Tariff::listOfficeDeliveryModes(), true)) {
                return;
            }

            if (empty($meta[MetaKeys::CITY])) {
                return;
            }

            $api = new CdekApi;

            $city = $api->cityCodeGet($meta[MetaKeys::CITY], $meta[MetaKeys::POSTAL]);

            try {
                $officeInfo = empty($meta[MetaKeys::OFFICE_CODE]) ? null : $api->officeGet($meta[MetaKeys::OFFICE_CODE]);
            } catch (Throwable $e) {
                $officeInfo = null;
            }

            if (!is_null($officeInfo)) {
                printf(
                    '<div class="cdek-office-info">%s, %s, %s</div>',
                    esc_html($officeInfo['code']),
                    esc_html($officeInfo['location']['city']),
                    esc_html($officeInfo['location']['address']),
                );
            }

            printf(
                '<div class="open-pvz-btn" data-city="%s"><script type="application/cdek-offices">%s</script><a>%s</a></div><input name="office_code" class="cdek-office-code" type="hidden" value="%s"/>',
                esc_attr($meta[MetaKeys::CITY]),
                wc_esc_json($city !== null ? $api->officeListRaw($city) : '[]', true),
                is_null($officeInfo) ? esc_html__('Choose pick-up', 'cdekdelivery') :
                    esc_html__('Re-select pick-up', 'cdekdelivery'),
                esc_attr($meta[MetaKeys::OFFICE_CODE]),
            );
        }
    }
}
