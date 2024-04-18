<?php

namespace Cdek\Contracts;

interface FieldConstructorInterface
{
    public function getFields(): array;
    public function isRequiredField(string $field): bool;
}
