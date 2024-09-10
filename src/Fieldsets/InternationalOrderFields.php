<?php

namespace Cdek\Fieldsets;

use Cdek\Contracts\FieldsetContract;
use Cdek\Helper;

class InternationalOrderFields extends FieldsetContract
{
    final public function isApplicable(): bool
    {
        return Helper::getActualShippingMethod()->get_option('international_mode') === 'yes';
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
