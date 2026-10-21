<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    use Cdek\Contracts\ExceptionContract;

    class OfficeNotSelectedException extends ExceptionContract {
        protected string $key = 'office.not_selected';

        public function __construct()
        {
            $this->message = $this->message ?: esc_html__('Order pickup point not selected.', 'cdekdelivery');

            parent::__construct();
        }
    }
}
