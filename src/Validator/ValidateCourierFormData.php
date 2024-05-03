<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Validator {

    use Cdek\Model\Validate;
    use DateTime;

    class ValidateCourierFormData
    {
        public static function validate($data): Validate
        {

            if (empty($data['date'])) {
                return new Validate(false, 'Дата ожидания курьера не выбрана');
            }

            $current = new DateTime();
            $currentDate = $current->format('Y-m-d');
            $currentDateUnix = strtotime($currentDate);
            $currentDate31DaysLater = strtotime($currentDate . ' +31 days');

            $dateUnix = strtotime($data['date']);
            if ($dateUnix < $currentDateUnix) {
                return new Validate(false, 'Дата ожидания курьера не может быть раньше текущей даты');
            }

            if ($dateUnix > $currentDate31DaysLater) {
                return new Validate(false, 'Дата ожидания курьера не может быть позднее на 31 текущей даты');
            }

            if (empty($data['starttime']) || empty($data['endtime'])) {
                return new Validate(false, 'Время ожидания курьера не выбрано');
            }

            $currentStartTimeUnix = strtotime($data['starttime']);
            $currentEndTimeUnix = strtotime($data['endtime']);

            if ($currentStartTimeUnix >= $currentEndTimeUnix) {
                return new Validate(false, 'Начало времени ожидания курьера не может начинаться позже времени окончания');
            }

            if (empty($data['name'])) {
                return new Validate(false, 'ФИО обязательно для заполнения');
            }

            if (empty($data['phone'])) {
                return new Validate(false, 'Телефон обязателен для заполнения');
            }

            if (empty($data['address'])) {
                return new Validate(false, 'Адрес обязателен для заполнения');
            }

            return new Validate(true);
        }

        public static function validatePackage($data): Validate
        {
            if (empty($data['desc'])) {
                return new Validate(false, 'Описание груза обязательно для заполнения');
            }

            if (empty($data['weight'])) {
                return new Validate(false, 'Вес обязателен для заполнения');
            }

            if (!is_numeric($data['weight'])) {
                return new Validate(false, 'Вес должен быть числом');
            }

            if (empty($data['length'])) {
                return new Validate(false, 'Длина обязателена для заполнения');
            }

            if (!is_numeric($data['length'])) {
                return new Validate(false, 'Длина должена быть числом');
            }

            if (empty($data['width'])) {
                return new Validate(false, 'Ширина обязателена для заполнения');
            }

            if (!is_numeric($data['width'])) {
                return new Validate(false, 'Ширина должна быть числом');
            }

            if (empty($data['height'])) {
                return new Validate(false, 'Высота обязателена для заполнения');
            }

            if (!is_numeric($data['height'])) {
                return new Validate(false, 'Высота должена быть числом');
            }

            return new Validate(true);
        }
    }
}
