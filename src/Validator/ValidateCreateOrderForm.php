<?php

namespace Cdek\Validator;

use Cdek\Model\Validate;

class ValidateCreateOrderForm
{
    public static function validate($data)
    {
        if ($data->get_param('package_length') === '' &&
            $data->get_param('package_width') === '' &&
            $data->get_param('package_height') === '') {
            return new Validate(false, 'Введите габариты упаковки в сантиметрах');
        }

        if ($data->get_param('package_length') === '') {
            return new Validate(false, 'Введите длину упаковки в сантиметрах');
        }

        if (!is_numeric($data->get_param('package_length'))) {
            return new Validate(false, 'Значение длины должно быть числом');
        }

        if ($data->get_param('package_width') === '') {
            return new Validate(false, 'Введите ширину упаковки в сантиметрах');
        }

        if (!is_numeric($data->get_param('package_width'))) {
            return new Validate(false, 'Значение ширины должно быть числом');
        }

        if ($data->get_param('package_height') === '') {
            return new Validate(false, 'Введите высоту упаковки в сантиметрах');
        }

        if (!is_numeric($data->get_param('package_height'))) {
            return new Validate(false, 'Значение высоты должно быть числом');
        }

        return new Validate(true);
    }
}