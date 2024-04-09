<?php

namespace Cdek\Checkout;

interface FieldConstructorInterface
{
    public function getFields(): array;
    public function getRequiredFields(): array;
    public function isRequiredField(string $field): bool;
    public function isExistField(string $field): bool;
}
