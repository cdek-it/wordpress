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
                return new Validate(
                    false,
                    __(
                        "The courier waiting date has not been selected",
                        'cdekdelivery'
                    )
                );
            }

            $current = new DateTime();
            $currentDate = $current->format('Y-m-d');
            $currentDateUnix = strtotime($currentDate);
            $currentDate31DaysLater = strtotime($currentDate . ' +31 days');

            $dateUnix = strtotime($data['date']);
            if ($dateUnix < $currentDateUnix) {
                return new Validate(
                    false,
                    __(
                        "The courier waiting date cannot be earlier than the current date",
                        'cdekdelivery'
                    )
                );
            }

            if ($dateUnix > $currentDate31DaysLater) {
                return new Validate(
                    false,
                    __(
                        "The courier waiting date cannot be later than the 31st of the current date",
                        'cdekdelivery'
                    )
                );
            }

            if (empty($data['starttime']) || empty($data['endtime'])) {
                return new Validate(
                    false,
                    __(
                        "No courier waiting time selected",
                        'cdekdelivery'
                    )
                );
            }

            $currentStartTimeUnix = strtotime($data['starttime']);
            $currentEndTimeUnix = strtotime($data['endtime']);

            if ($currentStartTimeUnix >= $currentEndTimeUnix) {
                return new Validate(
                    false,
                    __(
                        "The start of the courier waiting time cannot start later than the end time",
                        'cdekdelivery'
                    )
                );
            }

            if (empty($data['name'])) {
                return new Validate(
                    false,
                    __(
                        "FIO is required",
                        'cdekdelivery'
                    )
                );
            }

            if (empty($data['phone'])) {
                return new Validate(
                    false,
                    __(
                        "Phone is required",
                        'cdekdelivery'
                    )
                );
            }

            if (empty($data['address'])) {
                return new Validate(
                    false,
                    __(
                        "Address is required",
                        'cdekdelivery'
                    )
                );
            }

            return new Validate(true);
        }

        public static function validatePackage($data): Validate
        {
            if (empty($data['desc'])) {
                return new Validate(
                    false,
                    __(
                        "Cargo description is required",
                        'cdekdelivery'
                    )
                );
            }

            if (empty($data['weight'])) {
                return new Validate(
                    false,
                    __(
                        "Weight is required",
                        'cdekdelivery'
                    )
                );
            }

            if (!is_numeric($data['weight'])) {
                return new Validate(
                    false,
                    __(
                        "Weight must be a number",
                        'cdekdelivery'
                    )
                );
            }

            if (empty($data['length'])) {
                return new Validate(
                    false,
                    __(
                        "Length is required",
                        'cdekdelivery'
                    )
                );
            }

            if (!is_numeric($data['length'])) {
                return new Validate(
                    false,
                    __(
                        "Length must be a number",
                        'cdekdelivery'
                    )
                );
            }

            if (empty($data['width'])) {
                return new Validate(
                    false,
                    __(
                        "Width is required",
                        'cdekdelivery'
                    )
                );
            }

            if (!is_numeric($data['width'])) {
                return new Validate(
                    false,
                    __(
                        "Width must be a number",
                        'cdekdelivery'
                    )
                );
            }

            if (empty($data['height'])) {
                return new Validate(
                    false,
                    __(
                        "Height is required",
                        'cdekdelivery'
                    )
                );
            }

            if (!is_numeric($data['height'])) {
                return new Validate(
                    false,
                    __(
                        "Height must be a number",
                        'cdekdelivery'
                    )
                );
            }

            return new Validate(true);
        }
    }
}
