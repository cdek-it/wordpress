'use strict';
import {addQueryArgs} from '@wordpress/url';
import { __ } from '@wordpress/i18n';
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
        const $input = $(`#${window.cdek_item.prefix}_jewel_uin_${itemId}`);

        if(!$input.length) {
            return;
        }

        if($(this).parent().find(`.${window.cdek_item.prefix}-notice`).length) {
            $(this).parent().find(`.${window.cdek_item.prefix}-notice`).remove();
        }

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
        ).then(
            resp => {
                console.debug('[CDEK-MAP] Save UIN response', resp);

                const $messageContainer = $('<div></div>')
                    .addClass(`${window.cdek_item.prefix}-notice`);

                if (resp.success) {
                    $messageContainer
                        .addClass('notice-success')
                        .text(resp.message);
                } else {
                    $messageContainer
                        .addClass('notice-error')
                        .text(resp.message);
                }

                $input.before($messageContainer);

                setTimeout(() => {
                    $messageContainer.remove();
                }, 5000);
            }
        ).catch(error => {
            console.error('Error catch:', error);

            const $messageContainer = $('<div></div>')
                .addClass(`${window.cdek_item.prefix}-notice`)
                .addClass('notice-error')
                .text(__('Error saving UIN', 'cdek-map'));

            $input.before($messageContainer);
        });
    });
});
