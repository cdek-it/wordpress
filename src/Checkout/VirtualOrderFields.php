<?php

namespace Cdek\Checkout;

use Cdek\Checkout\FieldConstructorInterface;

class VirtualOrderFields implements FieldConstructorInterface
{
    const REQUIRED_FIELDS = [
        'billing_first_name',
        'billing_phone',
    ];

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

    const VALID_FIELDS = [
        'billing_first_name',
        'billing_last_name',
        'billing_company',
        'billing_phone',
        'billing_email',
    ];

    public function getFields(): array
    {
        return self::REQUIRED_FIELDS_PARAMS;
    }

    public function getRequiredFields(): array
    {
        return self::REQUIRED_FIELDS;
    }

    public function isRequiredField(string $field): bool
    {
        return isset(self::REQUIRED_FIELDS[$field]);
    }

    public function isExistField(string $field): bool
    {
        return in_array($field, self::VALID_FIELDS);
    }
}
