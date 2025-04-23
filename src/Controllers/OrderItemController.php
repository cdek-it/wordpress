<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Controllers {

    use Cdek\Config;
    use Cdek\Helpers\Logger;
    use Cdek\MetaKeys;
    use JsonException;

    class OrderItemController
    {
        public function __invoke(): void
        {
            if (!wp_doing_ajax()) {
                return;
            }

            $prefix = Config::DELIVERY_NAME;

            add_action("wp_ajax_$prefix-jewel-uin", [__CLASS__, 'jewelUin']);
        }

        public static function jewelUin(): void
        {
            check_ajax_referer(Config::DELIVERY_NAME);

            if (!current_user_can('edit_posts')) {
                wp_die(-2, 403);
            }

            try {
                $body = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                wp_send_json_error(
                    [
                        'message' => __('Invalid request data.', 'cdekdelivery'),
                    ]
                );
            }

            if (!isset($body['jewel_uin'], $body['item_id'])) {
                wp_send_json_error(
                    [
                        'message' => __('Invalid request data.', 'cdekdelivery'),
                    ]
                );
                die();
            }

            $item_id = (int)$body['item_id'];
            $jewel_uin = sanitize_text_field($body['jewel_uin']);

            try {
                if (wc_update_order_item_meta($item_id, MetaKeys::JEWEL_UIN, $jewel_uin)) {
                    wp_send_json_success(['message' => __('UIN saved successfully.', 'cdekdelivery')]);
                }
            } catch (\Exception $e) {
                Logger::warning(
                    sprintf(
                        'Failed to save UIN for item %d: %s',
                        $item_id,
                        $e->getMessage()
                    )
                );
            }

            wp_send_json_error(
                [
                    'message'   => __('Failed to save UIN.', 'cdekdelivery'),
                    'meta'      => MetaKeys::JEWEL_UIN,
                    'item_id'   => $item_id,
                    'jewel_uin' => $jewel_uin,
                ],
            );
        }
    }
}
