<?php

namespace Cdek\Contracts;

use InvalidArgumentException;

abstract class FieldsetContract
{
    protected const FIELDS = [];

    final public function getFieldsNames(): array
    {
        return array_keys($this->getFields());
    }

    abstract protected function getFields(): array;

    final public function isRequiredField(string $fieldName): bool
    {
        $fieldList = $this->getFields();
        if (!isset($fieldList[$fieldName])) {
            throw new InvalidArgumentException('Field not found');
        }

        return $fieldList[$fieldName]['required'] ?? false;
    }

    abstract public function isApplicable(): bool;

    final public function getFieldDefinition(string $fieldName): array
    {
        $fieldList = $this->getFields();
        if (!isset($fieldList[$fieldName])) {
            throw new InvalidArgumentException('Field not found');
        }

        return $fieldList[$fieldName];
    }
}
