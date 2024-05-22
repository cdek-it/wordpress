<?php
defined('ABSPATH') or exit;
/** @var $orderNumber */
/** @var $height */
/** @var $length */
/** @var $width */

/** @var $orderIdWP */

use Cdek\Helpers\UrlHelper;

?>
<div>
    <div id="cdek-create-order-form" <?php
    if ($orderNumber) { ?>style="display: none" <?php
    } ?>>
        <h3><?php esc_html_e('Packaging dimensions', 'cdekdelivery') ?></h3>
        <p id="cdek-create-order-error" class="form-field form-field-wide wc-order-status" style="display: none"></p>
        <p class="form-field form-field-wide wc-order-status">
            <input name="package_length" placeholder="<?php esc_html_e('Length in cm', 'cdekdelivery') ?>" type="text" value="<?php
            echo esc_attr($length) ?>">
        </p>
        <p class="form-field form-field-wide wc-order-status">
            <input name="package_width" placeholder="<?php esc_html_e('Width in cm', 'cdekdelivery') ?>" type="text" value="<?php
            echo esc_attr($width) ?>">
        </p>
        <p class="form-field form-field-wide wc-order-status">
            <input name="package_height" placeholder="<?php esc_html_e('Height in cm', 'cdekdelivery') ?>" type="text" value="<?php
            echo esc_attr($height) ?>">
        </p>
        <p class="form-field form-field-wide wc-order-status">
            <button id="create-order-btn" type="button" class="button refund-items"
                    data-action="<?php
                    echo esc_url(UrlHelper::buildRest("/order/$orderIdWP/create")) ?>"><?php esc_html_e('Send an order to CDEK', 'cdekdelivery') ?>
            </button>
        </p>
    </div>
</div>
