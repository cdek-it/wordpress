<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Validator {

    use Cdek\CdekApi;
    use Cdek\Config;
    use Cdek\Exceptions\CacheException;
    use Cdek\Exceptions\External\ApiException;
    use Cdek\Exceptions\External\CoreAuthException;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\Model\Tariff;
    use Throwable;

    class CheckoutValidator
    {

        public function __invoke(): void
        {
            if ( !WC()->cart->needs_shipping() ) {
                return;
            }

            $shippingMethods = WC()->session->get('chosen_shipping_methods');

            if ( empty($shippingMethods[0]) ) {
                wc_add_notice(esc_html__('No shipping methods.', 'cdekdelivery'), 'error');

                return;
            }

            $shippingMethodIdSelected = $shippingMethods[0];

            if ( strpos($shippingMethodIdSelected, Config::DELIVERY_NAME) === false ) {
                return;
            }

            $tariffCode = explode(':', $shippingMethodIdSelected)[1];

            if ( Tariff::isToOffice((int)$tariffCode) ) {
                if ( empty(CheckoutHelper::getValueFromCurrentSession('office_code')) ) {
                    wc_add_notice(esc_html__('Order pickup point not selected.', 'cdekdelivery'), 'error');
                }
            } else {
                if ( empty(CheckoutHelper::getValueFromCurrentSession('address_1')) ) {
                    wc_add_notice(esc_html__('No shipping address.', 'cdekdelivery'), 'error');
                }

                $city   = CheckoutHelper::getValueFromCurrentSession('city');
                $postal = CheckoutHelper::getValueFromCurrentSession('postcode');

                if ( (new CdekApi)->cityCodeGet($city, $postal) === null ) {
                    wc_add_notice(
                        sprintf(/* translators: 1: Name of a city 2: ZIP code */ esc_html__(
                            'Failed to determine locality in %1$s %2$s',
                            'cdekdelivery',
                        ),
                            $city,
                            $postal,
                        ),
                        'error',
                    );
                }
            }

            $phone = CheckoutHelper::getValueFromCurrentSession('phone');

            if ( empty($phone) ) {
                wc_add_notice(esc_html__('Phone number is required.', 'cdekdelivery'), 'error');
            } else {
                try {
                    PhoneValidator::new()($phone, CheckoutHelper::getValueFromCurrentSession('country'));
                } catch (CoreAuthException|ApiException|CacheException $e) {
                    return;
                } catch (Throwable $e) {
                    wc_add_notice($e->getMessage(), 'error');
                }
            }
        }
    }
}
