<?php

namespace Cdek\Checkout;

use Cdek\Contracts\FieldConstructorInterface;

class GeneralOrderFields implements FieldConstructorInterface
{
    const REQUIRED_FIELDS_PARAMS = [
        'billing_address_1'  => false,
        'billing_address_2'  => false,
        'billing_phone'      => true,
        'billing_city'       => true,
        'billing_first_name' => true,
    ];

    public function getFields(): array
    {
        return self::REQUIRED_FIELDS_PARAMS;
    }

    public function isRequiredField(string $field): bool
    {
        return isset(self::REQUIRED_FIELDS_PARAMS[$field]) && self::REQUIRED_FIELDS_PARAMS[$field];
    }
}
