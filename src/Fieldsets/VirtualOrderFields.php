<?php

namespace Cdek\Fieldsets;

use Cdek\Contracts\FieldsetContract;

class VirtualOrderFields implements FieldsetContract
{
    const REQUIRED_FIELDS_PARAMS = [
        'billing_phone'      => true,
        'billing_first_name' => true,
        'billing_city'       => false,
        'billing_address_1'  => false,
        'billing_address_2'  => false,
        'billing_country'  => false,
        'billing_state'  => false,
        'address-level1'  => false,
        'billing_postcode'  => false,
    ];

    public function getFields(): array
    {
        return self::REQUIRED_FIELDS_PARAMS;
    }

    public function isRequiredField(string $field): bool
    {
        return isset(self::REQUIRED_FIELDS_PARAMS[$field]);
    }
}
