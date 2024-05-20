<?php
defined('ABSPATH') or exit;
/** @var $orderNumber */
/** @var $orderStatusList */
/** @var $orderUuid */
/** @var $dateMin */
/** @var $dateMax */
/** @var $courierNumber */
/** @var $orderIdWP */
/** @var $fromDoor */

/** @var $actionOrderAvailable */

use Cdek\Helpers\UrlHelper;

?>

<div id="cdek-info-order" <?php
if (!$orderNumber) { ?>style="display: none" <?php
} ?>>
    <div>
        <h3 style="margin-bottom: 0">Заказ создан</h3>
        <div id="cdek-order-number-block">
            <div>
                <div id="cdek-order-status-block">
                    <?php
                    include 'status_list.php'; ?>
                </div>
                <p id="cdek-order-number">№ <b><?php
                        echo esc_html($orderNumber) ?></b></p>
                <a id="cdek-order-waybill"
                   href="<?php
                   echo esc_url(UrlHelper::buildRest("order/$orderIdWP/waybill")) ?>">Получить
                    накладную</a>
                <a id="cdek-order-barcode"
                   href="<?php
                   echo esc_url(UrlHelper::buildRest("order/$orderIdWP/barcode")) ?>">Получить ШК</a>
                <?php
                if ($actionOrderAvailable) { ?>
                    <p id="cdek-order-courier">
                        Вызвать курьера</p>
                    <?php
                } ?>
            </div>

            <div id="cdek-courier-result-block"
                 <?php
                 if (empty($courierNumber)) { ?>style="display: none;" <?php
            } else { ?> style="margin-top: 10px;" <?php
            } ?>>
                <hr>
                <p id="cdek-courier-info">Номер заявки: <?php
                    echo esc_html($courierNumber) ?></p>
                <p id="cdek-courier-delete"
                   data-action="<?php
                   echo esc_url(UrlHelper::buildRest("order/$orderIdWP/courier/delete")) ?>">Отменить
                    заявку</p>
            </div>

            <div id="call-courier-form">
                <?php
                include 'call_courier_form.php'; ?>
            </div>

        </div>
    </div>
    <?php
    if ($actionOrderAvailable) { ?>
        <hr>
        <div>
            <p id="cdek-delete-order-error" class="form-field form-field-wide wc-order-status"
               style="display: none"></p>
            <a id="delete-order-btn" href="<?php
            echo esc_url(UrlHelper::buildRest("order/$orderIdWP/delete")) ?>">Отменить
                заказ</a>
        </div>
        <?php
    } ?>
</div>
