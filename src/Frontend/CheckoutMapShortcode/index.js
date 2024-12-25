import $ from 'jquery';
import cdekWidget from '@cdek-it/widget';
import './style/main.scss';
import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';

const billingCityInput = $('#billing_city');
const shippingCityInput = $('#shipping_city');
const buttonNormalSize = 160;
const smallFontAttribute = 'aria-small';

let needChange;
let isNormalSize;
let widget = null;
let el;

if ((billingCityInput.val() || '') !== '' || (shippingCityInput.val() || '') !==
  '') {
    console.debug('[CDEK-MAP] City has value, initiating checkout update');
    $(document.body).trigger('update_checkout');
}

const closeMap = (e, errorMessage = null) => {
    console.debug('[CDEK-MAP] Removing selected office info');

    $('.cdek-office-info').remove();
    e.find('a').html(__('Choose pick-up', 'cdekdelivery'));
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

const onChoose = (_type, _tariff, address) => {
    $('.cdek-office-code').val(address.code);
    el.find('a').html(__('Re-select pick-up', 'cdekdelivery'));

    const officeInfo = el.parent().children('.cdek-office-info');
    if (officeInfo.length === 0) {
        el.before($('<div class="cdek-office-info"></div>').text(address.name));
    } else {
        officeInfo.text(address.name);
    }

    if (window.cdek.close) {
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

const initChanges = () => {
    needChange = false;
    isNormalSize = true;
};

const resizeObserver = new ResizeObserver(entries => {
    for (const entry of entries) {
        if(!entry.contentRect || !entry.target) {
            continue;
        }

        if (entry.contentRect.width < buttonNormalSize) {
            if (isNormalSize) {
                isNormalSize = false;
                needChange = true;
            }
        } else if (!isNormalSize) {
            isNormalSize = true;
            needChange = true;
        }

        if(!needChange){
            continue;
        }

        if (isNormalSize) {
            if (entry.target.hasAttribute(smallFontAttribute)) {
                entry.target.removeAttribute(smallFontAttribute);
            }
        } else if (!entry.target.hasAttribute(smallFontAttribute)) {
            entry.target.setAttribute(smallFontAttribute, '');
        }

        needChange = false;
    }
});

$(document.body)
  .on('input',
    '#billing_city, #billing_postcode, #shipping_city, #shipping_postcode',
    debouncedCheckoutUpdate)
  .on('updated_checkout', () => {
      const targetNode = document.querySelector('.open-pvz-btn');

      if (widget !== null) {
          console.debug('[CDEK-MAP] Clearing widget selection');

          widget.clearSelection();
      }

      if (targetNode) {
          initChanges();
          resizeObserver.observe(targetNode);
      }
  })
  .on('change', '.shipping_method',
    () => $(document.body).trigger('update_checkout'))
  .on('click', '.open-pvz-btn', null, (e) => {
      el = e.target.tagName === 'A' ? $(e.target.parentElement) : $(e.target);
      closeMap(el);

      try {
          const points = JSON.parse(el.find('script').text());
          console.debug('[CDEK-MAP] Got points from backend', points);

          if (!points.length) {
              console.warn('[CDEK-MAP] Backend points are empty');
              closeMap(el, __(
                'There are no CDEK pick-up points available in this direction, please select another delivery method',
                'cdekdelivery'));

              return;
          }

          if (widget === null) {
              widget = new cdekWidget({
                  apiKey: window.cdek.key,
                  popup: true,
                  debug: true,
                  lang: window.cdek.lang,
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
      } catch (SyntaxError) {
          console.error('[CDEK-MAP] SyntaxError during points parse');

          closeMap(el, __(
            'There are no CDEK pick-up points available in this direction, please select another delivery method',
            'cdekdelivery'));
      }
  });
