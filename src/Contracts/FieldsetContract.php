<?php

namespace Cdek\Contracts;

use InvalidArgumentException;

abstract class FieldsetContract
{
    protected const FIELDS = [];

    final public function getFieldsNames(): array
    {
        return array_keys(static::FIELDS);
    }

    final public function isRequiredField(string $fieldName): bool
    {
        if (!isset(static::FIELDS[$fieldName])) {
            throw new InvalidArgumentException('Field not found');
        }

        return static::FIELDS[$fieldName]['required'] ?? false;
    }

    abstract public function isApplicable(): bool;

    final public function getFieldDefinition(string $fieldName): array
    {
        if (!isset(static::FIELDS[$fieldName])) {
            throw new InvalidArgumentException('Field not found');
        }

        $def = static::FIELDS[$fieldName];

        if (!empty($def['label'])) {
            $def['label'] = esc_html__($def['label'], 'cdekdelivery');
        }

        return $def;
    }
}
