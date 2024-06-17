<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Exceptions{

    use WP_Error;

    class CdekScheduledTaskException
    {
        public function __construct(
            string $message,
            string $code = 'cdek_error',
            ?array $data = null
        )
        {
            $error = new WP_Error('cdek_error', 'Error happened at CDEKDelivery');
            $error->add($code, $message, $data);
            wp_die($error);
        }
    }
}
