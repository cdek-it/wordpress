<?php
declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use Cdek\Helpers\CheckoutHelper;
    use Cdek\Model\ShippingItem;
    use Cdek\Model\Tariff;
    use InvalidArgumentException;
    use WC_Order_Item_Shipping;

    class ProcessWoocommerceCreateShippingAction
    {
        public function __invoke(WC_Order_Item_Shipping $shippingItem): void
        {
            try {
                $shipping = new ShippingItem($shippingItem);
            } catch (InvalidArgumentException $e){
                return;
            }

            if (!Tariff::isToOffice((int)$shipping->tariff)) {
                return;
            }

            $shipping->office = CheckoutHelper::getValueFromCurrentSession('office_code');
            $shipping->save();
        }
    }
}
