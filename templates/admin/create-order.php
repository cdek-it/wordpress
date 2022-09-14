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

    <script>
        (function ($) {
            $(document).ready(function () {
                let packageList = [];
                $('#selected_product').change(function () {
                    let productId = $('#selected_product').val();
                    $('#product_' + productId).css('display', 'flex')
                })
                $('#save_package').click(function () {
                    $('#package_list').show();
                    let packageData = {};
                    packageData.length = $('input[name=package_length]').val();
                    packageData.width = $('input[name=package_width]').val();
                    packageData.height = $('input[name=package_height]').val();
                    packageData.items = [];
                    $('.product_list').each(function (index, item) {
                        if ($(item).css('display') !== 'none') {
                            packageData.items.push([$(item).find('input[name=product_id]').val(), $(item).find('input[type=text]').val(), $(item).find('input[type=number]').val()]);
                        }
                    })

                    if (checkForm(packageData)) {
                        packageList.push(packageData);

                        let packageInfo = '';
                        packageInfo = `Упаковка №${packageList.length} (${packageData.length}х${packageData.width}х${packageData.height}):`;

                        packageData.items.forEach(function (item) {
                            packageInfo += `${item[1]} х${item[2]}, `
                        })

                        $('#package_list').append(`<p>${packageInfo.slice(0, -2)}</p>`)

                        calculateQuantity();
                        cleanForm();
                        checkFullPackage();
                    }
                })

                $('#send_package').click(function () {
                    $.ajax({
                        method: "POST",
                        url: "/wp-json/cdek/v1/create-order",
                        data: {
                            package_order_id: $('input[name=package_order_id]').val(),
                            package_data: JSON.stringify(packageList),
                        },
                        success: function (response) {
                            let resp = JSON.parse(response);
                            if (resp.state === 'error') {
                                window.alert(resp.message);
                            } else {
                                $('#cdek-create-order-form').hide();
                                $('#cdek-order-number').html(resp.code);
                                $('#cdek-order-waybill').attr('href', resp.waybill);
                                $('#cdek-info-order').show();
                            }
                        },
                        error: function (error) {
                            console.log({error: error});
                        }
                    });
                })

                function checkFullPackage() {
                    let option = $('#selected_product option');
                    if (option.length === 1) {
                        $('#setting_block').hide();
                        $('#save_package_btn_block').hide();
                        $('#send_package_btn_block').show();
                    }
                }

                function checkForm(packageData) {
                    if (packageData.length === '') {
                        alert('Не задана длина упаковки')
                        return false
                    }
                    if (packageData.width === '') {
                        alert('Не задана ширина упаковки')
                        return false
                    }
                    if (packageData.height === '') {
                        alert('Не задана высота упаковки')
                        return false
                    }
                    if (packageData.items.length === 0) {
                        alert('Не добавлены товары в упаковку')
                        return false
                    }
                    return true;
                }

                function calculateQuantity() {
                    $('.product_list').each(function (index, item) {
                        if ($(item).css('display') !== 'none') {
                            let max = $(item).find('input[type=number]').attr('max');
                            let current = max - $(item).find('input[type=number]').val()
                            if (current !== 0) {
                                $(item).find('input[type=number]').attr('max', current)
                            } else {
                                $('#selected_product option').each(function (ind, option) {
                                    if ($(option).text() === $(item).find('input[type=text]').val()) {
                                        $(option).remove();
                                    }
                                })
                            }
                        }
                    })
                }

                function cleanForm() {
                    $('#selected_product').val(-1);
                    $('.product_list').each(function (index, item) {
                        $(item).find('input[type=number]').val(1)
                        $(item).css('display', 'none');
                        $('input[name=package_length]').val('');
                        $('input[name=package_width]').val('');
                        $('input[name=package_height]').val('');
                    })
                }
            })
        })(jQuery);
    </script>
<?php } else { ?>
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
<?php } ?>