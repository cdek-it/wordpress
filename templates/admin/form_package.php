<?php
/** @var $orderNumber */
/** @var $orderIdWP */
?>
<div>
    <div id="cdek-create-order-form" <?php if ($orderNumber) { ?>style="display: none" <?php } ?>>
        <h3>Габариты упаковки</h3>
        <p id="cdek-create-order-error" class="form-field form-field-wide wc-order-status" style="display: none"></p>
        <input name="package_order_id" type="hidden" value="<?php echo $orderIdWP ?>">
        <p class="form-field form-field-wide wc-order-status">
            <input name="package_length" placeholder="Длина в см" type="text">
        </p>
        <p class="form-field form-field-wide wc-order-status">
            <input name="package_width" placeholder="Ширина в см" type="text">
        </p>
        <p class="form-field form-field-wide wc-order-status">
            <input name="package_height" placeholder="Высота в см" type="text">
        </p>
        <p class="form-field form-field-wide wc-order-status">
            <button id="create-order-btn" type="button" class="button refund-items">Отправить заказ в СДЕК</button>
        </p>
    </div>
</div>