<?php

namespace Cdek\Validator;

use Cdek\Model\Validate;

class ValidateCreateOrderForm
{
    public static function validate($data)
    {
        $packageLength = $data->get_param('package_length');
        $packageWidth = $data->get_param('package_width');
        $packageHeight = $data->get_param('package_height');
        if ($packageLength === '' && $packageWidth === '' && $packageHeight === '') {
            return new Validate(false, 'Введите габариты упаковки в сантиметрах');
        }

        if ($packageLength === '') {
            return new Validate(false, 'Введите длину упаковки в сантиметрах');
        }

        if (!filter_var($packageLength, FILTER_VALIDATE_INT) || !is_numeric($packageLength)) {
            return new Validate(false, 'Значение длины должно быть числом');
        }

        if ($packageWidth === '') {
            return new Validate(false, 'Введите ширину упаковки в сантиметрах');
        }

        if (!filter_var($packageWidth, FILTER_VALIDATE_INT) || !is_numeric($packageWidth)) {
            return new Validate(false, 'Значение ширины должно быть числом');
        }

        if ($packageHeight === '') {
            return new Validate(false, 'Введите высоту упаковки в сантиметрах');
        }

        if (!filter_var($packageHeight, FILTER_VALIDATE_INT) || !is_numeric($packageHeight)) {
            return new Validate(false, 'Значение высоты должно быть числом');
        }

        return new Validate(true);
    }
}