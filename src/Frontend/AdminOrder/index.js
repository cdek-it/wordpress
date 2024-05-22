'use strict';
import {__} from '@wordpress/i18n';
import $ from 'jquery';
import './styles/main.scss';
import apiFetch from '@wordpress/api-fetch';

$(document).ready(() => {
    let packageList = [];

    checkOrderAvailable();

    function checkOrderAvailable() {
        const dataStatusAvailable = $('#cdek-status-block')
          .data('status-available');
        if (dataStatusAvailable !== undefined && !dataStatusAvailable) {
            $('#order_data')
              .find('input[name="order_date"]')
              .attr('disabled', true);
            $('#order_data')
              .find('input[name="order_date_hour"]')
              .attr('disabled', true);
            $('#order_data')
              .find('input[name="order_date_minute"]')
              .attr('disabled', true);
            $('#order_data')
              .find('select[name="customer_user"]')
              .attr('disabled', true);
            $('#order_data').find('a[class="edit_address"]').hide();
        }
    }

    $('#selected_product').change(function() {
        let productId = $('#selected_product').val();
        $('#product_' + productId).css('display', 'flex');
    });
    $('#save_package').click(function() {
        $('#package_list').show();
        let packageData = {};
        packageData.length = $('input[name=package_length]').val();
        packageData.width = $('input[name=package_width]').val();
        packageData.height = $('input[name=package_height]').val();
        packageData.items = [];
        $('.product_list').each(function(index, item) {
            if ($(item).css('display') !== 'none') {
                packageData.items.push({
                    id: $(item).find('input[name=product_id]').val(),
                    name: $(item).find('input[type=text]').val(),
                    quantity: $(item).find('input[type=number]').val(),
                });
            }
        });

        if (checkForm(packageData)) {
            packageList.push(packageData);

            let packageInfo = '';
            packageInfo = `${__('Package', 'cdekdelivery')} №${packageList.length} (${packageData.length}х${packageData.width}х${packageData.height}):`;

            packageData.items.forEach(function(item) {
                packageInfo += `${item.name} х${item.quantity}, `;
            });

            $('#package_list').append(`<p>${packageInfo.slice(0, -2)}</p>`);

            calculateQuantity();
            cleanForm();
            checkFullPackage();
        }
    });

    $('#cdek-order-waybill, #cdek-order-barcode').click(function(e) {
        e.preventDefault();
        e.stopPropagation();

        const loader = $('#cdek-loader');

        loader.show();

        apiFetch({ method: 'GET', url: e.target.href }).then(resp => {
            if (!resp.success) {
                alert(resp.message);
                return;
            }

            const binaryString = window.atob(resp.data);
            const uint8Array = new Uint8Array(binaryString.length);
            for (let i = 0; i < binaryString.length; i++) {
                uint8Array[i] = binaryString.charCodeAt(i);
            }
            const dataUrl = window.URL.createObjectURL(
              new Blob([uint8Array], { type: 'application/pdf' }));

            const a = window.document.createElement('a');
            a.target = '_blank';
            a.href = dataUrl;
            window.document.body.appendChild(a);
            a.click();
            a.remove();

            setTimeout(() => URL.revokeObjectURL(dataUrl), 200);
        })
          .catch(e => console.error(e))
          .finally(() => loader.hide());
    });

    $('#send_package').click(function(e) {
        const loader = $('#cdek-loader');
        loader.show();

        apiFetch({
            method: 'POST', url: e.target.dataset.action, data: {
                packages: packageList,
            },
        })
          .then(resp => {
              if (!resp.state) {
                  $('#cdek-create-order-error').text(resp.message).show();
                  return;
              }

              if (resp.door) {
                  $('#cdek-courier-result-block').hide();
                  $('#cdek-order-courier').show();
              }
              $('#cdek-create-order-form').hide();
              $('#cdek-order-number').html(`№ <b>${resp.code}</b>`);
              $('#cdek-order-number-input').val(resp.code);
              $('#cdek-info-order').show();
          })
          .catch(e => console.error(e))
          .finally(() => loader.hide());
    });

    function checkFullPackage() {
        let option = $('#selected_product option');
        if (option.length === 1) {
            $('#setting_block').hide();
            $('#save_package_btn_block').hide();
            $('#send_package_btn_block').show();
        }
    }

    function checkForm(packageData) {
        if (packageData.length === '') {
            alert(__('Packing length not specified', 'cdekdelivery'));
            return false;
        }
        if (packageData.width === '') {
            alert(__('Packing width not specified', 'cdekdelivery'));
            return false;
        }
        if (packageData.height === '') {
            alert(__('Packing height not specified', 'cdekdelivery'));
            return false;
        }
        if (packageData.items.length === 0) {
            alert(__('Products not added to packaging', 'cdekdelivery'));
            return false;
        }
        return true;
    }

    function calculateQuantity() {
        $('.product_list').each(function(index, item) {
            if ($(item).css('display') !== 'none') {
                let max = $(item).find('input[type=number]').attr('max');
                let current = max - $(item).find('input[type=number]').val();
                if (current !== 0) {
                    $(item).find('input[type=number]').attr('max', current);
                } else {
                    $('#selected_product option').each(function(ind, option) {
                        if ($(option).text() ===
                          $(item).find('input[type=text]').val()) {
                            $(option).remove();
                        }
                    });
                }
            }
        });
    }

    function cleanForm() {
        $('#selected_product').val(-1);
        $('.product_list').each(function(index, item) {
            $(item).find('input[type=number]').val(1);
            $(item).css('display', 'none');
            $('input[name=package_length]').val('');
            $('input[name=package_width]').val('');
            $('input[name=package_height]').val('');
        });
    }

    $('#create-order-btn').click(function(e) {
        $('#cdek-create-order-error').hide();
        $('#cdek-loader').show();
        apiFetch({
            method: 'POST', url: e.target.dataset.action, data: {
                packages: [
                    {
                        length: $('input[name=package_length]').val(),
                        width: $('input[name=package_width]').val(),
                        height: $('input[name=package_height]').val(),
                    }],
            },
        })
          .then(resp => {
              if (!resp.state) {
                  $('#cdek-create-order-error')
                    .text(resp.message)
                    .show();
              } else {
                  if (resp.door) {
                      $('#cdek-courier-result-block').hide();
                      $('#cdek-order-courier').show();
                  }
                  $('#cdek-status-block')
                    .data('status-available', resp.available);
                  checkOrderAvailable();
                  $('#cdek-order-status-block').html(resp.statuses);
                  $('#cdek-create-order-form').hide();
                  $('#cdek-order-number')
                    .html(`№ <b>${resp.code}</b>`);
                  $('#cdek-order-number-input').val(resp.code);
                  $('#cdek-info-order').show();
              }
          })
          .catch(e => console.log(e))
          .finally(() => $('#cdek-loader').hide());
    });

    $('#delete-order-btn').click(function(event) {
        event.preventDefault();
        $(event.target).addClass('clicked');
        $('#cdek-create-order-error').hide();
        $('#cdek-courier-error').hide();
        $('#cdek-loader').show();
        apiFetch({
            method: 'POST', url: event.target.href,
        }).then(resp => {
            if (!resp.state) {
                $('#cdek-delete-order-error')
                  .text(resp.message)
                  .show();
                $('#delete-order-btn').hide();
            } else {
                alert(resp.message);
                $(event.target).removeClass('clicked');
                $('#cdek-create-order-form').show();
                $('#cdek-info-order').hide();
            }
        }).catch(e => console.error(e)).finally(() => $('#cdek-loader').hide());
    });

    $('#cdek-courier-send-call').click(function(event) {
        $('#cdek-courier-error').hide();
        $('#cdek-loader').show();
        apiFetch({
            method: 'POST', url: event.target.dataset.action, data: {
                order_id: $('input[name=package_order_id]').val(),
                date: $('#cdek-courier-date').val(),
                starttime: $('#cdek-courier-startime').val(),
                endtime: $('#cdek-courier-endtime').val(),
                name: $('#cdek-courier-name').val(),
                phone: $('#cdek-courier-phone').val(),
                address: $('#cdek-courier-address').val(),
                desc: $('#cdek-courier-package-desc').val(),
                comment: $('#cdek-courier-comment').val(),
                weight: $('#cdek-courier-weight').val(),
                length: $('#cdek-courier-length').val(),
                width: $('#cdek-courier-width').val(),
                height: $('#cdek-courier-height').val(),
                need_call: $('#cdek-courier-call').prop('checked'),
            },
        }).then(resp => {
            if (!resp) {
                $('#cdek-courier-error').html(resp.message).show();
            } else {
                $('#call-courier-form').hide();
                $('#cdek-order-courier').hide();
                $('#cdek-courier-info').text(resp.message).show();
                $('#cdek-courier-result-block').show();
            }
        }).catch(e => console.error(e)).finally(() => $('#cdek-loader').hide());
    });

    $('#cdek-order-courier').click(function() {
        $('#call-courier-form').toggle();
    });

    $('#cdek-courier-delete').click(function(event) {
        $('#cdek-loader').show();
        apiFetch({
            method: 'POST', url: event.target.dataset.action, data: {
                order_id: $('input[name=package_order_id]').val(),
            },
        }).then(resp => {
            if (resp) {
                $('#cdek-courier-result-block').hide();
                $('#cdek-order-courier').show();
            }
        }).catch(e => console.error(e)).finally(() => $('#cdek-loader').hide());
    });

    $('#cdek-info-order')
      .on('click', '#cdek-order-status-btn', function(event) {
          let statusList = $('#cdek-order-status-list');
          let arrowUp = $('#cdek-btn-arrow-up');
          let arrowDown = $('#cdek-btn-arrow-down');

          statusList.toggle();
          arrowUp.toggle(!statusList.is(':visible'));
          arrowDown.toggle(statusList.is(':visible'));
      });

});
