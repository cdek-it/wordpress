(function ($) {
    $(document).ready(function() {
            let map = null;
            let initmap = false;
            let cluster = null;
            $('#edit-container').after('<div id="map-container" style="display: none"><div id="map"></div></div>');

            if ($('#edit-tariff-type').val() === '1') {
                if ($('#edit-pvz-info').val() === '') {
                    toggleMap();
                } else {
                    $('#edit-container').hide();
                    $('#pvz-info-container').show()
                    if (!initmap) {
                        displayMap();
                        $('#map-container').hide();
                    }
                }
            }

            $('#edit-tariff-type').on('change', function (event) {
                toggleMap();
            })

            $('#edit-from').after('<div id="region-list"></div>');
            $("#edit-from").after('<div id="pvz-info" style="display: none"><p id="pvz-info-text"></p></div>');
            $('#edit-from').on("input", function (event) {
                $("#region-list").empty();
                if (event.target.value.length > 2) {
                    $.ajax({
                        method: "GET",
                        url: "/get-region",
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
            $("#region-list").on('click', function (event) {
                $("#edit-from").val(event.target.innerText);
                $("input[name=code_city]").val(event.target.getAttribute("data-code"));
                $("#region-list").empty();
                if ($('#edit-tariff-type').val() === '1') {
                    jQuery.ajax({
                        method: "GET",
                        url: "/get-pvz",
                        data: {
                            city: event.target.getAttribute("data-code")
                        },
                        success: function (response) {
                            let center = L.latLng(event.target.getAttribute("data-lat"), event.target.getAttribute("data-lon"));
                            map.removeLayer(cluster);
                            cluster = L.markerClusterGroup();
                            map.addLayer(cluster);
                            map.setView(center);
                            let pvzs = JSON.parse(response);

                            for (let i = 0; i < pvzs.length; i++) {

                                let marker = null;
                                if (pvzs[i].type === "PVZ") {
                                    marker = L.circleMarker([pvzs[i].location.latitude, pvzs[i].location.longitude]);
                                } else {
                                    marker = L.circleMarker([pvzs[i].location.latitude, pvzs[i].location.longitude], {color: '#e77b12'});
                                }

                                marker.bindPopup(getPopup(pvzs[i])).openPopup();

                                cluster.addLayer(marker);
                            }
                        },
                        error: function (error) {
                            console.log({error: error});
                        }
                    });
                }
            });

            $("div").on("click", '.point-btn', function () {
                $('input[name=pvz]').val($(this).attr("data-code-point"));
                $('#map').hide();
                let addressPvz = `${$(this).attr("data-address")}`
                $('input[name=pvz_info]').val(addressPvz);
                $('#pvz-info-container').show();
            });

            $("#pvz-change-btn").click(function () {
                $('input[name=pvz]').val('');
                $('#map-container').show();
                $('input[name=pvz_info]').val('');
                $('#pvz-info-container').hide();
            });

            function toggleMap() {
                if ($('#edit-tariff-type').val() === '1') {
                    displayMap();
                } else {
                    hideMap();
                }
            }

            function displayMap() {
                initmap = true;
                $('#edit-container').hide();
                $('#map-container').show();
                map = L.map('map', {
                    center: [55.76, 37.61],
                    zoom: 9
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                cluster = L.markerClusterGroup();

                map.addLayer(cluster);

                let city = $('#edit-from').val();
                jQuery.ajax({
                    method: "GET",
                    url: "/get-pvz",
                    success: function (response) {
                        let pvzs = JSON.parse(response);
                        for (let i = 0; i < pvzs.length; i++) {
                            let marker = null;
                            if (pvzs[i].type === "PVZ") {
                                marker = L.circleMarker([pvzs[i].location.latitude, pvzs[i].location.longitude]);
                            } else {
                                marker = L.circleMarker([pvzs[i].location.latitude, pvzs[i].location.longitude], {color: '#e77b12'});
                            }

                            marker.bindPopup(getPopup(pvzs[i])).openPopup();

                            cluster.addLayer(marker);
                        }
                    },
                    error: function (error) {
                        console.log({error: error});
                    }
                });
            }

            function getPopup(pvz) {
                return `<p>${pvz.name}</p><p>${pvz.location.address}</p><input data-code-point="${pvz.code}" data-address="${pvz.location.address}" data-code-city="${pvz.location.city_code}"
                data-name="${pvz.name}" id="point-btn" class="point-btn form-submit" type="button" value="Выбрать">`;
            }

            function hideMap() {
                $('#map').hide();
                $('#pvz-info-container').hide();
                $('#edit-container').show();
            }
    })
})(jQuery);
