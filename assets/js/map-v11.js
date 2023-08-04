(function($, L) {
    $(document).ready(function() {
        let map = null;
        let cluster = null;
        function triggerWooCommerceUpdate() {
            let city = $('#billing_city').val();
            $('#billing_city').val('');
            $(document.body).trigger('update_checkout');
            setTimeout(updateCheckout, 1000, city);
        }

        function updateCheckout(city) {
            $('#billing_city').val(city);
            $(document.body).trigger('update_checkout');
        }

        if ($('#billing_city').val()) {
            triggerWooCommerceUpdate();
        }

        $('body').on('change', '#billing_city, #billing_state', function() {
            if ($('#billing_city').val() !== '') {
                $(document.body).trigger('update_checkout');
            }
        });

        $('body').on('click', '.open-pvz-btn', null, function() {
            $('#map-frame').css('display', 'flex');
            if (!map) {
                map = L.map('cdek-map', {
                    center: [55.76, 37.61],
                    zoom: 9,
                });
            }
            map._layersMaxZoom = 19;
            cluster = L.markerClusterGroup();
            map.addLayer(cluster);
            let layerMap = $('.open-pvz-btn').data('layer-map');
            if (layerMap === 1) {
                L.yandex().addTo(map);
            } else {
                L.tileLayer(
                  'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                      maxZoom: 19,
                      attribution: '© OpenStreetMap',
                  }).addTo(map);
            }

            L.Control.PvzList = L.Control.extend(
              {
                  options:
                    {
                        position: 'topright',
                    },
                  onAdd: function(map) {
                      var controlDiv = L.DomUtil.create('div',
                        'leaflet-draw-toolbar leaflet-bar');
                      L.DomEvent
                        .addListener(controlDiv, 'click',
                          L.DomEvent.stopPropagation)
                        .addListener(controlDiv, 'click',
                          L.DomEvent.preventDefault)
                        .addListener(controlDiv, 'click', function() {
                            if ($('#map-pvz-list').is(':visible')) {
                                $('#map-pvz-list').hide();
                            } else {
                                $('#map-pvz-list').show();
                            }
                        });

                      var controlUI = L.DomUtil.create('a',
                        'leaflet-draw-pvz-list', controlDiv);
                      controlUI.title = 'Pvz list';
                      controlUI.href = '#';

                      controlUI.ondblclick = (e) => {
                          e.stopPropagation();
                      };
                      controlUI.onclick = this.options.onclickMethod;

                      return controlDiv;
                  },
              });
            let pvzListControl = new L.Control.PvzList();
            map.addControl(pvzListControl);

            $('#map-loader').show();

            displayPvzOnMap();

            $('#background').click(function() {
                $('#map-frame').css('display', 'none');
                $('#map-pvz-list').hide();
                $('#map-pvz-item-list').empty();
                $('#map-pvz-list-search').val('');
                uninstallMap();
            });

            $('#map-pvz-list-search-clear').click(function() {
                $('#map-pvz-list-search').val('');
                $('.item-list-elem').each(function() {
                    $(this).show();
                });
            });
        });

        function uninstallMap() {
            if (map !== null) {
                map.off();
                map.remove();
                map = null;
            }
        }

        function getCity() {
            if ($('#billing_city').length &&
              !$('#ship-to-different-address-checkbox').prop('checked')) {
                return jQuery('#billing_city').val();
            }

            if ($('#shipping_city').length) {
                return $('#shipping_city').val();
            }

            return false;
        }

        function getState() {
            if ($('#billing_state').length &&
              !$('#ship-to-different-address-checkbox').prop('checked')) {
                return jQuery('#billing_state').val();
            }

            if ($('#shipping_state').length) {
                return $('#shipping_state').val();
            }

            return false;
        }

        function displayPvzOnMap() {
            getCityCodeByCityNameAndZipCode();
        }

        function getCityCodeByCityNameAndZipCode() {
            let cityName = getCity();
            let stateName = getState();
            $.ajax({
                method: 'GET',
                url: window.cdek_rest_map_api_path.city_code,
                data: {
                    city_name: cityName,
                    state_name: stateName,
                },
                success: function(cityCode) {
                    if (cityCode !== -1) {
                        $('#city-code').val(cityCode);
                        getPvz(cityCode);
                    }
                },
                error: function(error) {
                    console.log({ error: error });
                },
            });
        }

        function getPvz(cityCode) {
            let weight = $('.open-pvz-btn').data('weight');

            if (weight === '') {
                weight = 1;
            }

            $.ajax({
                method: 'GET',
                url: window.cdek_rest_map_api_path.get_pvz,
                data: {
                    city_code: cityCode,
                    weight: weight,
                },
                success: function(response) {
                    $('#map-loader').hide();
                    let resp = JSON.parse(response);
                    if (resp.success) {
                        setMarker(resp.pvz);
                    } else {
                        console.log('Не найдено');
                    }
                },
                error: function(error) {
                    console.log({ error: error });
                },
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
            let hasPostamat = false;
            $('#map-pvz-item-list').empty();
            for (let i = 0; i < pvz.length; i++) {
                let marker = null;
                if (pvz[i].type === 'POSTAMAT') {
                    if (postamat === 1) {
                        hasPostamat = true;
                        marker = L.circleMarker(
                          [pvz[i].latitude, pvz[i].longitude],
                          { color: '#ffad33' });
                        $('#map-pvz-item-list')
                          .append(
                            `<li class="item-list-elem" data-lat="${pvz[i].latitude}" data-lon="${pvz[i].longitude}">${pvz[i].address}</li>`);
                    }
                } else {
                    if (postamat !== 1) {
                        marker = L.circleMarker(
                          [pvz[i].latitude, pvz[i].longitude]);
                        $('#map-pvz-item-list')
                          .append(
                            `<li class="item-list-elem" data-lat="${pvz[i].latitude}" data-lon="${pvz[i].longitude}">${pvz[i].address}</li>`);
                    }
                }

                if (marker === null) {
                    continue;
                }

                $(marker).click(function(event) {
                    selectMarker(pvz[i]);
                });
                cluster.addLayer(marker);
            }

            if (postamat === 1 && !hasPostamat) {
                $('#map-frame').css('display', 'none');
                uninstallMap();
                let label = $('.open-pvz-btn').prev()[0];
                $(label)
                  .text(
                    'По данному направлению тарифы "до постамата" временно не работают');
                $('.open-pvz-btn').hide();
                $('#pvz-info').val('');
                $('#pvz-code').val('');
            } else {
                map.fitBounds(cluster.getBounds());
            }

            const $itemList = $('.item-list-elem');
            $('#map-pvz-list-search').keyup(function(event) {
                let filterValue = event.target.value.toLowerCase();
                $itemList.each(function() {
                    const itemText = $(this).text().toLowerCase();
                    if (itemText.indexOf(filterValue) !== -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            $itemList.click(function(event) {
                map.setView(new L.LatLng($(event.target).data('lat'),
                  $(event.target).data('lon')), 16);
                $('#map-pvz-list').hide();
            });
        }

        function selectMarker(pvz) {
            $('#pvz-info').val(pvz.address);
            $('#billing_address_1').val(pvz.address);
            $('#shipping_address_1').val(pvz.address);
            $('#pvz-code').val(pvz.code);
            let cityCode = $('#city-code').val();

            $.ajax({
                method: 'GET',
                url: window.cdek_rest_map_api_path.tmp_pvz_code,
                data: {
                    pvz_code: pvz.code,
                    pvz_info: pvz.address,
                    city_code: cityCode,
                },
            });

            $('#pvz-info').css('display', 'block');
            $('#map-frame').css('display', 'none');
            uninstallMap();
        }

    });
})(jQuery, L);
