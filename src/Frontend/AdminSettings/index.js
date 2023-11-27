import $ from 'jquery';
import cdekWidget from '@cdek-it/widget';
import './styles/main.scss';
$.getJSON(window.cdek_admin_settings.api.check_auth)
 .done(() => $('.token-wrong').remove())
 .fail((jqxhr) => {
     console.error(jqxhr);
     $('p:contains(\'Custom Shipping Method for Cdek\')').after('<div class="cdek-error token-wrong">[CDEKDelivery] Ошибка при получении токена. Убедитесь, что ключи интеграции верны</div>');
 });

const showMap = () => {
    $('input#woocommerce_official_cdek_map')
        .after('<div id="cdek-map-results"></div><div id="cdek-map"></div>');

    $('#cdek-map-results')
        .append(`<div id="selected_office"><span class="icon office"></span><div><h5>Выбранный ПВЗ</h5><div class="result"></div></div></div>`)
        .append(`<div id="selected_address"><div><h5>Выбранный адрес</h5><div class="result">Не выбран</div></div><span class="icon door"></span></div>`);

    const officeInput = $('input[name=woocommerce_official_cdek_pvz_code]');
    const doorInput = $('input[name=woocommerce_official_cdek_address]');

    let openedCity = '';
    let openedOffice = '';
    let openedDoor = '';

    const updateOfficeCode = () => {
        const officeCode = officeInput.val();
        const selectedOfficeDiv = $('#selected_office');

        if (officeCode) {
            try {
                const parsedOffice = JSON.parse(officeCode);
                selectedOfficeDiv
                    .addClass('selected')
                    .find('.result')
                    .html(`${parsedOffice.country}; ${parsedOffice.postal}; ${parsedOffice.city}; ${parsedOffice.address}`);

                openedCity = parsedOffice.city;
                openedOffice = parsedOffice.address;
                return;
            } catch (e) {}
        }

        selectedOfficeDiv
            .removeClass('selected')
            .find('.result')
            .html('Не выбран');

    };

    const updateDoor = () => {
        const address = doorInput.val();
        const selectedAddressDiv = $('#selected_address');

        if (address) {
            try {
                const parsedAddress = JSON.parse(address);

                selectedAddressDiv
                    .addClass('selected')
                    .find('.result')
                    .html(`${parsedAddress.country}; ${parsedAddress.postal}; ${parsedAddress.city}; ${parsedAddress.address}`);

                openedCity = parsedAddress.city;
                openedDoor = parsedAddress.address;
                return;
            } catch (e) {}
        }

        selectedAddressDiv
            .removeClass('selected')
            .find('.result')
            .html('Не выбран');
    };

    updateOfficeCode();
    updateDoor();

    new cdekWidget({
                              apiKey: window.cdek.apiKey,
                              sender: true,
                              debug: true,
                              requirePostcode: true,
                              defaultLocation: openedCity || 'Новосибирск',
                              servicePath: window.cdek_admin_settings.api.offices,
                              hideFilters: {
                                  type: true, have_cash: true, have_cashless: true, is_dressing_room: true,
                              },
                              selected: {
                                  office: openedOffice || null, door: openedDoor || null,
                              },
                              onChoose(type, tariff, target) {
                                  if (type === 'office') {
                                      officeInput.val(JSON.stringify({
                                                                         country: target.country_code,
                                                                         postal: target.postal_code,
                                                                         city: target.city,
                                                                         address: target.code,
                                                                     }));
                                      updateOfficeCode();
                                  } else if (type === 'door') {
                                      doorInput.val(JSON.stringify({
                                                                       country: target.country_code,
                                                                       postal: target.postal_code,
                                                                       city: target.city,
                                                                       address: target.formatted,
                                                                   }));
                                      updateDoor();
                                  }
                              },
                          });
};

if ($('input#woocommerce_official_cdek_map').length) {
    showMap();
}