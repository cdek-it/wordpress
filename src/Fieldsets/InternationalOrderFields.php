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

        final public function isApplicable(): bool
        {
            if (!ShippingMethod::factory()->international_mode) {
                return false;
            }

            // Для доставки внутри РФ паспортные поля не нужны
            return strtoupper((string)CheckoutHelper::getCurrentValue('country')) !== self::DOMESTIC_COUNTRY_CODE;
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
