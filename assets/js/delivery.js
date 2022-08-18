(function($) {
    $(document).ready(function() {

        let map = null;
        let cluster = null;
        let cityCodePvzReceived = null;
        modeToggle();
        cityAutocomplete();
        $('#woocommerce_cdek_city').after('<div id="region-list"></div>');
        regionListProcess();

        function cityAutocomplete() {
            $('#woocommerce_cdek_city').on('input', function (event) {
                $('#region-list').empty();
                $('#woocommerce_cdek_pvz_info').val('')
                $('#woocommerce_cdek_pvz_code').val('')
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
                $('#woocommerce_cdek_city').val(event.target.innerText)
                $("#woocommerce_cdek_city_code_value").val(event.target.getAttribute("data-code"));
                $('#region-list').empty();
                getPvz();
            })
        }

        function initMap() {
            $('#woocommerce_cdek_map').after('<div id="map-container"><div id="map"></div></div>')
            $('#map-container').show();

            let mapContainer = $('#map');
            if (mapContainer.length) {
                map = L.map('map', {
                    center: [55.76, 37.61],
                    zoom: 9
                });

                map._layersMaxZoom = 19;

                cluster = L.markerClusterGroup();
                map.addLayer(cluster);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);
            }
        }

        function modeToggle() {
            initMap();
            if ($('#woocommerce_cdek_mode').val() === '1') {
                $('#woocommerce_cdek_street').hide();
                $($('label[for=woocommerce_cdek_street]')[0]).text('Карта');
                $('#woocommerce_cdek_pvz_info').show();
                getPvz();
            } else {
                $('#map-container').hide();
                $('#woocommerce_cdek_pvz_info').hide();
            }

            $('#woocommerce_cdek_mode').change(function (event) {
                if ($(event.target).val() === '1') {
                    $($('label[for=woocommerce_cdek_street]')[0]).text('Карта');
                    $('#map-container').show();
                    $('#woocommerce_cdek_street').hide();
                    $('#woocommerce_cdek_pvz_info').show();
                    getPvz();
                } else {
                    $($('label[for=woocommerce_cdek_street]')[0]).text('Адрес');
                    $('#map-container').hide();
                    $('#woocommerce_cdek_pvz_info').hide();
                    $('#woocommerce_cdek_street').show();
                }
            })
        }

        function getPvz() {
            let cityCode = $('#woocommerce_cdek_city_code_value').val();
            if (cityCode !== cityCodePvzReceived) {
                $.ajax({
                    method: "GET",
                    url: "/wp-json/cdek/v1/get-pvz",
                    data: {
                        city_code: cityCode
                    },
                    success: function (response) {
                        cityCodePvzReceived = cityCode;
                        setMarker(JSON.parse(response));
                    },
                    error: function (error) {
                        console.log({error: error});
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
            $('#woocommerce_cdek_pvz_info').val(pvz.address)
            $('#woocommerce_cdek_pvz_code').val(pvz.code)
        }
    })
})(jQuery);
