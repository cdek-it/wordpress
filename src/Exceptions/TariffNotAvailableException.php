<?php
declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    use Cdek\Contracts\ExceptionContract;

    class TariffNotAvailableException extends ExceptionContract
    {
        protected string $key = 'calc.tariff';

        public function __construct(array $availableTariffs)
        {
            parent::__construct('Tariff not available', $availableTariffs, false);
        }
    }
}
