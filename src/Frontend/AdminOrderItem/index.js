'use strict';
import {addQueryArgs} from '@wordpress/url';
import $ from 'jquery';
import apiFetch from '@wordpress/api-fetch';

$(document).ready(() => {
    let dataUrl = null;

    const revokeDataUrl = () => {
        if (dataUrl !== null) {
            URL.revokeObjectURL(dataUrl);
        }
    };

    $('.official_cdek-show_uin').on('click', function (event) {
        event.preventDefault();
        const $container = $(this).next('.hidden');

        if ($container.length) {
            $(this).addClass('hidden');
            $container.removeClass('hidden');
        }
    });

    $('.official_cdek-save_uin').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        revokeDataUrl();

        const itemId = $(this).parent().data('id');
        const $input = $(`#official_cdek_jewel_uin_${itemId}`);

        apiFetch(
            {
                method: 'POST',
                url: addQueryArgs(ajaxurl, {
                    action: `${window.cdek_item.prefix}-jewel-uin`,
                    _wpnonce: window.cdek_item.nonce,
                }),
                data: {
                    item_id: itemId,
                    jewel_uin: $input.length ? $input.val() : '',
                },
                parse: false,
            },
        ).then(resp => {
            console.log('[CDEK-MAP] Save UIN response', resp);
           }
        ).catch(error => console.error('Error catch:', error));
    });
});
