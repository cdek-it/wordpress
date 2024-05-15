<?php

namespace Cdek\Fieldsets;

use Cdek\Contracts\FieldsetContract;
use Cdek\Helper;

class InternationalOrderFields extends FieldsetContract
{
    protected const FIELDS
        = [
            'passport_series'        => [
                'priority'          => 120,
                'label'             => 'Passport Series',
                'required'          => true,
                'custom_attributes' => [
                    'maxlength' => 4,
                ],
                'class'             => ['form-row-wide'],
            ],
            'passport_number'        => [
                'priority'          => 120,
                'label'             => 'Passport number',
                'required'          => true,
                'custom_attributes' => [
                    'maxlength' => 6,
                ],
                'class'             => ['form-row-wide'],
            ],
            'passport_date_of_issue' => [
                'priority' => 120,
                'type'     => 'date',
                'label'    => 'Passport date of issue',
                'required' => true,
                'class'    => ['form-row-wide'],
            ],
            'passport_organization'  => [
                'priority' => 120,
                'label'    => 'Passport organization',
                'required' => true,
                'class'    => ['form-row-wide'],
            ],
            'tin'                    => [
                'priority'          => 120,
                'label'             => 'TIN',
                'required'          => true,
                'custom_attributes' => [
                    'maxlength' => 12,
                ],
                'class'             => ['form-row-wide'],
            ],
            'passport_date_of_birth' => [
                'priority' => 120,
                'type'     => 'date',
                'label'    => 'Birthday',
                'required' => true,
                'class'    => ['form-row-wide'],
            ],
        ];

    final public function isApplicable(): bool
    {
        return Helper::getActualShippingMethod()->get_option('international_mode') === 'yes';
    }
}
