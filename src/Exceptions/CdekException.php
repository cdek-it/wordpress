<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions {

    use Exception;
    use WP_Error;

    abstract class CdekException extends Exception
    {
        protected $code = 'cdek_error';
        private array $data;

        public function __construct(string $message = "",
                                    string $code = 'cdek_error',
                                    ?array  $data = null,
                                    bool   $stopPropagation = true)
        {
            $this->code = $code;
            $this->data = $data;

            if ($stopPropagation) {
                wp_die($this->getWpError());
            }

            parent::__construct($message);
        }

        private function getWpError(): WP_Error
        {
            return new WP_Error($this->code, $this->message, $this->data);
        }

        final public function getData(): array
        {
            return $this->data;
        }
    }
}
