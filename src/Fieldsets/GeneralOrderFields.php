<?php

namespace Cdek\Fieldsets;

use Cdek\Contracts\FieldsetContract;

class GeneralOrderFields extends FieldsetContract
{
    protected const FIELDS
        = [
            'billing_address_1'  => [
                'required' => false,
            ],
            'billing_address_2'  => [
                'required' => false,
            ],
            'billing_phone'      => [
                'required' => true,
            ],
            'billing_city'       => [
                'required' => true,
            ],
            'billing_first_name' => [
                'required' => true,
            ],
        ];

    final public function isApplicable(): bool
    {
        return empty(WC()->cart) || WC()->cart->needs_shipping();
    }
}
