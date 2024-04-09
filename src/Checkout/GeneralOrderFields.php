<?php

namespace Cdek\Checkout;

use Cdek\Checkout\FieldConstructorInterface;

class GeneralOrderFields implements FieldConstructorInterface
{
    const REQUIRED_FIELDS = [
        'billing_first_name',
        'billing_city',
        'billing_phone',
        'billing_address_1',
    ];

    const REQUIRED_FIELDS_PARAMS = [
        'billing_address_1'  => false,
        'billing_address_2'  => false,
        'billing_phone'      => true,
        'billing_city'       => true,
        'billing_first_name' => true,
    ];

    private array $fields;

    public function __construct($fields)
    {
        $this->fields = $fields['billing'];
    }

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
        return isset(self::REQUIRED_FIELDS_PARAMS[$field]) && self::REQUIRED_FIELDS_PARAMS[$field];
    }

    public function isExistField(string $field): bool
    {
        return isset($this->fields[$field]);
    }
}
