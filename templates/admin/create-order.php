<?php
/** @var $order */
/** @var $orderUuid */
/** @var $orderId */
/** @var $waybill */
/** @var $items */
/** @var $hasPackages */
?>
<?php if ($hasPackages) { ?>
    <div>
            <div id="cdek-create-order-form" <?php if ($orderUuid) { ?>style="display: none" <?php } ?> >
                <div id="setting_block">
                    <h3>Габариты упаковки №1</h3>
                    <div style="display: flex">
                        <select id="selected_product">
                            <option value="-1">Выберите товар</option>
                            <?php foreach ($items as $key => $item) { ?>
                                <option value="<?php echo $key ?>"><?php echo $item['name'] ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div>
                        <?php foreach ($items as $id => $item) { ?>
                            <div id="product_<?php echo $id ?>" class="product_list" style="display: none;">
                                <p class="form-field form-field-wide wc-order-status" style="display: flex">
                                    <input name="product_id" type="hidden" readonly value="<?php echo $id ?>">
                                    <input type="text" readonly value="<?php echo $item['name'] ?>">
                                    <label for="quantity" style="margin-left: 10px; margin-right: 10px">x</label>
                                    <input name="quantity" type="number" min="1" max="<?php echo $item['quantity'] ?>" value="1"
                                           style="width: 4em">
                                </p>
                            </div>
                        <?php } ?>
                    </div>
                    <div id="package_parameters">
                        <p class="form-field form-field-wide wc-order-status" style="display: none">
                            <input name="package_order_id" type="text" value="<?php echo $orderId?>">
                        </p>
                        <p class="form-field form-field-wide wc-order-status">
                            <label for="package_length">Длина см</label>
                            <input name="package_length" type="text">
                        </p>
                        <p class="form-field form-field-wide wc-order-status">
                            <label for="package_width">Ширина см</label>
                            <input name="package_width" type="text">
                        </p>
                        <p class="form-field form-field-wide wc-order-status">
                            <label for="package_height">Высота см</label>
                            <input name="package_height" type="text">
                        </p>
                    </div>
                </div>


                <div id="package_list" style="display: none">
                </div>

                <div id="save_package_btn_block">
                    <p class="form-field form-field-wide wc-order-status">
                        <button id="save_package" type="button" class="button refund-items">Сохранить</button>
                    </p>
                </div>

                <div id="send_package_btn_block" style="display: none">
                    <p class="form-field form-field-wide wc-order-status">
                        <button id="send_package" type="button" class="button refund-items">Создать заказ</button>
                    </p>
                </div>
            </div>


<?php } else { ?>
    <div>
        <div id="cdek-create-order-form" <?php if ($orderUuid) { ?>style="display: none" <?php } ?>
            <h3>Габариты упаковки</h3>
            <input name="package_order_id" type="hidden" value="<?php echo $order->get_id() ?>">
            <p class="form-field form-field-wide wc-order-status">
                <label for="package_length">Длина см</label>
                <input name="package_length" type="text">
            </p>
            <p class="form-field form-field-wide wc-order-status">
                <label for="package_width">Ширина см</label>
                <input name="package_width" type="text">
            </p>
            <p class="form-field form-field-wide wc-order-status">
                <label for="package_height">Высота см</label>
                <input name="package_height" type="text">
            </p>
            <p class="form-field form-field-wide wc-order-status">
                <button id="create-order-btn" type="button" class="button refund-items">Отправить заказ в СДЕК</button>
            </p>
        </div>
<?php } ?>

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
