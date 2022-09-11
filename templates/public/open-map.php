<?php
/**
 * @var $layerMap
 */
?>
<div class="open-pvz-btn">Выбрать ПВЗ</div>
<div>
    <input name="pvz_info" id="pvz-info" type="text">
    <input name="pvz_code" id="pvz-code" type="hidden">
    <input name="city_code" id="city-code" type="hidden">
</div>
<style>
    .open-pvz-btn {
        color: #7f54b3;
        cursor: pointer;
        font-size: small;
    }

    #pvz-info {
        display: none;
    }
</style>

<script>
    (function ($) {
        $(document).ready(function () {

            let map = null;
            let cluster = null;

            $('.open-pvz-btn').click(function () {
                $('#map-frame').css('display', 'flex');

                let container = L.DomUtil.get('map');

                if(container != null) {
                    container._leaflet_id = null;
                }

                map = L.map('map', {
                    center: [55.76, 37.61],
                    zoom: 9
                });

                map._layersMaxZoom = 19;

                cluster = L.markerClusterGroup();
                map.addLayer(cluster);

                if (<?php echo $layerMap?> === 1) {
                    L.yandex().addTo(map);
                } else {
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(map);
                }

                displayPvzOnMap();
            })

            function displayPvzOnMap() {
                getCityCodeByCityNameAndZipCode()
            }

            function getCityCodeByCityNameAndZipCode() {
                let cityName = $('#billing_city').val();
                let zipCode = $('#billing_postcode').val();
                $.ajax({
                    method: "GET",
                    url: "/wp-json/cdek/v1/get-city-code",
                    data: {
                        city_name: cityName,
                        zip_code: zipCode,
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
                        setMarker(JSON.parse(response));
                    },
                    error: function (error) {
                        console.log({error: error});
                    }
                });
            }

            function setMarker(pvz) {
                map.removeLayer(cluster);
                cluster = L.markerClusterGroup();
                map.addLayer(cluster);
                for (let i = 0; i < pvz.length; i++) {
                    let marker = null;
                    if (pvz[i].type === 'POSTAMAT') {
                        marker = L.circleMarker([pvz[i].latitude, pvz[i].longitude], {color: '#ffad33'});
                    } else {
                        marker = L.circleMarker([pvz[i].latitude, pvz[i].longitude]);
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
                $('#pvz-info').css('display', 'block');
                $('#map-frame').css('display', 'none');
            }
        })
    })(jQuery);

</script>
<?php ?>
