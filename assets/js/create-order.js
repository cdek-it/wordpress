(function ($) {
    $(document).ready(function () {
        $('#create-order-btn').click(function () {
            $.ajax({
                method: "GET",
                url: "/wp-json/cdek/v1/create-order",
                data: {
                    package_order_id: $('input[name=package_order_id]').val(),
                    package_length: $('input[name=package_length]').val(),
                    package_width: $('input[name=package_width]').val(),
                    package_height: $('input[name=package_height]').val()
                },
                success: function (code) {
                    $('#cdek-create-order-form').hide();
                    $('#cdek-order-number').html(code);
                    $('#cdek-info-order').show();
                },
                error: function (error) {
                    console.log({error: error});
                }
            });
        })
        $('#delete-order-btn').click(function () {
            $.ajax({
                method: "GET",
                url: "/wp-json/cdek/v1/delete-order",
                data: {
                    number: $('#cdek-order-number').html(),
                    order_id: $('input[name=package_order_id]').val()
                },
                success: function (response) {
                    $('#cdek-create-order-form').show();
                    $('#cdek-info-order').hide();
                },
                error: function (error) {
                    console.log({error: error});
                }
            });
        })
    })
})(jQuery);