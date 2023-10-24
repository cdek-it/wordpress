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
                method: 'POST',
                url: window.cdek_rest_order_api_path.create_order,
                data: {
                    package_order_id: $('input[name=package_order_id]').val(),
                    package_data: JSON.stringify(packageList),
                },
                beforeSend: function() {
                    $('#cdek-loader').show();
                },
                complete: function() {
                    $('#cdek-loader').hide();
                },
                success: function (response) {
                    if (!response.state) {
                        $('#cdek-create-order-error').text(response.message).show();
                    } else {
                        if (response.door) {
                            $('#cdek-courier-result-block').hide()
                            $('#cdek-order-courier').show()
                        }
                        $('#cdek-create-order-form').hide();
                        $('#cdek-order-number').html(`№ <b>${response.code}</b>`);
                        $('#cdek-order-number-input').val(response.code);
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


        $('#create-order-btn').click(function() {
            $('#cdek-create-order-error').hide();
            $.ajax({
                method: 'POST',
                url: window.cdek_rest_order_api_path.create_order,
                data: {
                    package_order_id: $('input[name=package_order_id]').val(),
                    package_length: $('input[name=package_length]').val(),
                    package_width: $('input[name=package_width]').val(),
                    package_height: $('input[name=package_height]').val(),
                },
                beforeSend: function() {
                    $('#cdek-loader').show();
                },
                complete: function() {
                    $('#cdek-loader').hide();
                },
                success: function(response) {
                    if (!response.state) {
                        $('#cdek-create-order-error')
                          .text(response.message)
                          .show();
                    } else {
                        if (response.door) {
                            $('#cdek-courier-result-block').hide();
                            $('#cdek-order-courier').show();
                        }
                        $('#cdek-create-order-form').hide();
                        $('#cdek-order-number')
                          .html(`№ <b>${response.code}</b>`);
                        $('#cdek-order-number-input').val(response.code);
                        $('#cdek-info-order').show();
                    }
                },
                error: function(error) {
                    console.log({ error: error });
                },
            });
        });

        $('#delete-order-btn').click(function(event) {
            $(event.target).addClass('clicked');
            $('#cdek-create-order-error').hide();
            $('#cdek-courier-error').hide();
            $.ajax({
                method: 'POST',
                url: window.cdek_rest_order_api_path.delete_order,
                data: {
                    number: $('#cdek-order-number-input').val(),
                    order_id: $('input[name=package_order_id]').val(),
                },
                beforeSend: function() {
                    $('#cdek-loader').show();
                },
                complete: function() {
                    $('#cdek-loader').hide();
                },
                success: function(response) {
                    if (!response.state) {
                        $('#cdek-delete-order-error')
                          .text(response.message)
                          .show();
                        $('#delete-order-btn').hide();
                    } else {
                        alert(response.message);
                        $(event.target).removeClass('clicked');
                        $('#cdek-create-order-form').show();
                        $('#cdek-info-order').hide();
                    }
                },
                error: function(error) {
                    console.log({ error: error });
                },
            });
        });

        $('#cdek-courier-send-call').click(function(event) {
            $('#cdek-courier-error').hide();
            $.ajax({
                method: 'POST',
                url: window.cdek_rest_order_api_path.call_courier,
                data: {
                    order_id: $('input[name=package_order_id]').val(),
                    date: $('#cdek-courier-date').val(),
                    starttime: $('#cdek-courier-startime').val(),
                    endtime: $('#cdek-courier-endtime').val(),
                    name: $('#cdek-courier-name').val(),
                    phone: $('#cdek-courier-phone').val(),
                    address: $('#cdek-courier-address').val(),
                    desc: $('#cdek-courier-package-desc').val(),
                    comment: $('#cdek-courier-comment').val(),
                    weight: $('#cdek-courier-weight').val(),
                    length: $('#cdek-courier-length').val(),
                    width: $('#cdek-courier-width').val(),
                    height: $('#cdek-courier-height').val(),
                    need_call: $('#cdek-courier-call').prop('checked'),
                },
                beforeSend: function() {
                    $('#cdek-loader').show();
                },
                complete: function() {
                    $('#cdek-loader').hide();
                },
                success: function(response) {
                    if (!response) {
                        $('#cdek-courier-error').html(response.message).show();
                    } else {
                        $('#call-courier-form').hide();
                        $('#cdek-order-courier').hide();
                        $('#cdek-courier-info').text(response.message).show();
                        $('#cdek-courier-result-block').show();
                    }
                },
                error: function(error) {
                    console.log({ error: error });
                },
            });
        });

        $('#cdek-order-courier').click(function(event) {
            $('#call-courier-form').toggle();
        });

        $('#cdek-courier-delete').click(function(event) {
            $.ajax({
                method: 'GET',
                url: window.cdek_rest_order_api_path.call_courier_delete,
                data: {
                    order_id: $('input[name=package_order_id]').val(),
                },
                beforeSend: function() {
                    $('#cdek-loader').show();
                },
                complete: function() {
                    $('#cdek-loader').hide();
                },
                success: function(response) {
                    if (response) {
                        $('#cdek-courier-result-block').hide();
                        $('#cdek-order-courier').show();
                    }
                },
                error: function(error) {
                    console.log({ error: error });
                },
            });
        });

    })
})(jQuery);
