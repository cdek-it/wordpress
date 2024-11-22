import $ from 'jquery';
import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';
import './styles/main.scss';
import { createRoot, render } from '@wordpress/element';
import { DeliveryPrice } from './components/DeliveryPrice';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

(() => {
    const suggest = debounce((q) => apiFetch({
        url: addQueryArgs(ajaxurl, {
            action: `${window.cdek.prefix}-cities`,
            _wpnonce: window.cdek.nonce,
            q,
        }),
    }).then(r => {
        if (r.data.length === 0) {
            $('.city-suggest')
              .append('<div class="city-suggest__404">' +
                __('Nothing found', 'cdekdelivery') + '</div>');
            return;
        }

        $('.city-suggest__404').remove();
        $('.city-suggest__item').remove();

        r.data.forEach(e => {
            $('.city-suggest')
              .append($('<div class="city-suggest__item"></div>')
                .html(e.full_name)
                .on('click', () => {
                    $('input#woocommerce_official_cdek_city')
                      .val(e.full_name.split(',', 2)[0]);
                    $('input#woocommerce_official_cdek_city_code')
                      .val(e.code);
                    $('.city-suggest').remove();
                }));
        });
    }).catch(() => {
        $('.city-suggest__404').remove();
        $('.city-suggest__item').remove();

        $('.city-suggest')
          .append('<div class="city-suggest__404">' +
            __('Temporal error, try again', 'cdekdelivery') + '</div>');
    }).finally(() => $('.city-loader').remove()), 900);

    $('input#woocommerce_official_cdek_city').on('input', function() {
        $('.city-suggest').remove();
        $('.city-loader').remove();

        $(this)
          .after('<div class="city-suggest"></div>')
          .after('<span class="city-loader"></span>');

        suggest(this.value);
    });

    const deliveryRulesInput = $(
      'input#woocommerce_official_cdek_delivery_price_rules');

    if (deliveryRulesInput.length) {
        const div = window.document.createElement('div');
        deliveryRulesInput.after(div);

        if (createRoot !== undefined) {
            const root = createRoot(div);

            if (root !== undefined && typeof root.render === 'function') {
                root.render(<DeliveryPrice input={deliveryRulesInput} />);
            } else {
                render(<DeliveryPrice input={deliveryRulesInput} />, div);
            }
        } else {
            render(<DeliveryPrice input={deliveryRulesInput} />, div);
        }
    }

    const banAttachmentCheckbox = $(
      '#woocommerce_official_cdek_services_ban_attachment_inspection');
    const tryingOnCheckbox = $('#woocommerce_official_cdek_services_trying_on');
    const partDelivCheckbox = $(
      '#woocommerce_official_cdek_services_part_deliv');

    processBanAttachmentCheckbox(banAttachmentCheckbox,
      tryingOnCheckbox.add(partDelivCheckbox));
    processOtherServicesCheckbox([tryingOnCheckbox, partDelivCheckbox],
      banAttachmentCheckbox);

    banAttachmentCheckbox.change(function() {
        processBanAttachmentCheckbox($(this),
          tryingOnCheckbox.add(partDelivCheckbox));
    });

    tryingOnCheckbox.change(function() {
        processOtherServicesCheckbox([$(this), partDelivCheckbox],
          banAttachmentCheckbox);
    });

    partDelivCheckbox.change(function() {
        processOtherServicesCheckbox([$(this), tryingOnCheckbox],
          banAttachmentCheckbox);
    });

    function processBanAttachmentCheckbox(currentService, bindService) {
        if (currentService.prop('checked')) {
            bindService.prop('checked', false);
            bindService.prop('disabled', true);
        } else {
            bindService.prop('disabled', false);
        }
    }

    function processOtherServicesCheckbox(services, bindService) {
        if (services[0].prop('checked') || services[1].prop('checked')) {
            bindService.prop('checked', false);
            bindService.prop('disabled', true);
        } else {
            bindService.prop('disabled', false);
        }
    }
})();
