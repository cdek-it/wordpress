<?php
defined('ABSPATH') or exit;
/** @var $orderNumber */
/** @var $orderIdWP */
/** @var $orderUuid */
/** @var $dateMin */
/** @var $dateMax */
/** @var $courierNumber */

/** @var $fromDoor */

use Cdek\Helpers\UrlHelper;

?>

<div id="cdek-courier-block">
    <div>
        <div>
            <p><?php esc_html_e('Courier waiting date', 'cdekdelivery') ?>:</p>
            <input id="cdek-courier-date" type="date" min='<?php
            echo esc_attr($dateMin) ?>' max='<?php
            echo esc_attr($dateMax) ?>'>
        </div>
        <div>
            <p><?php esc_html_e('Courier awaiting time', 'cdekdelivery') ?>:</p>
            <label for="cdek-courier-startime"><?php esc_html_e('from', 'cdekdelivery') ?></label>
            <input id="cdek-courier-startime" type="time" list="avail">
            <label for="cdek-courier-endtime"><?php esc_html_e('to', 'cdekdelivery') ?></label>
            <input id="cdek-courier-endtime" type="time" list="avail">
            <datalist id="avail">
                <option value="09:00">
                <option value="10:00">
                <option value="11:00">
                <option value="12:00">
                <option value="13:00">
                <option value="14:00">
                <option value="15:00">
                <option value="16:00">
                <option value="17:00">
                <option value="18:00">
                <option value="19:00">
                <option value="20:00">
                <option value="21:00">
                <option value="22:00">
            </datalist>
        </div>
    </div>
    <input id="cdek-courier-name" type="text" placeholder="<?php esc_html_e('Full name', 'cdekdelivery') ?>">
    <input id="cdek-courier-phone" type="tel" min="0" placeholder="<?php esc_html_e('Phone', 'cdekdelivery') ?>">
    <?php
    echo wc_help_tip(esc_html__('Must be transmitted in international format: country code (for Russia +7) and the number itself (10 or more digits)', 'cdekdelivery')); ?>
    <input id="cdek-courier-address" title="tooltip" type="text" placeholder="<?php esc_html_e('Street address', 'cdekdelivery') ?>">
    <label for="cdek-courier-address">
        <?php
        echo wc_help_tip(esc_html__('The city is taken from the plugin settings. In the Address field, enter only the street, house, apartment', 'cdekdelivery')); ?>
    </label>
    <input id="cdek-courier-comment" type="text" placeholder="<?php esc_html_e('Comment', 'cdekdelivery') ?>">
    <?php
    if (!$fromDoor) { ?>
        <input id="cdek-courier-package-desc" type="text" placeholder="<?php esc_html_e('Description of cargo', 'cdekdelivery') ?>">
        <div>
            <div style="display: inline-flex; margin-top: 5px; align-items: center;">
                <p style="margin: auto"><?php esc_html_e('Dimensions', 'cdekdelivery') ?></p>
                <?php
                echo wc_help_tip(esc_html__('For warehouse tariffs, you can send several orders at once. Therefore, the dimensions may differ from those indicated when creating the order. For door tariffs, you can duplicate those that were specified when creating the order.', 'cdekdelivery')); ?>
            </div>

            <input id="cdek-courier-weight" type="number" min="0" placeholder="<?php esc_html_e('Weight in kg', 'cdekdelivery') ?>">
            <input id="cdek-courier-length" type="number" min="0" placeholder="<?php esc_html_e('Length in cm', 'cdekdelivery') ?>">
            <input id="cdek-courier-width" type="number" min="0" placeholder="<?php esc_html_e('Width in cm', 'cdekdelivery') ?>">
            <input id="cdek-courier-height" type="number" min="0" placeholder="<?php esc_html_e('Height in cm', 'cdekdelivery') ?>">
        </div>
        <?php
    } ?>
    <div>
        <label for="cdek-courier-startime"><?php esc_html_e('Telephone call required', 'cdekdelivery') ?></label>
        <input id="cdek-courier-call" type="checkbox">
    </div>
    <p id="cdek-courier-error" style="display: none"></p>
    <input id="cdek-courier-send-call" class="button save_order button-primary" type="button" value="<?php esc_html_e('Send',
                                                                                                              'cdekdelivery') ?>"
           data-action="<?php
           echo esc_url(UrlHelper::buildRest("order/$orderIdWP/courier")) ?>">
</div>
