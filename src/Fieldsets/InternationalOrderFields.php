<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Fieldsets {

    use Cdek\Contracts\FieldsetContract;
    use Cdek\Helpers\CheckoutHelper;
    use Cdek\ShippingMethod;

    class InternationalOrderFields extends FieldsetContract
    {
        private const DOMESTIC_COUNTRY_CODE = 'RU';

        final public function isModeEnabled(): bool
        {
            return (bool)ShippingMethod::factory()->international_mode;
        }

        final public function isDomestic(): bool
        {
            return strtoupper($this->getDestinationCountry()) === self::DOMESTIC_COUNTRY_CODE;
        }

        /**
         * @noinspection GlobalVariableUsageInspection
         */
        private function getDestinationCountry(): string
        {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- значение только сравнивается с кодом страны
            if (empty($_POST['ship_to_different_address']) && !empty($_POST['billing_country'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                return wc_clean(wp_unslash($_POST['billing_country']));
            }

            return (string)CheckoutHelper::getCurrentValue('country');
        }

        final public function isApplicable(): bool
        {
            // Для доставки внутри РФ паспортные поля не нужны
            return $this->isModeEnabled() && !$this->isDomestic();
        }

        final protected function getFields(): array
        {
            return [
                'passport_series'        => [
                    'priority'          => 120,
                    'label'             => esc_html__('Passport Series', 'cdekdelivery'),
                    'required'          => true,
                    'custom_attributes' => [
                        'maxlength' => 4,
                    ],
                    'class'             => ['form-row-wide'],
                ],
                'passport_number'        => [
                    'priority'          => 120,
                    'label'             => esc_html__('Passport number', 'cdekdelivery'),
                    'required'          => true,
                    'custom_attributes' => [
                        'maxlength' => 6,
                    ],
                    'class'             => ['form-row-wide'],
                ],
                'passport_date_of_issue' => [
                    'priority' => 120,
                    'type'     => 'date',
                    'label'    => esc_html__('Passport date of issue', 'cdekdelivery'),
                    'required' => true,
                    'class'    => ['form-row-wide'],
                ],
                'passport_organization'  => [
                    'priority' => 120,
                    'label'    => esc_html__('Passport organization', 'cdekdelivery'),
                    'required' => true,
                    'class'    => ['form-row-wide'],
                ],
                'tin'                    => [
                    'priority'          => 120,
                    'label'             => esc_html__('TIN', 'cdekdelivery'),
                    'required'          => true,
                    'custom_attributes' => [
                        'maxlength' => 12,
                    ],
                    'class'             => ['form-row-wide'],
                ],
                'passport_date_of_birth' => [
                    'priority' => 120,
                    'type'     => 'date',
                    'label'    => esc_html__('Birthday', 'cdekdelivery'),
                    'required' => true,
                    'class'    => ['form-row-wide'],
                ],
            ];
        }
    }
}
