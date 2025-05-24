<?php
declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}


namespace Cdek\Actions {

    use Cdek\Config;
    use Cdek\Traits\CanBeCreated;

    class SaveOfficeToSessionAction
    {
        use CanBeCreated;

        /** @noinspection GlobalVariableUsageInspection */
        public function __invoke(): void
        {
            if (!isset($_POST['code'])) {
                return;
            }

            $officeCode = sanitize_text_field($_POST['code']);

            try {
                $session = WC()->session;

                if (is_null($session)) {
                    wp_send_json_error();
                    return;
                }

                $session->set(Config::DELIVERY_NAME.'_office_code', $officeCode);
            } catch (\Throwable $e) {
                wp_send_json_error();
                return;
            }

            wp_send_json_success();
        }
    }
}
