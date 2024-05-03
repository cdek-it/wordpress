<?php
defined('ABSPATH') or exit;
/** @var $order */
/** @var $orderNumber */
/** @var $orderIdWP */
/** @var $orderUuid */
/** @var $items */

/** @var $hasPackages */

use Cdek\Helpers\UrlHelper;

?>
<div id="cdek-create-order-form" <?php
if ($orderNumber): ?>style="display: none" <?php
endif ?> >
    <div id="setting_block">
        <h3>Габариты упаковки №1</h3>
        <div style="display: flex">
            <select id="selected_product">
                <option value="-1">Выберите товар</option>
                <?php
                foreach ($items as $key => $item): ?>
                    <option value="<?php
                    esc_attr_e($key) ?>"><?php echo esc_html($item['name']) ?></option>
                <?php
                endforeach; ?>
            </select>
        </div>
        <div>
            <?php
            foreach ($items as $id => $item): ?>
                <div id="product_<?php
                echo esc_attr($id) ?>" class="product_list" style="display: none;">
                    <p class="form-field form-field-wide wc-order-status" style="display: flex">
                        <input name="product_id" type="hidden" readonly value="<?php
                        echo esc_attr($id) ?>">
                        <input type="text" readonly value="<?php
                        echo esc_attr($item['name']) ?>">
                        <label for="quantity" style="margin-left: 10px; margin-right: 10px">x</label>
                        <input name="quantity" type="number" min="1" max="<?php
                        echo esc_attr($item['quantity']) ?>"
                               value="1"
                               style="width: 4em">
                    </p>
                </div>
            <?php
            endforeach; ?>
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
            <button id="send_package" type="button" class="button refund-items"
                    data-action="<?php
                    echo esc_url(UrlHelper::buildRest("/order/$orderIdWP/create")) ?>">Создать
                заказ
            </button>
        </p>
    </div>
</div>
