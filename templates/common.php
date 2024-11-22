<?php
/**
 * @var \Cdek\Model\Order $order
 * @var array $meta
 */

use Cdek\Loader;

?>
<div class="loader" aria-disabled="true"><span></span></div>
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

<?php if (!empty($meta['errors'])): ?>
    <?php foreach ($meta['errors'] as $error): ?>
        <div class="notice notice-error">
            <p>
                <?php esc_html_e($error) ?>
            </p>
        </div>
    <?php endforeach ?>
<?php endif ?>

<?php if (!empty($meta['messages'])): ?>
    <?php foreach ($meta['messages'] as $msg): ?>
        <div class="notice notice-info">
            <p>
                <?php esc_html_e($msg) ?>
            </p>
        </div>
    <?php endforeach ?>
<?php endif ?>

<?php if (!empty($meta['success'])): ?>
    <?php foreach ($meta['success'] as $msg): ?>
        <div class="notice notice-success">
            <p>
                <?php esc_html_e($msg) ?>
            </p>
        </div>
    <?php endforeach ?>
<?php endif ?>
