<?php

declare(strict_types=1);

namespace Cdek\UI {

    use Cdek\Config;
    use Cdek\Helpers\UI;
    use Cdek\Loader;
    use Cdek\MetaKeys;
    use WC_AJAX;

    class AdminOrderProductFields
    {
        public function __invoke($itemId, $item, $product)
        {
            if (!$item instanceof \WC_Order_Item) {
                return;
            }

            if (!$product instanceof \WC_Product) {
                return;
            }

            $order = $item->get_order();

            if (!$order instanceof \WC_Order) {
                return;
            }

            if (!$this->checkShipping($order->get_shipping_methods())) {
                return;
            }

            $jewel_uin_value = wc_get_order_item_meta($itemId, MetaKeys::JEWEL_UIN);

            echo '<br/>';

            if (empty($jewel_uin_value)){
                echo wc_render_action_buttons(
                    [
                        [
                            'action' => Config::DELIVERY_NAME . "-show_uin",
                            'url' => '#',
                            'name' => esc_html__('Add jewel UIN', 'cdekdelivery'),
                        ]
                    ]
                );
            }

            echo sprintf(
                '<div id="uin-input-container-%1$s" class="uin-input-container%2$s" data-id="%1$s">',
                $itemId,
                empty($jewel_uin_value) ? ' hidden' : ''
            );
            woocommerce_wp_text_input(
                [
                    'id' => Config::DELIVERY_NAME . "_jewel_uin_$itemId",
                    'name' => 'jewel_uin[' . $itemId . ']',
                    'label' => esc_html__('UIN: ', 'cdekdelivery'),
                    'value' => esc_attr($jewel_uin_value),
                ]
            );

            echo wc_render_action_buttons(
                [
                    [
                        'action' => Config::DELIVERY_NAME . '-save_uin',
                        'url' => '#',
                        'name' => esc_html__('Save', 'cdekdelivery'),
                    ]
                ]
            );
            echo '</div>';

            $this->enqueueScript();
        }

        public static function save()
        {
            if (isset($_POST['jewel_uin'], $_POST['item_id'])) {
                $item_id = intval($_POST['item_id']);
                $jewel_uin = sanitize_text_field($_POST['jewel_uin']);

                if (wc_update_order_item_meta($item_id, MetaKeys::JEWEL_UIN, $jewel_uin)) {
                    wp_send_json_success(['message' => __('UIN saved successfully.', 'cdekdelivery')]);
                }

                wp_send_json_error(
                    [
                        'message' => __('Failed to save UIN.', 'cdekdelivery'),
                        'meta' => MetaKeys::JEWEL_UIN,
                        'item_id' => $item_id,
                        'jewel_uin' => $jewel_uin,
                    ]
                );
            }

            wp_send_json_error(['message' => __('Invalid request data.', 'cdekdelivery')]);
        }

        private function checkShipping($shipping_methods): bool
        {
            foreach ($shipping_methods as $shipping_method) {
                if (strpos($shipping_method->get_method_id(), Config::DELIVERY_NAME) !== false) {
                    return true;
                }
            }

            return false;
        }

        private function enqueueScript(): void
        {
            UI::enqueueScript('cdek-order-item', 'cdek-order-item');

            wp_localize_script(
                'cdek-order-item',
                'cdek_order_item',
                [
                    'saver' => WC_AJAX::get_endpoint(Config::DELIVERY_NAME . '_save-uin'),
                ]
            );
        }
    }
}
