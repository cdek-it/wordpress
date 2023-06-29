<?php
/** @var $orderNumber */
/** @var $orderUuid */
?>
<div id="cdek-info-order" <?php if (!$orderNumber) { ?>style="display: none" <?php } ?>>
    <div>
        <h3 style="margin-bottom: 0">Заказ создан</h3>
        <div id="cdek-order-number-block">
            <p id="cdek-order-number" data-number="<?php echo $orderNumber; ?>">№ <b><?php echo $orderNumber; ?></b></p>
            <input id="cdek-order-number-input" type="hidden" value="<?php echo $orderNumber; ?>">
            <a id="cdek-order-waybill" target="_blank"
               href="/wp-json/cdek/v1/get-waybill?number=<?php echo $orderUuid; ?>">Получить квитанцию</a>
        </div>
    </div>
    <hr>
    <div>
        <p id="cdek-delete-order-error" class="form-field form-field-wide wc-order-status" style="display: none"></p>
        <a id="delete-order-btn">Отменить</a>
    </div>
</div>