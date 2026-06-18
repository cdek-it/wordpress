/**
 * External dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { debounce, isEqual } from 'lodash';
import { getSetting } from '@woocommerce/settings';
import cdekWidget from '@cdek-it/widget';

export const Block = ({
    checkoutExtensionData, extensions, cart, validation,
}) => {
    const { apiKey, officeDeliveryModes, lang } = getSetting('official_cdek_data');

    const [showMap, setShowMap] = useState(false);
    const [validationError, setValidationError] = useState(null);

    const { setExtensionData } = checkoutExtensionData;

    const widgetRef = useRef(null);
    const lastCityRef = useRef(null);
    const lastOfficesRef = useRef(null);
    const lastOfficeCodeRef = useRef(null);

    const {
        setValidationErrors, clearValidationError, getValidationError,
    } = validation;

    const debouncedSetExtensionData = debounce((namespace, key, value) => {
        setExtensionData(namespace, key, value);
    }, 500);

    const debouncedMapRender = useCallback(debounce((shippingRates, points) => {
        if (points === '' || !cart.cartNeedsShipping) {
            lastOfficeCodeRef.current = null;
            debouncedSetExtensionData('official_cdek', 'office_code', null);
            clearValidationError('official_cdek_office');
            return;
        }

        const selectedRate = shippingRates.flatMap(
          (cartShippingRate) => cartShippingRate.shipping_rates)
          .find((rate) => rate.selected);

        if (!selectedRate ||
          !Object.prototype.hasOwnProperty.call(selectedRate, 'method_id') ||
          selectedRate.method_id !== 'official_cdek') {
            lastOfficeCodeRef.current = null;
            debouncedSetExtensionData('official_cdek', 'office_code', null);
            clearValidationError('official_cdek_office');
            return;
        }

        if (officeDeliveryModes.indexOf(parseInt(selectedRate.meta_data.find(
          (meta) => meta.key === '_official_cdek_tariff_mode').value)) === -1) {
            lastOfficeCodeRef.current = null;
            debouncedSetExtensionData('official_cdek', 'office_code', null);
            clearValidationError('official_cdek_office');
            return;
        }

        setValidationErrors({
            ['official_cdek_office']: {
                message: __('Choose pick-up', 'cdekdelivery'),
                hidden: true,
            },
        });

        const city = shippingRates[0].destination.city;
        const officesRaw = JSON.parse(points);

        if (widgetRef.current === null) {
            widgetRef.current = new cdekWidget({
                apiKey,
                lang,
                debug: true,
                defaultLocation: city,
                officesRaw,
                hideDeliveryOptions: {
                    door: true,
                },
                onChoose(_type, _tariff, address) {
                    lastOfficeCodeRef.current = address.code;
                    debouncedSetExtensionData('official_cdek', 'office_code',
                      address.code);
                    clearValidationError('official_cdek_office');
                },
            });
            lastCityRef.current = city;
            lastOfficesRef.current = officesRaw;
        } else if (city !== lastCityRef.current ||
            !isEqual(officesRaw, lastOfficesRef.current)) {
            widgetRef.current.clearSelection();
            widgetRef.current.updateOfficesRaw(officesRaw);
            widgetRef.current.updateLocation(city);
            lastCityRef.current = city;
            lastOfficesRef.current = officesRaw;
            lastOfficeCodeRef.current = null;
        } else if (lastOfficeCodeRef.current !== null) {
            clearValidationError('official_cdek_office');
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
