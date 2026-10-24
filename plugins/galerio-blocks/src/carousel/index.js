/**
 * The Carousel block.
 */
import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import save from './save';
import transforms from './transforms';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save,
	transforms,
} );
