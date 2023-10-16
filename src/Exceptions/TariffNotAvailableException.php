<?php

namespace Cdek\Exceptions;

use RuntimeException;

class TariffNotAvailableException extends RuntimeException {
    public array $availableTariffs;

    public function __construct(array $availableTariffs) {
        $this->availableTariffs = $availableTariffs;
        parent::__construct('Tariff not available');
    }
}
