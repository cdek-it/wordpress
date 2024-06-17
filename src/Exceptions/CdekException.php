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
        private ?array $data;

        public function __construct(
            string $message = '',
            string $code = 'cdek_error',
            ?array $data = null,
            bool $stopPropagation = true
        ) {
            $this->code    = $code;
            $this->data    = $data ?? [];
            $this->message = $message;

            if ($stopPropagation && defined('REST_REQUEST')) {
                wp_die($this->getWpError());
            }

            parent::__construct($message);
        }

        private function getWpError(): WP_Error
        {
            $error = new WP_Error('cdek_error', 'Error happened at CDEKDelivery');
            $error->add($this->code, $this->message, $this->data);

            return $error;
        }

        final public function getData(): array
        {
            return $this->data;
        }
    }
}
