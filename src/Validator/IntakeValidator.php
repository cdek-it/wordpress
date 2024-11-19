<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Validator {

    use Cdek\Model\ValidationResult;
    use DateTime;

    class IntakeValidator
    {
        public static function validate($data): ValidationResult
        {
            if (empty($data['date'])) {
                return new ValidationResult(
                    false, esc_html__('The courier waiting date has not been selected', 'cdekdelivery'),
                );
            }

            $current                = new DateTime();
            $currentDate            = $current->format('Y-m-d');
            $currentDateUnix        = strtotime($currentDate);
            $currentDate31DaysLater = strtotime($currentDate.' +31 days');

            $dateUnix = strtotime($data['date']);
            if ($dateUnix < $currentDateUnix) {
                return new ValidationResult(
                    false, esc_html__(
                        'The courier waiting date cannot be earlier than the current date',
                        'cdekdelivery',
                    ),
                );
            }

            if ($dateUnix > $currentDate31DaysLater) {
                return new ValidationResult(
                    false, esc_html__(
                        'The courier waiting date cannot be later than the 31st of the current date',
                        'cdekdelivery',
                    ),
                );
            }

            if (empty($data['starttime']) || empty($data['endtime'])) {
                return new ValidationResult(false, esc_html__('No courier waiting time selected', 'cdekdelivery'));
            }

            $currentStartTimeUnix = strtotime($data['starttime']);
            $currentEndTimeUnix   = strtotime($data['endtime']);

            if ($currentStartTimeUnix >= $currentEndTimeUnix) {
                return new ValidationResult(
                    false, esc_html__(
                        'The start of the courier waiting time cannot start later than the end time',
                        'cdekdelivery',
                    ),
                );
            }

            if (empty($data['name'])) {
                return new ValidationResult(false, esc_html__('Full name is required', 'cdekdelivery'));
            }

            if (empty($data['phone'])) {
                return new ValidationResult(false, esc_html__('Phone is required', 'cdekdelivery'));
            }

            if (empty($data['address'])) {
                return new ValidationResult(false, esc_html__('Address is required', 'cdekdelivery'));
            }

            return new ValidationResult(true);
        }

        public static function validatePackage($data): ValidationResult
        {
            if (empty($data['desc'])) {
                return new ValidationResult(false, esc_html__('Cargo description is required', 'cdekdelivery'));
            }

            if (empty($data['weight'])) {
                return new ValidationResult(false, esc_html__('Weight is required', 'cdekdelivery'));
            }

            if (!is_numeric($data['weight'])) {
                return new ValidationResult(false, esc_html__('Weight must be a number', 'cdekdelivery'));
            }

            if (empty($data['length'])) {
                return new ValidationResult(false, esc_html__('Length is required', 'cdekdelivery'));
            }

            if (!is_numeric($data['length'])) {
                return new ValidationResult(false, esc_html__('Length must be a number', 'cdekdelivery'));
            }

            if (empty($data['width'])) {
                return new ValidationResult(false, esc_html__('Width is required', 'cdekdelivery'));
            }

            if (!is_numeric($data['width'])) {
                return new ValidationResult(false, esc_html__('Width must be a number', 'cdekdelivery'));
            }

            if (empty($data['height'])) {
                return new ValidationResult(false, esc_html__('Height is required', 'cdekdelivery'));
            }

            if (!is_numeric($data['height'])) {
                return new ValidationResult(false, esc_html__('Height must be a number', 'cdekdelivery'));
            }

            return new ValidationResult(true);
        }
    }
}
