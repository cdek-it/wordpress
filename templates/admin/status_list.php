<?php
defined('ABSPATH') or exit;
/** @var $cdekStatuses */

/** @var $actionOrderAvailable */

if (empty($cdekStatuses)) { ?>
    <p><?php esc_html_e('No order statuses found. Try reloading the page later', 'cdekdelivery') ?></p>
    <?php
} else { ?>
    <p id="cdek-status-block" data-status-available="<?php
    echo esc_attr((int) $actionOrderAvailable) ?>"><?php esc_html_e('Order statuses', 'cdekdelivery') ?></p>
    <hr>
    <div class="cdek-order-status-elem-time"><?php
        echo esc_html($cdekStatuses[0]['time']) ?></div>
    <div id="cdek-order-status-btn" class="cdek-order-status-elem-name">
        <b><?php
            echo esc_html($cdekStatuses[0]['name']) ?></b>
        <div id="cdek-btn-arrow-up" class="cdek-btn-arrow">&#9660;</div>
        <div id="cdek-btn-arrow-down" class="cdek-btn-arrow">&#9650;</div>
    </div>
    <hr>
    <div id="cdek-order-status-list">
        <?php
        foreach (array_slice($cdekStatuses, 1) as $orderStatus) { ?>
            <div class="cdek-order-status-elem-time"><?php
                echo esc_html($orderStatus['time']) ?></div>
            <div class="cdek-order-status-elem-name"><b><?php
                   echo esc_html($orderStatus['name']) ?></b></div>
            <hr>
            <?php
        } ?>
    </div>
    <?php
}
