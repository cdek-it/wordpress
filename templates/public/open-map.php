<?php
/**
 * @var $layerMap
 * @var $postamat
 */
?>
<div class="open-pvz-btn">Выбрать ПВЗ</div>
<div>
    <input name="pvz_info" id="pvz-info" type="text">
    <input name="pvz_code" id="pvz-code" type="hidden">
    <input name="city_code" id="city-code" type="hidden">
</div>
<style>

    #map-container {
        z-index: 20;
    }

    #map-frame {
        display: none;
        width: 100%;
        height: 100%;
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        right: 0;
        align-items: center;
        justify-content: center;

        /*transform: translate(-50%, -50%);*/
    }

    #background {
        background-color: rgba(255,255,255,.7);
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10;
    }

    #map-loader {
        display: none;
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(255,255,255,0.8) url("<?php echo plugins_url( '/img/loader.gif', __FILE__ )?>") center center no-repeat;
        z-index: 1000;
    }

    .open-pvz-btn {
        appearance: none;
        background-color: #2ea44f;
        border: 1px solid rgba(27, 31, 35, .15);
        border-radius: 6px;
        box-shadow: rgb(27 31 35 / 10%) 0 1px 0;
        box-sizing: border-box;
        color: #fff;
        cursor: pointer;
        display: inline-block;
        font-size: 14px;
        font-weight: 600;
        line-height: 20px;
        padding: 6px 16px;
        position: relative;
        text-align: center;
        text-decoration: none;
        user-select: none;
        -webkit-user-select: none;
        touch-action: manipulation;
        vertical-align: middle;
        white-space: nowrap;
        margin-bottom: 10px;
        margin-top: 10px;
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

            $('body').append('<div id="map-frame" style="z-index: 1000;"><div id="map-container"><div id="map"><div id="map-loader"></div></div></div>' +
                '<div id="background"></div></div>');

            $('.open-pvz-btn').click(function () {
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
                if (<?php echo $layerMap?> === 1) {
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
                map.removeLayer(cluster);
                cluster = L.markerClusterGroup();
                map.addLayer(cluster);
                let postamat = <?php echo $postamat;?>;
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

</script>
<?php ?>
