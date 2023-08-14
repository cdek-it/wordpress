'use strict';
(function($, L) {
    function debounce(callee, timeoutMs) {
        return function action(...args) {
            let previousCall = this.lastCall;
            this.lastCall = Date.now();
            if (previousCall && this.lastCall - previousCall <= timeoutMs) {
                clearTimeout(this.lastCallTimer);
            }
            this.lastCallTimer = setTimeout(() => callee(...args), timeoutMs);
        };
    }

    $(document).ready(function() {
        let map = null;
        let cluster = null;

        function uninstallMap(errorMessage = null) {
            console.debug('[CDEK-MAP] Closing map');

            $('#map-pvz-list-search').val('');
            $('#map-frame').css('display', 'none');

            if (map !== null) {
                map.off();
                map.remove();
                map = null;
            }

            if (typeof errorMessage === 'string') {
                console.debug('[CDEK-MAP] Rendering error message');

                const triggerBtn = $('.open-pvz-btn');
                triggerBtn.prev().text(errorMessage);
                triggerBtn.remove();

                $('#pvz-info').hide();
                $('#pvz-code').val('');
            }
        }

        const cityInput = $('#billing_city');

        const loadPoints = () => {
            const triggerBtn = $('.open-pvz-btn');
            const points = triggerBtn.data('points');
            console.debug('[CDEK-MAP] Got points from backend:', points);

            if (typeof points !== 'object') {
                console.error('[CDEK_MAP] backend points not object');
                uninstallMap(
                  'CDEK не смог загрузить список доступных ПВЗ, выберите другой метод доставки');

                return [];
            } else if (!points.length) {
                console.warn('[CDEK_MAP] backend points are empty');
                uninstallMap(
                  'По данному направлению нет доступных пунктов выдачи CDEK, выберите другой метод доставки');

                return [];
            }

            return points;
        };

        $(document.body).on('updated_checkout', () => {
            if (!$('.open-pvz-btn').length) {
                console.debug(
                  '[CDEK-MAP] Checkout updated! No map button, doing nothing');
                return;
            }

            console.debug('[CDEK-MAP] Checkout updated! Applying new points');
            renderPointsOnMap(loadPoints());
        });

        if (cityInput.val()) {
            console.debug(
              '[CDEK-MAP] City has value, initiating checkout update');
            const city = cityInput.val();
            cityInput.val('');
            $(document.body).trigger('update_checkout');

            setTimeout(() => {
                cityInput.val(city);
                $(document.body).trigger('update_checkout');
            }, 1000);
        }

        $('body')
          .on('change', '#billing_city, #billing_state', () => {
              if (cityInput.val() !== '') {
                  console.debug(
                    '[CDEK-MAP] City or state changed, initiating checkout update');
                  $(document.body).trigger('update_checkout');
              }
          })
          .on('click', '.open-pvz-btn', null, () => {
              console.debug('[CDEK-MAP] Start map render');

              const triggerBtn = $('.open-pvz-btn');
              $('#map-frame').css('display', 'flex');

              if (!map) {
                  map = L.map('cdek-map', {
                      center: [55.76, 37.61], zoom: 9,
                  });
              }

              map._layersMaxZoom = 19;

              if (triggerBtn.data('layer-map') === 1) {
                  L.yandex().addTo(map);
                  console.info('[CDEK-MAP] Using Yandex');
              } else {
                  L.tileLayer(
                    'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
                    {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap contributors © CARTO',
                    }).addTo(map);
                  console.info('[CDEK-MAP] Using Basemap');
              }

              L.Control.PvzList = L.Control.extend({
                  options: { position: 'topright' }, onAdd: () => {
                      const controlDiv = L.DomUtil.create('div',
                        'leaflet-draw-toolbar leaflet-bar');

                      const controlUI = L.DomUtil.create('a',
                        'leaflet-close-map', controlDiv);
                      controlUI.title = 'Закрыть карту';
                      controlUI.href = '#';

                      controlUI.ondblclick = (e) => e.stopPropagation();
                      controlUI.onclick = uninstallMap;

                      const sidebarUI = L.DomUtil.create('a',
                        'leaflet-expand-list', controlDiv);
                      sidebarUI.title = 'Открыть список';
                      sidebarUI.href = '#';

                      sidebarUI.ondblclick = (e) => e.stopPropagation();
                      sidebarUI.onclick = () => {
                          console.debug('[CDEK-MAP] Toggle offices list');
                          $('#main-map-container').toggleClass('mobile-toggle');
                      };

                      return controlDiv;
                  },
              });

              map.addControl(new L.Control.PvzList());

              renderPointsOnMap(loadPoints());

              $('#background').on('click', uninstallMap);

              $('#map-pvz-list-search-clear')
                .on('click',
                  () => $('#map-pvz-list-search').val('').trigger('input'));

              $('#map-pvz-list-search').on('input', debounce((el) => {
                  const searchPredicate = el.target.value.toLowerCase();
                  console.info('[CDEK-MAP] Starting search with value',
                    searchPredicate);

                  const result = loadPoints()
                    .filter((point) => point.name.toLowerCase()
                        .indexOf(searchPredicate) !== -1 ||
                      point.address.toLowerCase().indexOf(searchPredicate) !==
                      -1 ||
                      point.code.toLowerCase().indexOf(searchPredicate) !== -1);

                  console.debug('[CDEK-MAP] Search found that points:', result);

                  renderPointsOnMap(result, false);
              }, 500));
          });

        function renderPointsOnMap(points, initialRender = true) {
            if (!map) {
                console.debug('[CDEK-MAP] No map for points to render, sorry');
                return;
            }

            if (cluster) {
                console.debug('[CDEK-MAP] Clearing previous points');
                map.removeLayer(cluster);

                $('#map-pvz-item-list').empty();
            }
            cluster = L.markerClusterGroup();
            map.addLayer(cluster);

            const postamatIcon = L.icon({
                iconUrl: window.cdek_map.icons.postamat,
                iconSize: [28, 36],
                iconAnchor: [14, 36],
                popupAnchor: [0, -40],
            });

            const officeIcon = L.icon({
                iconUrl: window.cdek_map.icons.office,
                iconSize: [28, 36],
                iconAnchor: [14, 36],
                popupAnchor: [0, -40],
            });

            const selectedPoint = initialRender ? $('#pvz-code').val() : null;
            if (initialRender) {
                console.debug(
                  `[CDEK-MAP] Using previous selected point: ${selectedPoint}`);
            }

            Promise.all(points.map((point) => {
                const domEl = $('<li></li>');
                const marker = L.marker([point.latitude, point.longitude], {
                    icon: point.type === 'PVZ' ? officeIcon : postamatIcon,
                }).bindPopup(`[${point.code}] ${point.name}`);

                const onClick = (type) => {
                    console.debug(`[CDEK-MAP] Selected point from ${type}`,
                      point);

                    const showPopUp = () => {
                        marker.openPopup();
                        map.off('zoomend', showPopUp);
                    };

                    map.on('zoomend', showPopUp);

                    map.closePopup();
                    map.flyTo([point.latitude, point.longitude], 16);

                    if (type !== 'list') {
                        domEl[0].scrollIntoView({
                            behavior: 'smooth',
                        });
                    }

                    $('#map-pvz-item-list .item-list-elem.selected')
                      .removeClass('selected');
                    domEl.addClass('selected');

                    $('#pvz-info').text(`[${point.code}] ${point.name}`).show();
                    $('#pvz-code').val(point.code);
                    $('#billing_address_1').val(point.address);
                    $('#shipping_address_1').val(point.address);

                    $.ajax({
                        method: 'GET',
                        url: window.cdek_map.tmp_pvz_code,
                        data: {
                            pvz_code: point.code,
                        },
                    });
                };

                domEl.html(
                  `<h6>[${point.code}] ${point.name}</h6><i>${point.address}</i><br>${point.work_time}`);
                domEl.addClass('item-list-elem');
                domEl.on('click', () => {
                    onClick('list');
                });

                marker.on('click', () => {
                    onClick('map');
                });

                $('#map-pvz-item-list').append(domEl);
                marker.addTo(cluster);

                if (initialRender && selectedPoint === point.code) {
                    onClick('external');
                }

            })).then(() => {
                if (!selectedPoint) {
                    const newBounds = cluster.getBounds();
                    if (newBounds.isValid()) {
                        console.debug('[CDEK-MAP] Render done! Resizing map');
                        map.fitBounds(cluster.getBounds());
                    } else {
                        console.debug(
                          '[CDEK-MAP] Render done! New bounds for resize are empty');
                    }
                } else {
                    console.debug(
                      '[CDEK-MAP] Render done! Map resize not required');
                }
            }).catch((e) => {
                //IDK when it can happen, but...
                console.error('[CDEK-MAP] Render failed!', e);
                uninstallMap(
                  'Произошла ошибка при загрузке карты CDEK, выберите другой метод доставки');
            });
        }
    });
})(jQuery, L);
