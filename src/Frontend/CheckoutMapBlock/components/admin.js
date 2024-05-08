/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import '../styles/main.scss';

export const Edit = () => {
    const blockProps = useBlockProps();
    return (<div {...blockProps} className="admin-block-cdek-map">
        {__('Pickups map from CDEK', 'cdekdelivery')}
    </div>);
};

export const Save = () => (<div {...useBlockProps.save()} />);
