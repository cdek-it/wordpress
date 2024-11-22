<?php

defined('ABSPATH') or exit;

use Cdek\Model\Tariff;

/**
 * @var \Cdek\Model\Order $order
 * @var \Cdek\Model\ShippingItem $shipping
 */

$dateMin = gmdate('Y-m-d');
$dateMax = gmdate('Y-m-d', strtotime($dateMin." +31 days"));

?>

<div class="form">
    <div>
        <label><?php esc_html_e('Date', 'cdekdelivery') ?>:
            <input type="date" min='<?php echo esc_attr($dateMin) ?>'
                   max='<?php echo esc_attr($dateMax) ?>' name="date" required>
        </label>
    </div>
    <div>
        <label><?php esc_html_e('From', 'cdekdelivery') ?>
            <input type="time" list="avail" name="from" required>
        </label>
        <label><?php esc_html_e('to', 'cdekdelivery') ?>
            <input type="time" list="avail" name="to" required>
        </label>
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
    <label>
        <?php esc_html_e('Comment', 'cdekdelivery') ?>
        <input type="text" name="comment">
    </label>
    <?php if (!Tariff::isFromDoor((int)($shipping->tariff ?: $order->tariff_id))) : ?>
        <label>
            <?php esc_html_e('Description', 'cdekdelivery') ?>
            <input type="text" name="desc">
        </label>
        <div>
            <div style="display: inline-flex; margin-top: 5px; align-items: center;">
                <label style="margin: auto">
                    <?php esc_html_e('Weight in kg', 'cdekdelivery') ?>
                </label>
                <?php echo wc_help_tip(
                    esc_html__(
                        'For warehouse tariffs, you can send several orders at once. Therefore, the dimensions may differ from those indicated when creating the order. For door tariffs, you can duplicate those that were specified when creating the order.',
                        'cdekdelivery',
                    ),
                ) ?>
            </div>
            <input type="number" min="0" name="weight" style="width: 150px" required>
        </div>
    <?php endif ?>
    <div>
        <label><?php esc_html_e('Telephone call required', 'cdekdelivery') ?>
            <input type="checkbox" name="call">
        </label>
    </div>
    <div class="buttons">
        <button class="button" type="button" data-id="<?php echo esc_attr($order->id) ?>">
            <?php esc_html_e('Call', 'cdekdelivery') ?>
        </button>
    </div>
</div>
