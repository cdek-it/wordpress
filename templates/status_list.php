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
    <p><?php
        esc_html_e('No order statuses found. Try reloading the page later', 'cdekdelivery') ?></p>
<?php
else: ?>
    <hr>
    <div class="cdek-order-status-elem-time"><?php
        echo esc_html($statuses[0]['time']->format('H:i d.m.Y')) ?></div>
    <div id="cdek-order-status-btn" class="cdek-order-status-elem-name">
        <b><?php
            echo esc_html($statuses[0]['name']) ?></b>
        <div id="cdek-btn-arrow-up" class="cdek-btn-arrow">&#9660;</div>
        <div id="cdek-btn-arrow-down" class="cdek-btn-arrow">&#9650;</div>
    </div>
    <hr>
    <div id="cdek-order-status-list">
        <?php
        foreach (array_slice($statuses, 1) as $status) { ?>
            <div class="cdek-order-status-elem-time"><?php
                echo esc_html($status['time']->format('H:i d.m.Y')) ?></div>
            <div class="cdek-order-status-elem-name"><b><?php
                    echo esc_html($status['name']) ?></b></div>
            <hr>
            <?php
        } ?>
    </div>
<?php
endif; ?>
