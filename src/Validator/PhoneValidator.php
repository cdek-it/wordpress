<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Validator {

    use Cdek\CoreApi;
    use Cdek\Exceptions\External\HttpClientException;
    use Cdek\Exceptions\InvalidPhoneException;
    use Cdek\Traits\CanBeCreated;

    class PhoneValidator
    {
        use CanBeCreated;

        /**
         * @throws \Cdek\Exceptions\InvalidPhoneException
         */
        public function __invoke(string $phone, string $countryCode = null): string
        {
            try {
                return (new CoreApi)->validatePhone($phone, $countryCode);
            } catch (HttpClientException $e) {
                throw new InvalidPhoneException($phone);
            }
        }
    }
}
