<?php

declare(strict_types=1);

namespace Cdek\UI {

    use Cdek\Config;
    use Cdek\Helpers\Logger;
    use Cdek\Helpers\UI;
    use Cdek\MetaKeys;
    use WC_AJAX;
    use WC_Order_Item;
    use WC_Product;
    use WC_Order;

    class AdminOrderProductFields
    {
        public function __invoke($itemId, ?WC_Order_Item $item, ?WC_Product $product): void
        {
            if ($item === null || $product === null) {
                return;
            }

            $order = $item->get_order();

            if (!$order instanceof WC_Order) {
                return;
            }

            if (!$this->checkShipping($order->get_shipping_methods())) {
                return;
            }

            $itemId = (int)$itemId;

            try {
                $jewel_uin_value = wc_get_order_item_meta($itemId, MetaKeys::JEWEL_UIN);
            } catch (\Exception $e) {
                Logger::warning(
                    sprintf(
                        'Failed to get UIN for item %d: %s',
                        $itemId,
                        $e->getMessage()
                    )
                );
                $jewel_uin_value = null;
            }

            echo '<br/>';

            if (empty($jewel_uin_value)){
                echo wc_render_action_buttons(
                    [
                        [
                            'action' => Config::DELIVERY_NAME . "-show_uin",
                            'url' => '#',
                            'name' => __('Add jewel UIN', 'cdekdelivery'),
                        ]
                    ]
                );
            }

            echo sprintf(
                '<div class="'. Config::DELIVERY_NAME . '-uin-input-container%s" data-id="%s">',
                empty($jewel_uin_value) ? ' hidden' : '',
                $itemId,
            );

            woocommerce_wp_text_input(
                [
                    'id' => Config::DELIVERY_NAME . "_jewel_uin_$itemId",
                    'name' => 'jewel_uin[' . $itemId . ']',
                    'label' => __('UIN: ', 'cdekdelivery'),
                    'value' => $jewel_uin_value,
                ]
            );

            echo wc_render_action_buttons(
                [
                    [
                        'action' => Config::DELIVERY_NAME . '-save_uin',
                        'url' => '#',
                        'name' => __('Save', 'cdekdelivery'),
                    ]
                ]
            );
            echo '</div>';

            $this->enqueueScript();
        }

        private function checkShipping(array $shipping_methods): bool
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
