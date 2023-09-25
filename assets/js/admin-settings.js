'use strict';

jQuery(($) => {
    const showMap = () => {
        $('input#woocommerce_official_cdek_map')
          .after('<div id="cdek-map-results"></div><div id="cdek-map"></div>');

        $('#cdek-map-results')
          .append(
            `<div id="selected_office"><img src="${window.cdek_admin_settings.icons.office}" alt="Office icon"><div><h5>Выбранный ПВЗ</h5><div class="result"></div></div></div>`)
          .append(
            `<div id="selected_address"><div><h5>Выбранный адрес</h5><div class="result">Не выбран</div></div><img src="${window.cdek_admin_settings.icons.door}" alt="Door icon"></div>`);

        const officeInput = $('input[name=woocommerce_official_cdek_pvz_code]');
        const doorInput = $('input[name=woocommerce_official_cdek_address]');
        const updateOfficeCode = () => {
            const officeCode = officeInput.val();

            if (officeCode) {
                $('#selected_office')
                  .addClass('selected')
                  .find('.result')
                  .html(officeCode);
            } else {
                $('#selected_office')
                  .removeClass('selected')
                  .find('.result')
                  .html('Не выбран');
            }
        };

        const updateDoor = () => {
            const address = doorInput.val();

            if (address) {
                $('#selected_address')
                  .addClass('selected')
                  .find('.result')
                  .html(address);
            } else {
                $('#selected_address')
                  .removeClass('selected')
                  .find('.result')
                  .html('Не выбран');
            }
        };

        updateOfficeCode();
        updateDoor();

        new window.CDEKWidget({
            apiKey: window.cdek.apiKey,
            sender: true,
            defaultLocation: 'Новосибирск',
            servicePath: window.cdek_admin_settings.api.offices,
            hideFilters: {
                type: true,
                have_cash: true,
                have_cashless: true,
                is_dressing_room: true,
            },
            selected: {
                office: officeInput.val() ? officeInput.val().split(' ')[1] : null,
                door: doorInput.val() || null,
            },
            onChoose(type, tariff, target) {
                if (type === 'office') {
                    officeInput.val(`${target.city} ${target.code}`);
                    updateOfficeCode();
                } else if (type === 'door') {
                    doorInput.val(target.formatted);
                    updateDoor();
                }
                console.log(type, target);
            },
        });
    };

    if ($('input#woocommerce_official_cdek_map').length) {
        showMap();
    }
});
