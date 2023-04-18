(function($) {
    $(document).ready(function() {
        let map = null;
        let cluster = null;
        let cityCodePvzReceived = null;

        if ($('#woocommerce_official_cdek_seller_name').length === 0) {
            $('#woocommerce_official_cdek_client_secret').after('<p>Ошибка авторизации. Введите корректные идентификатор клиента и секретный ключ.</p>');
        } else {
            modeToggle();
            cityAutocomplete();
            $('#woocommerce_official_cdek_city').after('<div id="region-list"></div>');
            regionListProcess();
            chooseLayerMap();
            function chooseLayerMap() {
                let tiles = $('#woocommerce_official_cdek_map_layer').val();
                if (tiles === '1') {
                    $('#woocommerce_official_cdek_yandex_map_api_key').get(0).type = 'text';
                }

                $('#woocommerce_official_cdek_map_layer').change(function (event) {
                    if ($(event.currentTarget).val() === '1') {
                        $('#woocommerce_official_cdek_yandex_map_api_key').get(0).type = 'text';
                    } else {
                        $('#woocommerce_official_cdek_yandex_map_api_key').get(0).type = 'hidden';
                    }
                })
            }


            function cityAutocomplete() {
                $('#woocommerce_official_cdek_city').on('input', function (event) {
                    $('#no_delivery_points_in_city').remove();
                    $('#region-list').empty();
                    $('#woocommerce_official_cdek_pvz_address').val('')
                    $('#woocommerce_official_cdek_pvz_code').val('')
                    if (event.target.value.length > 2) {
                        $.ajax({
                            method: "GET",
                            url: "/wp-json/cdek/v1/get-region",
                            data: {
                                city: event.target.value
                            },
                            success: function (response) {
                                let regions = JSON.parse(response);
                                if (regions.length > 0) {
                                    for (let i = 0; i < regions.length; i++) {
                                        if (regions[i].region === regions[i].city) {
                                            $("#region-list").append(`<div class="reg-elem" data-code="${regions[i].code}" data-lat="${regions[i].latitude}" data-lon="${regions[i].longitude}">${regions[i].country}, ${regions[i].city}</div>`);
                                        } else {
                                            $("#region-list").append(`<div class="reg-elem" data-code="${regions[i].code}" data-lat="${regions[i].latitude}" data-lon="${regions[i].longitude}">${regions[i].country}, ${regions[i].region}, ${regions[i].city}</div>`);
                                        }
                                    }
                                }
                            },
                            error: function (error) {
                                console.log({error: error});
                            }
                        });
                    }
                })
            }

            function regionListProcess() {
                $("#region-list").on('click', function (event) {
                    $('#woocommerce_official_cdek_city').val(event.target.innerText)
                    $("#woocommerce_official_cdek_city_code_value").val(event.target.getAttribute("data-code"));
                    $('#region-list').empty();
                    getPvz();
                })
            }

            function initMap() {
                $('#woocommerce_official_cdek_map').after('<div id="map-container"><div id="cdek-map"></div></div>')

                let mapContainer = $('#cdek-map');
                if (mapContainer.length) {
                    map = L.map('cdek-map', {
                        center: [55.76, 37.61],
                        zoom: 9
                    });

                    map._layersMaxZoom = 19;

                    cluster = L.markerClusterGroup();
                    map.addLayer(cluster);
                    if ($('#woocommerce_official_cdek_map_layer').val() === '1' && $('#woocommerce_official_cdek_yandex_map_api_key').val() !== '') {
                        L.yandex().addTo(map);
                    } else {

                        if ($('#woocommerce_official_cdek_map_layer').val() === '1') {
                            $('#message').after('<div class="error"><p>Не удалось получить доступ к YandexMap по указанному ApiKey</p></div>')
                        }

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '© OpenStreetMap'
                        }).addTo(map);
                    }
                    getPvz();
                }
            }

            function modeToggle() {
                initMap();
                $('#woocommerce_official_cdek_mode').change(function (event) {
                    if ($(event.target).val() === '1') {
                        $($('label[for=woocommerce_official_cdek_address]')[0]).text('Карта');
                        $('#map-container').show();
                        $('#woocommerce_official_cdek_address').hide();
                        $('#woocommerce_official_cdek_pvz_address').show();
                        getPvz();
                    } else {
                        $($('label[for=woocommerce_official_cdek_address]')[0]).text('Адрес');
                        $('#map-container').hide();
                        $('#woocommerce_official_cdek_pvz_address').hide();
                        $('#woocommerce_official_cdek_address').show();
                    }
                })
            }

            function getPvz() {
                let cityCode = $('#woocommerce_official_cdek_city_code_value').val();
                if (cityCode !== cityCodePvzReceived) {
                    $.ajax({
                        method: "GET",
                        url: "/wp-json/cdek/v1/get-pvz",
                        data: {
                            city_code: cityCode,
                            admin: 1
                        },
                        success: function (response) {
                            console.log({response: response})
                            let resp = JSON.parse(response);
                            if (resp.success) {
                                cityCodePvzReceived = cityCode;
                                setMarker(resp.pvz);
                            } else {
                                $('#no_delivery_points_in_city').remove();
                                $('#woocommerce_official_cdek_city').before(`<p id="no_delivery_points_in_city" className="description">${resp.message}</p>`);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log('xhr: ' + xhr);
                            console.log('Status: ' + status);
                            console.log('Error: ' + error);
                        }
                    });
                }
            }

            function setMarker(pvz) {
                map.removeLayer(cluster);
                cluster = L.markerClusterGroup();
                map.addLayer(cluster);
                for (let i = 0; i < pvz.length; i++) {
                    let marker = L.circleMarker([pvz[i].latitude, pvz[i].longitude]);
                    $(marker).click(function (event) {
                        selectMarker(pvz[i])
                    });
                    cluster.addLayer(marker);
                }
                map.fitBounds(cluster.getBounds())
            }

            function selectMarker(pvz) {
                $('#woocommerce_official_cdek_pvz_address').val(pvz.address)
                $('#woocommerce_official_cdek_pvz_code').val(pvz.code)
            }

            $('#woocommerce_official_cdek_tariff_list').change(function (event) {
                let tariffCode62 = $(event.currentTarget).find('option[value=62]');
                if ($(tariffCode62).is(':selected')) {
                    $('#woocommerce_official_cdek_service_list').find('option[value=DELIV_RECEIVER]').css('display', '');
                } else {
                    $('#woocommerce_official_cdek_service_list').find('option[value=DELIV_RECEIVER]').removeAttr("selected");
                    $('#woocommerce_official_cdek_service_list').find('option[value=DELIV_RECEIVER]').css('display', 'none');
                }
                hideIfServicesEmpty()
            })

            $('#woocommerce_official_cdek_service_list').after('<div id="service-message">Услуг для выбранных тарифов не найдено</div>');
            $('#woocommerce_official_cdek_service_list').css('display', 'none');
            checkServicesAvailable();
            function checkServicesAvailable() {
                let tariffCode62IsSelected = false;
                $('#woocommerce_official_cdek_tariff_list').find('option:selected').each(function (key, option) {
                    if ($(option).val() === '62') {
                        tariffCode62IsSelected = true;
                    }
                })

                if (!tariffCode62IsSelected) {
                    $('#woocommerce_official_cdek_service_list').find('option[value=DELIV_RECEIVER]').removeAttr("selected");
                    $('#woocommerce_official_cdek_service_list').find('option[value=DELIV_RECEIVER]').css('display', 'none');
                }

                hideIfServicesEmpty()
            }

            function hideIfServicesEmpty() {
                let isEmpty = true;
                $('#woocommerce_official_cdek_service_list').find('option').each(function (key, option) {
                    if ($(option).css('display') === 'block') {
                        isEmpty = false;
                        return false;
                    }
                })

                if (isEmpty) {
                    $('#woocommerce_official_cdek_service_list').css('display', 'none');
                    $('#service-message').css('display', '');
                } else {
                    $('#woocommerce_official_cdek_service_list').css('display', '');
                    $('#service-message').css('display', 'none');
                }
            }
        }

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

        $('#woocommerce_official_cdek_extra_day').attr('min', 0);
        $('#woocommerce_official_cdek_extra_cost').attr('min', 0);
        $('#woocommerce_official_cdek_percentprice').attr('min', 100);
        $('#woocommerce_official_cdek_percentcod').attr('min', 100);
        $('#woocommerce_official_cdek_product_length_default').attr('min', 1);
        $('#woocommerce_official_cdek_product_width_default').attr('min', 1);
        $('#woocommerce_official_cdek_product_height_default').attr('min', 1);
        $('#woocommerce_official_cdek_stepprice').attr('min', 1);
        $('#woocommerce_official_cdek_stepcodprice').attr('min', 1);
    })
})(jQuery);
