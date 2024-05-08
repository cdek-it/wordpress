<?php

namespace Cdek\Checkout;

use Cdek\Contracts\FieldConstructorInterface;

class InternationalOrderFields implements FieldConstructorInterface
{
    private array $fields;

    public function __construct($fields = [])
    {
        if (empty($fields)) {
            $this->fields = [
                'passport_series'        => [
                    'label'             => __('Passport Series', 'cdekdelivery'),
                    'required'          => true,
                    'custom_attributes' => [
                        'maxlength' => 4,
                    ],
                ],
                'passport_number'        => [
                    'label'             => __('Passport number', 'cdekdelivery'),
                    'required'          => true,
                    'custom_attributes' => [
                        'maxlength' => 6,
                    ],
                ],
                'passport_date_of_issue' => [
                    'type'     => 'date',
                    'label'    => __('Passport date of issue', 'cdekdelivery'),
                    'required' => true,
                ],
                'passport_organization'  => [
                    'label'    => __('Passport organization', 'cdekdelivery'),
                    'required' => true,
                ],
                'tin'                    => [
                    'label'             => __('TIN', 'cdekdelivery'),
                    'required'          => true,
                    'custom_attributes' => [
                        'maxlength' => 12,
                    ],
                ],
                'passport_date_of_birth' => [
                    'type'     => 'date',
                    'label'    => __('Birthday', 'cdekdelivery'),
                    'required' => true,
                ],
            ];
        } else {
            $this->fields = $fields;
        }
    }


    public function getFields(): array
    {
        $arFields = [];

        foreach ($this->fields as $key => $arField) {
            $arFields[$key] = [
                'priority' => 120,
                'label'    => $arField['label'],
                'required' => $arField['required'],
                'class'    => ['form-row-wide'],
                'clear'    => true,
            ];

            if (!empty($arField['type'])) {
                $arFields[$key]['type'] = $arField['type'];
            }

            if (!empty($arField['custom_attributes'])) {
                $arFields[$key]['custom_attributes'] = $arField['custom_attributes'];
            }
        }

        return $arFields;
    }

    public function isRequiredField(string $field): bool
    {
        return $this->fields[$field]['required'];
    }
}
