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
        <h3>Габариты упаковки</h3>
        <p id="cdek-create-order-error" class="form-field form-field-wide wc-order-status" style="display: none"></p>
        <p class="form-field form-field-wide wc-order-status">
            <input name="package_length" placeholder="Длина в см" type="text" value="<?php
            echo esc_html($length) ?>">
        </p>
        <p class="form-field form-field-wide wc-order-status">
            <input name="package_width" placeholder="Ширина в см" type="text" value="<?php
            echo esc_html($width) ?>">
        </p>
        <p class="form-field form-field-wide wc-order-status">
            <input name="package_height" placeholder="Высота в см" type="text" value="<?php
            echo esc_html($height) ?>">
        </p>
        <p class="form-field form-field-wide wc-order-status">
            <button id="create-order-btn" type="button" class="button refund-items"
                    data-action="<?php
                    echo esc_url(UrlHelper::buildRest("/order/$orderIdWP/create")) ?>">Отправить заказ в СДЕК
            </button>
        </p>
    </div>
</div>
