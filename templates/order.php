<?php

defined('ABSPATH') or exit;

use Cdek\Loader;

/**
 * @var \Cdek\Model\Order $order
 * @var \Cdek\Model\ShippingItem $shipping
 */

$intake = $order->getIntake();
?>
<?php if ($order->isLocked()): ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php echo Loader::getPluginName() ?>:</strong>
            <?php esc_html_e(
                'Editing of waybill is not available due to a change in the order status in the CDEK system',
                'cdekdelivery',
            ) ?>
        </p>
    </div>
<?php endif ?>

<div>
    <p><?php esc_html_e('Waybill №', 'cdekdelivery') ?> <b>
            <?php echo esc_html($order->number) ?></b></p>
    <?php if (!empty($intake->number)): ?>
        <p>
            <?php esc_html_e('Intake №', 'cdekdelivery') ?> <b>
                <?php echo esc_html($intake->number) ?></b></p>
    <?php endif ?>
    <hr>
    <div><?php include 'statuses.php' ?></div>
    <a class="print actions" data-action="waybill" data-id="<?php echo esc_attr($order->id) ?>"><?php esc_html_e('Print waybill', 'cdekdelivery') ?></a>
    <a class="print actions" data-action="barcode" data-id="<?php echo esc_attr($order->id) ?>"><?php esc_html_e('Print barcode', 'cdekdelivery') ?></a>
</div>

<?php if (!$order->isLocked()) : ?>
    <?php if (empty($intake->number)) : ?>
        <div aria-expanded="false" class="intake">
            <a class="actions toggle"><?php esc_html_e('Call the courier', 'cdekdelivery') ?></a>
            <?php include 'intake.php' ?>
        </div>
    <?php endif ?>

    <hr>
    <div class="submitbox">
        <?php if (!empty($intake->number)) : ?>
            <a class="submitdelete deletion actions" data-action="intake_delete"
               data-id="<?php echo esc_attr($order->id) ?>">
                <?php esc_html_e("Cancel the courier call", 'cdekdelivery') ?>
            </a>
        <?php endif ?>

        <a class="submitdelete deletion actions" data-action="delete"
           data-id="<?php echo esc_attr($order->id) ?>">
            <?php esc_html_e("Delete waybill", 'cdekdelivery') ?>
        </a>
    </div>
<?php endif ?>
