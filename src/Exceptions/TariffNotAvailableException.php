<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    class TariffNotAvailableException extends CdekException
    {

        public function __construct(array $availableTariffs)
        {
            parent::__construct('Tariff not available', 'cdek_error.calc.tariff', $availableTariffs, false);
        }
    }
}
