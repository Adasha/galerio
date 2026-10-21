/**
 * src/index.js
 *
 * Block registration entry point.
 * Imports block metadata, the Edit component, and the Save component,
 * then registers the block type with WordPress.
 */

import { registerBlockType } from '@wordpress/blocks';

// Import block metadata (block.json is resolved via @wordpress/scripts webpack config).
import metadata from './block.json';

// Import the editor Edit component.
import Edit from './edit';

// Import the frontend-serialised Save component.
import save from './save';

// Import editor-only styles (loaded in the block editor only).
import './editor.scss';

/**
 * Register the custom/query-carousel block type.
 *
 * The `metadata` object (from block.json) provides `name`, `attributes`,
 * `supports`, and all other declarative configuration.
 * The `edit` and `save` functions handle editor and serialised markup respectively.
 */
registerBlockType( metadata.name, {
	edit: Edit,
	save,
} );
