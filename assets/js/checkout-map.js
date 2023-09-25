'use strict';

jQuery(($) => {
    const cityInput = $('#billing_city');
    let widget = null;

    if (cityInput.val() !== '') {
        console.debug('[CDEK-MAP] City has value, initiating checkout update');
        $(document.body).trigger('update_checkout');
    }

    const closeMap = (errorMessage = null) => {
        console.debug('[CDEK-MAP] Removing selected office info');

        $('.cdek-office-info').remove();
        $('.cdek-office-code').val('');

        if (typeof errorMessage === 'string') {
            console.debug('[CDEK-MAP] Rendering error message');

            const triggerBtn = $('.open-pvz-btn');
            triggerBtn.prev().text(errorMessage);
            triggerBtn.remove();
        }
    };

    $(document.body)
      .on('change', '#billing_city, #billing_state', () => {
          if (cityInput.val() !== '') {
              console.debug(
                '[CDEK-MAP] City or state changed, initiating checkout update');
              $(document.body).trigger('update_checkout');
          }
      })
      .on('updated_checkout', () => {
          if (widget !== null) {
              console.debug('[CDEK-MAP] Clearing widget selection');

              widget.clearSelection();
          }
      })
      .on('click', '.open-pvz-btn', null, (e) => {
          closeMap();

          const el = $(e.target);

          const points = el.data('points');
          console.debug('[CDEK-MAP] Got points from backend:', points);

          if (typeof points !== 'object') {
              console.error('[CDEK_MAP] backend points not object');
              closeMap(
                'CDEK не смог загрузить список доступных ПВЗ, выберите другой метод доставки');

              return;
          } else if (!points.length) {
              console.warn('[CDEK_MAP] backend points are empty');
              closeMap(
                'По данному направлению нет доступных пунктов выдачи CDEK, выберите другой метод доставки');

              return;
          }

          if (widget === null) {
              widget = new window.CDEKWidget({
                  apiKey: window.cdek.apiKey,
                  popup: true,
                  debug: true,
                  defaultLocation: cityInput.val(),
                  officesRaw: points,
                  hideDeliveryOptions: {
                      door: true,
                  },
                  onChoose(_type, _tariff, address) {
                      $('.cdek-office-code').val(address.code);
                      el.html('Повторно выбрать ПВЗ');
                      el.before(
                        `<div class="cdek-office-info">${address.name} - [${address.code}]</div>`);

                      $.ajax({
                          method: 'GET',
                          url: window.cdek_map.tmp_pvz_code,
                          data: {
                              pvz_code: address.code,
                          },
                      });
                  },
              });
          } else {
              widget.updateOffices(points);
          }

          widget.open();
      });
});
