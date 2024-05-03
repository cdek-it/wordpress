import { registerBlockType } from '@wordpress/blocks';
import { Icon } from '@wordpress/icons';

import { Edit, Save } from './components/admin';
import metadata from './block.json';
import icon from './icons/cdek';

registerBlockType( metadata, {
    icon: {
        src: <Icon icon={ icon } />,
    },
    edit: Edit,
    save: Save,
} );
