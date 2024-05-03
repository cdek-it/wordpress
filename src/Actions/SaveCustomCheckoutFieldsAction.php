<?php

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Actions {

    use WC_Order;

    class SaveCustomCheckoutFieldsAction
    {
        public function __invoke(WC_Order $order, $data): void
        {
            foreach ([
                         'passport_series',
                         'passport_number',
                         'passport_date_of_issue',
                         'passport_organization',
                         'tin',
                         'passport_date_of_birth',
                     ] as $key) {
                if (!isset($_POST[$key])) {
                    continue;
                }

                $order->update_meta_data("_$key", sanitize_text_field($_POST[$key]));
            }
        }
    }
}
