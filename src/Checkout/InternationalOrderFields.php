<?php

namespace Cdek\Checkout;

use Cdek\Checkout\FieldConstructorInterface;

class InternationalOrderFields implements FieldConstructorInterface
{
    const MODULE_COMMERCE = 'woocommerce';

    private array $fields = [
        'passport_series' => [
            'label' => 'Серия паспорта',
            'required' => true,
            'custom_attributes' => [
                'maxlength' => 4
            ]
        ],
        'passport_number' => [
            'label' => 'Номер паспорта',
            'required' => true,
            'custom_attributes' => [
                'maxlength' => 6
            ]
        ],
        'passport_date_of_issue' => [
            'type'     => 'date',
            'label' => 'Дата выдачи паспорта',
            'required' => true,
        ],
        'passport_organization' => [
            'label' => 'Орган выдачи паспорта',
            'required' => true,
        ],
        'tin' => [
            'label' => 'ИНН',
            'required' => true,
            'custom_attributes' => [
                'maxlength' => 12
            ]
        ],
        'passport_date_of_birth' => [
            'type'     => 'date',
            'label' => 'Орган выдачи паспорта',
            'required' => true,
        ],
    ];

    public function getFields(): array
    {
        $arFields = [];

        foreach ($this->fields as $key => $arField){
            $arFields[$key] = [
                'priority' => 120,
                'label'    => __($arField['label'], static::MODULE_COMMERCE),
                'required' => $arField['required'],
                'class'    => ['form-row-wide'],
                'clear'    => true,
            ];

            if(!empty($arField['type'])){
                $arFields[$key]['type'] = $arField['type'];
            }

            if(!empty($arField['custom_attributes'])){
                $arFields[$key]['custom_attributes'] = $arField['custom_attributes'];
            }
        }

        return $arFields;
    }

    public function getRequiredFields(): array
    {
        return $this->fields;
    }

    public function isRequiredField(string $field): bool
    {
        return $this->fields[$field]['required'];
    }

    public function isExistField(string $field): bool
    {
        return isset($this->fields[$field]);
    }
}
