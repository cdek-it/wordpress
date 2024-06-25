<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\Config;
    use Cdek\Helper;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\MetaKeys;
    use Cdek\Model\Tariff;
    use WC_Order_Item_Shipping;

    class ProcessWoocommerceCreateShippingAction
    {
        /**
         * @throws \WC_Data_Exception
         * @throws \Exception
         */
        public function __invoke(WC_Order_Item_Shipping $shipping): void
        {
            if ($shipping->get_method_id() !== Config::DELIVERY_NAME) {
                return;
            }

            $pvzCode  = CheckoutHelper::getValueFromCurrentSession('office_code');
            $tariffId = $shipping->get_meta(MetaKeys::TARIFF_CODE) ?: $shipping->get_meta('tariff_code');

            if (Tariff::isTariffToOffice($tariffId)) {
                $shipping->add_meta_data(MetaKeys::OFFICE_CODE, $pvzCode);
                $shipping->save_meta_data();
            }
        }
    }
}
