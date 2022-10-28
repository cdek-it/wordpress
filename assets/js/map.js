(function ($) {
    $(document).ready(function () {
        let map = null;
        let cluster = null;

        $('body').append('<div id="map-frame" style="z-index: 1000;"><div id="map-container"><div id="map"><div id="map-loader"></div></div></div>' +
            '<div id="background"></div></div>');

        $('body').on('click', '.open-pvz-btn', null, function () {
            $('#map-frame').css('display', 'flex');
            if(!map) {
                map = L.map('map', {
                    center: [55.76, 37.61],
                    zoom: 9
                });
            }
            map._layersMaxZoom = 19;
            cluster = L.markerClusterGroup();
            map.addLayer(cluster);
            let layerMap = $('.open-pvz-btn').data('layer-map');
            if (layerMap === 1) {
                L.yandex().addTo(map);
            } else {
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);
            }

            $('#map-loader').show();

            displayPvzOnMap();

            $('#background').click(function () {
                $('#map-frame').css('display', 'none');
                uninstallMap();
            })
        })

        function uninstallMap() {
            if (map !== null) {
                map.off();
                map.remove();
                map = null;
            }
        }

        function getCity(){
            if ($('#billing_city').length && !$('#ship-to-different-address-checkbox').prop('checked')){
                return jQuery('#billing_city').val();
            }

            if ($('#shipping_city').length){
                return $('#shipping_city').val();
            }

            return false;
        }

        function getState(){
            if ($('#billing_state').length && !$('#ship-to-different-address-checkbox').prop('checked')){
                return jQuery('#billing_state').val();
            }

            if ($('#shipping_state').length){
                return $('#shipping_state').val();
            }

            return false;
        }

        function displayPvzOnMap() {
            getCityCodeByCityNameAndZipCode()
        }

        function getCityCodeByCityNameAndZipCode() {
            let cityName = getCity();
            let stateName = getState();
            $.ajax({
                method: "GET",
                url: "/wp-json/cdek/v1/get-city-code",
                data: {
                    city_name: cityName,
                    state_name: stateName
                },
                success: function (cityCode) {
                    $('#city-code').val(cityCode);
                    getPvz(cityCode)
                },
                error: function (error) {
                    console.log({error: error});
                }
            });
        }

        function getPvz(cityCode) {
            $.ajax({
                method: "GET",
                url: "/wp-json/cdek/v1/get-pvz",
                data: {
                    city_code: cityCode
                },
                success: function (response) {
                    $('#map-loader').hide();
                    setMarker(JSON.parse(response));
                },
                error: function (error) {
                    console.log({error: error});
                }
            });
        }

        function setMarker(pvz) {
            if (!map) {
                return false;
            }
            map.removeLayer(cluster);
            cluster = L.markerClusterGroup();
            map.addLayer(cluster);
            let postamat = $('.open-pvz-btn').data('postamat');
            for (let i = 0; i < pvz.length; i++) {
                let marker = null;
                if (pvz[i].type === 'POSTAMAT') {
                    if (postamat === 1) {
                        marker = L.circleMarker([pvz[i].latitude, pvz[i].longitude], {color: '#ffad33'});
                    }
                } else {
                    if (postamat !== 1) {
                        marker = L.circleMarker([pvz[i].latitude, pvz[i].longitude]);
                    }
                }

                if (marker === null) {
                    continue;
                }

                $(marker).click(function (event) {
                    selectMarker(pvz[i])
                });
                cluster.addLayer(marker);
            }
            map.fitBounds(cluster.getBounds())
        }

        function selectMarker(pvz) {
            $('#pvz-info').val(pvz.address);
            $('#pvz-code').val(pvz.code);
            let cityCode = $('#city-code').val();

            $.ajax({
                method: "GET",
                url: "/wp-json/cdek/v1/set-pvz-code-tmp",
                data: {
                    pvz_code: pvz.code,
                    pvz_info: pvz.address,
                    city_code: cityCode,
                }
            });

            $('#pvz-info').css('display', 'block');
            $('#map-frame').css('display', 'none');
            uninstallMap();
        }

    })
})(jQuery);