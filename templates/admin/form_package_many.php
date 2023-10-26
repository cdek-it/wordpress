<?php
/** @var $order */
/** @var $orderNumber */
/** @var $orderIdWP */
/** @var $orderUuid */
/** @var $items */
/** @var $hasPackages */

use Cdek\Helpers\UrlHelper;

?>
<div id="cdek-create-order-form" <?php if ($orderNumber) { ?>style="display: none" <?php } ?> >
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
            <button id="send_package" type="button" class="button refund-items" data-action="<?= UrlHelper::buildRest
            ("/order/$orderIdWP/create")?>">Создать
                заказ</button>
        </p>
    </div>
</div>
