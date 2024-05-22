/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

const style = {
    display: 'flex',
    background: '#eeeeee',
    height: '600px',
    padding: '10px',
    'align-items': 'center',
    'justify-content': 'center',
};

export const Edit = () => {
    const blockProps = useBlockProps();
    return (<div {...blockProps} className="admin-block-cdek-map" style={style}>
        {__('Pickups map from CDEK', 'cdekdelivery')}
    </div>);
};

export const Save = () => (<div {...useBlockProps.save()} />);
