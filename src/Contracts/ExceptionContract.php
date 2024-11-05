<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Contracts {

    use Cdek\Config;
    use Exception;
    use WP_Error;

    abstract class ExceptionContract extends Exception
    {
        protected string $key = 'cdek_error';
        protected int $status = 500;
        private ?array $data;

        public function __construct(
            string $message = '',
            ?array $data = null,
            bool $stopPropagation = true
        ) {
            $this->data    = $data ?? [];
            $this->message = '['.Config::PLUGIN_NAME."] $message";

            if ($stopPropagation && defined('REST_REQUEST')) {
                wp_die($this->getWpError(), '', $this->status);
            }

            parent::__construct($message);
        }

        private function getWpError(): WP_Error
        {
            // WP_Error при выводе на экран съедает часть data 0 ошибки, поэтому оригинальную ошибку добавляем 1
            $error = new WP_Error('cdek_error', 'Error happened at CDEKDelivery');
            $error->add($this->key, $this->message, $this->data);

            return $error;
        }

        final public function getData(): array
        {
            return $this->data;
        }
    }
}
