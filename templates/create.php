<?php

defined('ABSPATH') or exit;

/**
 * @var \Cdek\Model\Order $order
 * @var \Cdek\Model\ShippingItem $shipping
 */
?>
<div class="create">
    <h3><?php esc_html_e('Packaging dimensions', 'cdekdelivery') ?></h3>
    <p class="form-field form-field-wide">
        <input name="length" placeholder="<?php esc_html_e('Length in cm', 'cdekdelivery') ?>" type="text"
               value="<?php echo esc_attr($shipping->length) ?>">
    </p>
    <p class="form-field form-field-wide">
        <input name="width" placeholder="<?php esc_html_e('Width in cm', 'cdekdelivery') ?>" type="text"
               value="<?php echo esc_attr($shipping->width) ?>">
    </p>
    <p class="form-field form-field-wide">
        <input name="height" placeholder="<?php esc_html_e('Height in cm', 'cdekdelivery') ?>" type="text"
               value="<?php echo esc_attr($shipping->height) ?>">
    </p>
    <p class="form-field form-field-wide">
        <button type="button" class="button" data-id="<?php echo esc_attr($order->id) ?>">
            <?php esc_html_e('Sync to CDEK', 'cdekdelivery') ?>
        </button>
    </p>
</div>
