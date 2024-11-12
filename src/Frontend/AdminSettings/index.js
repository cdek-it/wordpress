import $ from 'jquery';
import {__} from '@wordpress/i18n';
import { createRoot, render } from '@wordpress/element';
import { DeliveryPrice } from './components/DeliveryPrice';

$.getJSON(window.cdek_admin_settings.api.check_auth)
 .done(() => $('.token-wrong').remove())
 .fail((jqxhr) => {
     console.error(jqxhr);
     $('p:contains(\'Custom Shipping Method for Cdek\')').after('<div class="cdek-error token-wrong">[CDEKDelivery] ' + __('Error receiving token. Make sure the integration keys are correct', 'cdekdelivery') + '</div>');
 });
const deliveryRulesInput = $('input#woocommerce_official_cdek_delivery_price_rules');

if (deliveryRulesInput.length){
    const div = window.document.createElement('div');
    deliveryRulesInput.after(div);
    if(typeof render === 'function'){
        render(<DeliveryPrice input={deliveryRulesInput}/>, div);
    } else {
        createRoot(div).render(<DeliveryPrice input={deliveryRulesInput}/>);
    }
}

(function() {
    const banAttachmentCheckbox = $('#woocommerce_official_cdek_services_ban_attachment_inspection');
    const tryingOnCheckbox = $('#woocommerce_official_cdek_services_trying_on');
    const partDelivCheckbox = $('#woocommerce_official_cdek_services_part_deliv');

    processBanAttachmentCheckbox(banAttachmentCheckbox, tryingOnCheckbox.add(partDelivCheckbox));
    processOtherServicesCheckbox([tryingOnCheckbox, partDelivCheckbox], banAttachmentCheckbox);

    banAttachmentCheckbox.change(function() {
        processBanAttachmentCheckbox($(this), tryingOnCheckbox.add(partDelivCheckbox));
    });

    tryingOnCheckbox.change(function() {
        processOtherServicesCheckbox([$(this), partDelivCheckbox], banAttachmentCheckbox);
    });

    partDelivCheckbox.change(function() {
        processOtherServicesCheckbox([$(this), tryingOnCheckbox], banAttachmentCheckbox);
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
