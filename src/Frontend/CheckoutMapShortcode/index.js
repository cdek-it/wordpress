import $ from 'jquery';
import cdekWidget from '@cdek-it/widget';
import './style/main.scss';
import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';

const billingCityInput = $('#billing_city');
const shippingCityInput = $('#shipping_city');
let widget = null;



if ((billingCityInput.val() || '') !== '' || (shippingCityInput.val() || '') !== '') {
    console.debug('[CDEK-MAP] City has value, initiating checkout update');
    $(document.body).trigger('update_checkout');
}

const closeMap = (el, errorMessage = null) => {
    console.debug('[CDEK-MAP] Removing selected office info');

    $('.cdek-office-info').remove();
    el.html(__('Choose pick-up', 'cdekdelivery'));
    $('.cdek-office-code').val('');

    if (widget !== null) {
        widget.clearSelection();
    }

    if (typeof errorMessage === 'string') {
        console.debug('[CDEK-MAP] Rendering error message');

        const triggerBtn = $('.open-pvz-btn');
        triggerBtn.prev().text(errorMessage);
        triggerBtn.remove();
    }
};

let el;

const onChoose = (_type, _tariff, address) => {
    $('.cdek-office-code').val(address.code);
    el.html(__('Re-select pick-up', 'cdekdelivery'));
    const officeInfo = el.parent().children('.cdek-office-info');
    if (officeInfo.length === 0) {
        el.before(
          `<div class="cdek-office-info">${address.name} - [${address.code}]</div>`);
    } else {
        officeInfo.html(`${address.name} - [${address.code}]`);
    }
    if ($('.cdek-office-code').data('map-auto-close')) {
        widget.close();
    }
};

const debouncedCheckoutUpdate = debounce(() => {
    if (($('#ship-to-different-address-checkbox').is(':checked')
      ? shippingCityInput.val()
      : billingCityInput.val()) === '') {
        return;
    }
    console.debug(
      '[CDEK-MAP] City or postcode changed, initiating checkout update');
    $(document.body).trigger('update_checkout');
}, 500);

$(document.body)
  .on('input',
    '#billing_city, #billing_postcode, #shipping_city, #shipping_postcode',
    debouncedCheckoutUpdate)
  .on('updated_checkout', () => {
      if (widget !== null) {
          console.debug('[CDEK-MAP] Clearing widget selection');

          widget.clearSelection();
      }
  })
  .on('change', '.shipping_method', () => {
      $(document.body).trigger('update_checkout');
  })
  .on('click', '.open-pvz-btn', null, (e) => {
      el = $(e.target);
      closeMap(el);

      const points = el.data('points');
      console.debug('[CDEK-MAP] Got points from backend:', points);

      if (typeof points !== 'object') {
          console.error('[CDEK_MAP] backend points not object');
          closeMap(el,
                   __('CDEK was unable to load the list of available pickup points, please select another delivery method', 'cdekdelivery'));

          return;
      } else if (!points.length) {
          console.warn('[CDEK_MAP] backend points are empty');
          closeMap(el,
                   __('There are no CDEK pick-up points available in this direction, please select another delivery method')
                   );

          return;
      }

      if (widget === null) {
          widget = new cdekWidget({
              apiKey: window.cdek.apiKey,
              popup: true,
              debug: true,
              defaultLocation: el.data('city'),
              officesRaw: points,
              hideDeliveryOptions: {
                  door: true,
              },
              onChoose,
          });
      } else {
          widget.updateOfficesRaw(points);
          widget.updateLocation(el.data('city'));
      }

      widget.open();
  });
