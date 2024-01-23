<?php
defined('ABSPATH') or exit;
/** @var $cdekStatuses */

/** @var $actionOrderAvailable */

use Cdek\Config;

if (empty($cdekStatuses)) { ?>
    <p>Статусы заказа не найдены. Попробуйте перезагрузить страницу позднее</p>
    <?php
} else { ?>
    <p id="cdek-status-block" data-status-available="<?php
    echo (int) $actionOrderAvailable ?>">Статусы заказа</p>
    <hr>
    <div class="cdek-order-status-elem-time"><?php
        echo $cdekStatuses[0]['time'] ?></div>
    <div id="cdek-order-status-btn" class="cdek-order-status-elem-name">
        <b><?php
            echo $cdekStatuses[0]['name'] ?></b>
        <div id="cdek-btn-arrow-up" class="cdek-btn-arrow"><?php
            echo Config::CODE_ARROW_DOWN; ?></div>
        <div id="cdek-btn-arrow-down" class="cdek-btn-arrow"><?php
            echo Config::CODE_ARROW_UP; ?></div>
    </div>
    <hr>
    <div id="cdek-order-status-list">
        <?php
        foreach (array_slice($cdekStatuses, 1) as $orderStatus) { ?>
            <div class="cdek-order-status-elem-time"><?php
                echo $orderStatus['time'] ?></div>
            <div class="cdek-order-status-elem-name"><b><?php
                    echo $orderStatus['name'] ?></b></div>
            <hr>
            <?php
        } ?>
    </div>
    <?php
} ?>
