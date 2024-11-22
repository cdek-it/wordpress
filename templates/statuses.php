<?php

defined('ABSPATH') or exit;

/**
 * @var \Cdek\Model\Order $order
 */

try {
    $statuses = empty($statuses) ? $order->loadLegacyStatuses() : $statuses;
} catch (Throwable $e) {
    $statuses = [];
}

if (empty($statuses)): ?>
    <p><?php esc_html_e('No order statuses found. Try reloading the page later', 'cdekdelivery') ?></p>
<?php
else: ?>
    <div class="status" aria-expanded="false">
        <div><?php echo esc_html($statuses[0]['time']->format('H:i d.m.Y')) ?></div>
        <div class="toggle name">
            <b><?php echo esc_html($statuses[0]['name']) ?></b>
            <span class="toggle-indicator"></span>
        </div>
        <hr>
        <div class="list">
            <?php
            foreach (array_slice($statuses, 1) as $status) : ?>
                <div><?php echo esc_html($status['time']->format('H:i d.m.Y')) ?></div>
                <div class="name"><b><?php echo esc_html($status['name']) ?></b></div>
                <hr>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>
