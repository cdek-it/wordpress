<?php
/** @var $order */
/** @var $orderUuid */
/** @var $waybill */
?>
    <div>
        <div id="cdek-create-order-form" <?php if ($orderUuid) { ?>style="display: none" <?php } ?>
            <h3>Габариты упаковки</h3>
            <input name="package_order_id" type="hidden" value="<?php echo $order->get_id() ?>">
            <p class="form-field form-field-wide wc-order-status">
                <label for="package_length">Длина</label>
                <input name="package_length" type="text">
            </p>
            <p class="form-field form-field-wide wc-order-status">
                <label for="package_width">Ширина</label>
                <input name="package_width" type="text">
            </p>
            <p class="form-field form-field-wide wc-order-status">
                <label for="package_height">Высота</label>
                <input name="package_height" type="text">
            </p>
            <p class="form-field form-field-wide wc-order-status">
                <button id="create-order-btn" type="button" class="button refund-items">Отправить заказ в СДЕК</button>
            </p>
        </div>
        <div id="cdek-info-order" <?php if (!$orderUuid) { ?>style="display: none" <?php } ?>>
            <p class="form-field form-field-wide wc-order-status">
                <label for="package_length">Номер заказа CDEK: </label>
            </p>
            <div>
                <p id="cdek-order-number"><?php echo $orderUuid; ?></p>
                <a id="cdek-order-waybill" target="_blank" href="/wp-json/cdek/v1/get-waybill?number=<?php echo $waybill;?>">Получить квитанцию</a>
            </div>
            <p class="form-field form-field-wide wc-order-status">
                <button id="delete-order-btn" type="button" class="button refund-items">Отменить</button>
            </p>
        </div>
    </div>
<?php ?>