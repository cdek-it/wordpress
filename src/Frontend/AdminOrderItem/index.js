'use strict';
import {addQueryArgs} from '@wordpress/url';
import {__} from '@wordpress/i18n';
import './styles/main.scss';
import $ from 'jquery';
import apiFetch from '@wordpress/api-fetch';

$(document).ready(() => {
    $('.official_cdek-show_uin').on('click', function (event) {
        event.preventDefault();
        const $container = $(this).next('.hidden');

        if ($container.length === 0) {
            $(this).addClass('hidden');
            $container.removeClass('hidden');
        }
    });

    $('.official_cdek-save_uin').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $container = $(this).parent();
        const $input = $container.find('.official_cdek-jewel-uin');

        if ($input.length === 0) {
            return;
        }

        let $notice = $container.find(`.${window.cdek.prefix}-notice`);

        if ($notice.length !== 0) {
            $notice.remove();
        }

        $notice = $('<div></div>').addClass(`${window.cdek.prefix}-notice`);

        apiFetch(
            {
                method: 'POST',
                url: addQueryArgs(ajaxurl, {
                    action: `${window.cdek.prefix}-jewel-uin`,
                    _wpnonce: window.cdek.nonce,
                }),
                data: {
                    item_id: $container.data('id'),
                    jewel_uin: $input.val(),
                },
            },
        ).then(
            resp => {
                console.debug('[CDEK-ORDER-ITEM] Save UIN response', resp);

                if (resp.success) {
                    $notice.addClass('notice-success');
                } else {
                    $notice.addClass('notice-error');
                }

                $input.before($notice.text(resp.data.message));

                setTimeout(() => $notice.remove(), 5000);
            },
        ).catch(error => {
            console.error('Error catch:', error);

            $input.before(
                $notice
                    .addClass('notice-error')
                    .text(__('Error saving UIN', 'cdekdelivery')),
            );
        });
    });
});
