/**
 * Saves only the wrapped Query Loop, when there is one. The server renders
 * the carousel around it, so changing the block's settings never invalidates
 * saved content.
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return <InnerBlocks.Content />;
}
