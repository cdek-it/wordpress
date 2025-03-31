<?php

use Cdek\Loader;

defined('ABSPATH') or exit;

/**
 * @var \Cdek\Model\Order $order
 */

?>

<div class="notice notice-warning">
    <p>
        <strong><?php echo Loader::getPluginName() ?>:</strong>
        <?php esc_html_e(
            'Waybill info is not available due to a processing of the order status in the CDEK system',
            'cdekdelivery',
        ) ?>
    </p>
</div>

<div>
    <p><?php esc_html_e('Waybill', 'cdekdelivery') ?> <b>
            <?php echo esc_html($order->number) ?></b></p>
</div>

<hr>
<div class="submitbox">
    <a class="submitdelete deletion actions" data-action="delete"
       data-id="<?php echo esc_attr($order->id) ?>">
        <?php esc_html_e("Clear waybill from local DB", 'cdekdelivery') ?>
    </a>
</div>
