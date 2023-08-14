<?php
/** @var $orderNumber */
/** @var $orderUuid */
/** @var $dateMin */
/** @var $dateMax */
/** @var $courierNumber */

/** @var $fromDoor */

use Cdek\Helper;

?>
<div id="cdek-info-order" <?php if (!$orderNumber) { ?>style="display: none" <?php } ?>>
    <div>
        <h3 style="margin-bottom: 0">Заказ создан</h3>
        <div id="cdek-order-number-block">
            <div>
                <p id="cdek-order-number" data-number="<?= $orderNumber ?>">№ <b><?= $orderNumber ?></b>
                </p>
                <input id="cdek-order-number-input" type="hidden" value="<?= $orderNumber ?>">
                <a id="cdek-order-waybill" target="_blank"
                   href="<?= Helper::buildRestUrl('cdek/v1/get-waybill', ['number' => $orderUuid], '') ?>">Получить
                    квитанцию</a>
                <a id="cdek-order-barcode" target="_blank"
                   href="<?= Helper::buildRestUrl("order/$orderNumber/barcode") ?>">Получить ШК</a>
                <p id="cdek-order-courier" <?php if (!empty($courierNumber)) { ?>style="display: none" <?php } ?>>
                    Вызвать курьера</p>
            </div>

            <div id="cdek-courier-result-block"
                 <?php if (empty($courierNumber)) { ?>style="display: none;" <?php } else { ?> style="margin-top: 10px;" <?php } ?>>
                <hr>
                <p id="cdek-courier-info">Номер заявки: <?= $courierNumber ?></p>
                <p id="cdek-courier-delete">Отменить заявку</p>
            </div>

            <div id="call-courier-form">

                <?php include 'call_courier_form.php'; ?>

            </div>


        </div>
    </div>
    <hr>
    <div>
        <p id="cdek-delete-order-error" class="form-field form-field-wide wc-order-status" style="display: none"></p>
        <a id="delete-order-btn">Отменить заказ</a>
    </div>
</div>
