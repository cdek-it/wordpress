<?php

namespace Cdek\Fieldsets;

use Cdek\Contracts\FieldsetContract;


class GeneralOrderFields extends FieldsetContract
{
    final public function isApplicable(): bool
    {
        return empty(WC()->cart) || WC()->cart->needs_shipping();
    }

    final protected function getFields(): array
    {
        return [
            'billing_address_1'  => [
                'required'     => false,
                'label'        => __('Street address', 'cdekdelivery'),
                'placeholder'  => __('House number and street name', 'cdekdelivery'),
                'class'        => ['form-row-wide', 'address-field'],
                'autocomplete' => 'address-line1',
                'priority'     => 50,
            ],
            'billing_address_2'  => [
                'required'     => false,
                'label'        => __('Apartment, suite, unit, etc.', 'cdekdelivery'),
                'label_class'  => ['screen-reader-text'],
                'placeholder'  => __('Apartment, suite, unit, etc. (optional)', 'cdekdelivery'),
                'class'        => ['form-row-wide', 'address-field'],
                'autocomplete' => 'address-line2',
                'priority'     => 60,
            ],
            'billing_phone'      => [
                'required'     => true,
                'label'        => __('Phone', 'cdekdelivery'),
                'type'         => 'tel',
                'class'        => ['form-row-wide'],
                'validate'     => ['phone'],
                'autocomplete' => 'tel',
                'priority'     => 100,
            ],
            'billing_city'       => [
                'required'     => true,
                'label'        => __('Town / City', 'cdekdelivery'),
                'class'        => ['form-row-wide', 'address-field'],
                'autocomplete' => 'address-level2',
                'priority'     => 70,
            ],
            'billing_first_name' => [
                'required'     => true,
                'label'        => __('First name', 'cdekdelivery'),
                'class'        => ['form-row-first'],
                'autocomplete' => 'given-name',
                'priority'     => 10,
            ],
        ];
    }
}
