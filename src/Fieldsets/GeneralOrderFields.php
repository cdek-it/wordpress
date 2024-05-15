<?php

namespace Cdek\Fieldsets;

use Cdek\Contracts\FieldsetContract;


class GeneralOrderFields extends FieldsetContract
{
    protected const FIELDS
        = [
            'billing_address_1'  => [
                'required'     => false,
                'label'        => 'Street address',
                'placeholder'  => 'House number and street name',
                'class'        => ['form-row-wide', 'address-field'],
                'autocomplete' => 'address-line1',
                'priority'     => 50,
            ],
            'billing_address_2'  => [
                'required'     => false,
                'label'        => 'Apartment, suite, unit, etc.',
                'label_class'  => ['screen-reader-text'],
                'placeholder'  => 'Apartment, suite, unit, etc. (optional)',
                'class'        => ['form-row-wide', 'address-field'],
                'autocomplete' => 'address-line2',
                'priority'     => 60,
            ],
            'billing_phone'      => [
                'required'     => true,
                'label'        => 'Phone',
                'type'         => 'tel',
                'class'        => ['form-row-wide'],
                'validate'     => ['phone'],
                'autocomplete' => 'tel',
                'priority'     => 100,
            ],
            'billing_city'       => [
                'required'     => true,
                'label'        => 'Town / City',
                'class'        => ['form-row-wide', 'address-field'],
                'autocomplete' => 'address-level2',
                'priority'     => 70,
            ],
            'billing_first_name' => [
                'required'     => true,
                'label'        => 'First name',
                'class'        => ['form-row-first'],
                'autocomplete' => 'given-name',
                'priority'     => 10,
            ],
        ];

    final public function isApplicable(): bool
    {
        return empty(WC()->cart) || WC()->cart->needs_shipping();
    }
}
