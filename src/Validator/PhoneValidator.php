<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Validator {

    use Cdek\Exceptions\InvalidPhoneException;
    use Cdek\Traits\CanBeCreated;
    use libphonenumber\NumberParseException;
    use libphonenumber\PhoneNumberUtil;

    class PhoneValidator
    {
        use CanBeCreated;
        /**
         * @throws \Cdek\Exceptions\InvalidPhoneException
         */
        public function __invoke(string $phone, string $countryCode = null): void
        {
            $phoneNumUtil = PhoneNumberUtil::getInstance();
            try {
                if (!$phoneNumUtil->isValidNumber($phoneNumUtil->parse($phone, $countryCode))) {
                    throw new InvalidPhoneException($phone);
                }
            } catch (NumberParseException $e) {
                throw new InvalidPhoneException($phone);
            }
        }
    }
}
