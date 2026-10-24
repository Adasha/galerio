/**
 * The Exhibition Dates block: settings in the sidebar and a preview rendered
 * by the server, as on the front end.
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import './style.scss';

function Edit( { attributes, setAttributes, context } ) {
	const { format, showStatus } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Dates', 'galerio-blocks' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Format', 'galerio-blocks' ) }
						value={ format }
						options={ [
							{
								label: __(
									'Both dates in full (the site’s date format)',
									'galerio-blocks'
								),
								value: 'site',
							},
							{
								label: __(
									'Short range, e.g. 19 May – 16 June 2023',
									'galerio-blocks'
								),
								value: 'short',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { format: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show upcoming, on now or past',
							'galerio-blocks'
						) }
						checked={ showStatus }
						onChange={ ( value ) =>
							setAttributes( { showStatus: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
					urlQueryArgs={
						context.postId ? { post_id: context.postId } : {}
					}
					skipBlockSupportAttributes
					EmptyResponsePlaceholder={ () => (
						<p>
							{ __(
								'Exhibition dates show here when the collection has them.',
								'galerio-blocks'
							) }
						</p>
					) }
				/>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
