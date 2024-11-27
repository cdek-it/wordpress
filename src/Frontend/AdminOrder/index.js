'use strict';
import { __ } from '@wordpress/i18n';
import $ from 'jquery';
import './styles/main.scss';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

$(document).ready(() => {
    const packageList = [];
    let dataUrl = null;

    const revokeDataUrl = () => {
        if (dataUrl !== null) {
            URL.revokeObjectURL(dataUrl);
        }
    };

    const metaBox = $('#official_cdek_order')
      .on('change', '.create select',
        e => metaBox.find(`.item[data-id=${e.target.value}]`)
          .attr('aria-hidden', 'false'))
      .on('click', '.print', e => {
          e.preventDefault();
          e.stopPropagation();

          const loader = metaBox.find('.loader');
          loader.attr('aria-disabled', 'false');

          revokeDataUrl();

          apiFetch({
              method: 'GET', url: addQueryArgs(ajaxurl, {
                  action: `${window.cdek.prefix}-${e.target.dataset.action}`,
                  _wpnonce: window.cdek.nonce,
                  id: e.target.dataset.id,
              }),
          }).then(resp => {
              if (!resp.success) {
                  alert(resp.message);
                  return;
              }

              const binaryString = window.atob(resp.data);
              const uint8Array = new Uint8Array(binaryString.length);
              for (let i = 0; i < binaryString.length; i++) {
                  uint8Array[i] = binaryString.charCodeAt(i);
              }
              dataUrl = window.URL.createObjectURL(
                new Blob([uint8Array], { type: 'application/pdf' }));

              const a = window.document.createElement('a');
              a.target = '_blank';
              a.href = dataUrl;
              window.document.body.appendChild(a);
              a.click();
              a.remove();

              window.document.addEventListener('beforeunload',
                () => URL.revokeObjectURL(dataUrl));
          })
            .catch(e => console.error(e))
            .finally(() => loader.attr('aria-disabled', 'true'));
      })
      .on('click', '.create button.package', e => {
          e.preventDefault();
          e.stopPropagation();

          const len = metaBox.find('input[name=length]').val();
          const wid = metaBox.find('input[name=width]').val();
          const hei = metaBox.find('input[name=height]').val();

          if (isNaN(len) || len === '') {
              alert(__('Package length not specified', 'cdekdelivery'));
              return;
          }

          if (isNaN(wid) || wid === '') {
              alert(__('Package width not specified', 'cdekdelivery'));
              return;
          }

          if (isNaN(hei) || hei === '') {
              alert(__('Package height not specified', 'cdekdelivery'));
              return;
          }

          const packageData = {
              length: parseInt(len),
              width: parseInt(wid),
              height: parseInt(hei),
              items: metaBox.find(`.item[data-id][aria-hidden=false]`)
                .map((i, e) => ({
                    id: parseInt(e.dataset.id),
                    name: $(e).text(),
                    qty: parseInt($(e).find('input[type=number]').val()),
                })).toArray(),
          };

          if (packageData.length < 1) {
              alert(__('Package length should be greater 1', 'cdekdelivery'));
              return;
          }
          if (packageData.width < 1) {
              alert(__('Package width should be greater 1', 'cdekdelivery'));
              return;
          }
          if (packageData.height < 1) {
              alert(__('Package height should be greater 1', 'cdekdelivery'));
              return;
          }
          if (packageData.items.length < 1) {
              alert(__('Items not added to package', 'cdekdelivery'));
              return;
          }

          packageList.push(packageData);

          metaBox.find('.create .list')
            .append($('<p></p>')
              .text(__('Package', 'cdekdelivery') +
                ` №${packageList.length} (${packageData.length}х${packageData.width}х${packageData.height}):` +
                packageData.items.reduce(
                  (acc, e) => acc + `${e.name}${e.qty}, `, '').slice(0, -2)));

          metaBox.find(`.create .item[data-id][aria-hidden=false]`)
            .each((i, e) => {
                const input = $(e).find('input[type=number]');

                const left = input.attr('max') - input.val();

                if (left !== 0) {
                    input.attr('max', left);
                    return;
                }

                metaBox.find(`.create select option[value=${e.dataset.id}]`)
                  .remove();
            });

          metaBox.find('.create select').val(-1);
          metaBox.find('.create .pack input[type=text]').val('');
          metaBox.find('.create .item[data-id][aria-hidden=false]')
            .attr('aria-hidden', 'true');

          if (metaBox.find('.create select option').length !== 1) {
              return;
          }

          metaBox.find('.create').attr('aria-invalid', 'false');
      })
      .on('click', '.create button', e => {
          if (!Object.prototype.hasOwnProperty.call(e.target.dataset, 'id')) {
              return;
          }

          e.preventDefault();
          e.stopPropagation();

          revokeDataUrl();

          const loader = metaBox.find('.loader');
          loader.attr('aria-disabled', 'false');

          apiFetch({
              method: 'POST', url: addQueryArgs(ajaxurl, {
                  action: `${window.cdek.prefix}-create`,
                  _wpnonce: window.cdek.nonce,
                  id: e.target.dataset.id,
              }), data: packageList.length !== 0 ? packageList : [
                  {
                      length: metaBox.find('input[name=length]').val(),
                      width: metaBox.find('input[name=width]').val(),
                      height: metaBox.find('input[name=height]').val(),
                  }], parse: false,
          })
            .then(r => r.text()).then(t => metaBox.find('.inside').html(t))
            .catch(e => console.error(e))
            .finally(() => loader.attr('aria-disabled', 'true'));
      })
      .on('click', '.deletion', e => {
          e.preventDefault();
          e.stopPropagation();

          revokeDataUrl();

          const loader = metaBox.find('.loader');
          loader.attr('aria-disabled', 'false');

          apiFetch({
              method: 'POST', url: addQueryArgs(ajaxurl, {
                  action: `${window.cdek.prefix}-${e.target.dataset.action}`,
                  _wpnonce: window.cdek.nonce,
                  id: e.target.dataset.id,
              }), parse: false,
          })
            .then(r => r.text()).then(t => metaBox.find('.inside').html(t))
            .catch(e => console.error(e))
            .finally(() => loader.attr('aria-disabled', 'true'));
      })
      .on('click', '.toggle', e => {
          const target = e.target.classList.contains('toggle')
            ? e.target.parentElement
            : e.target.parentElement.parentElement;

          target.ariaExpanded = target.ariaExpanded === 'true'
            ? 'false'
            : 'true';
      })
      .on('click', '.intake button', e => {
          e.preventDefault();
          e.stopPropagation();

          revokeDataUrl();

          metaBox.find('.intake input[required]')
            .each((i, e) => e.ariaInvalid = e.value === '' ? 'true' : 'false');

          if (metaBox.find(
            '.intake input[required][aria-invalid=true]').length > 0) {
              return;
          }

          const loader = metaBox.find('.loader');
          loader.attr('aria-disabled', 'false');

          apiFetch({
              method: 'POST', url: addQueryArgs(ajaxurl, {
                  action: `${window.cdek.prefix}-intake_create`,
                  _wpnonce: window.cdek.nonce,
                  id: e.target.dataset.id,
              }), data: {
                  date: metaBox.find('input[name=date]').val(),
                  from: metaBox.find('input[name=from]').val(),
                  to: metaBox.find('input[name=to]').val(),
                  desc: metaBox.find('input[name=desc]').val(),
                  comment: metaBox.find('input[name=comment]').val(),
                  weight: parseInt(metaBox.find('input[name=weight]').val()),
                  call: metaBox.find('input[name=call]').is(':checked'),
              }, parse: false,
          })
            .then(r => r.text()).then(t => metaBox.find('.inside').html(t))
            .catch(e => console.error(e))
            .finally(() => loader.attr('aria-disabled', 'true'));
      });
});
