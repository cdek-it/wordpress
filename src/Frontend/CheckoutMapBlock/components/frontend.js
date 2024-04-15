/**
 * External dependencies
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';
import { getSetting } from '@woocommerce/settings';
import cdekWidget from '@cdek-it/widget';

export const Block = ({
    checkoutExtensionData, extensions, cart, validation,
}) => {
    const { apiKey, officeDeliveryModes } = getSetting('official_cdek_data');

    const [showMap, setShowMap] = useState(false);
    const [validationError, setValidationError] = useState(null);

    const { setExtensionData } = checkoutExtensionData;

    const {
        setValidationErrors, clearValidationError, getValidationError,
    } = validation;

    const debouncedSetExtensionData = debounce((namespace, key, value) => {
        setExtensionData(namespace, key, value);
    }, 500);

    const debouncedMapRender = useCallback(debounce((shippingRates, points) => {
        if (points === '' || !cart.cartNeedsShipping) {
            debouncedSetExtensionData('official_cdek', 'office_code', null);
            clearValidationError('official_cdek_office');
            return;
        }

        const selectedRate = shippingRates.flatMap(
          (cartShippingRate) => cartShippingRate.shipping_rates)
          .find((rate) => rate.selected);

        if (!selectedRate ||
          Object.prototype.hasOwnProperty.call(selectedRate, 'method_id') ||
          selectedRate.method_id !== 'official_cdek') {
            debouncedSetExtensionData('official_cdek', 'office_code', null);
            clearValidationError('official_cdek_office');
            return;
        }

        if (officeDeliveryModes.indexOf(parseInt(selectedRate.meta_data.find(
          (meta) => meta.key === '_official_cdek_tariff_mode').value)) === -1) {
            debouncedSetExtensionData('official_cdek', 'office_code', null);
            clearValidationError('official_cdek_office');
            return;
        }

        setValidationErrors({
            ['official_cdek_office']: {
                message: __('Выберите пункт получения', 'official-cdek'),
                hidden: true,
            },
        });

        if (window.widget === undefined) {
            window.widget = new cdekWidget({
                apiKey: apiKey,
                debug: true,
                defaultLocation: shippingRates[0].destination.city,
                officesRaw: JSON.parse(points),
                hideDeliveryOptions: {
                    door: true,
                },
                onChoose(_type, _tariff, address) {
                    debouncedSetExtensionData('official_cdek', 'office_code',
                      address.code);
                    clearValidationError('official_cdek_office');
                },
            });
        } else {
            window.widget.clearSelection();
            window.widget.updateOfficesRaw(JSON.parse(points));
            window.widget.updateLocation(shippingRates[0].destination.city);
        }

        setShowMap(true);
    }, 1000), []);

    useEffect(() => {
        setShowMap(false);
        if (cart.isLoading || cart.isLoadingRates ||
          !extensions.official_cdek) {
            clearValidationError('official_cdek_office');
            return;
        }

        debouncedMapRender(cart.shippingRates,
          extensions.official_cdek.points || []);
    }, [
        cart.isLoading,
        cart.isLoadingRates,
        cart.shippingRates,
        extensions.official_cdek]);

    useEffect(() => {
        setValidationError(getValidationError('official_cdek_office'));
    });

    return <div className="wp-block-shipping-cdek-map"
                style={{ display: showMap ? 'block' : 'none' }}>
        <div id="cdek-map" style={{
            height: '600px',
            border: validationError?.hidden === false
              ? '1px solid #cc1818'
              : '',
        }}></div>
        {validationError?.hidden === false &&
          (<div className="wc-block-components-validation-error" role="alert">
              <p>{validationError.message}</p></div>)}
    </div>;
};
