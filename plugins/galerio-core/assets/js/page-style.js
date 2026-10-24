/**
 * The editor's Page style panel: a closed panel in the sidebar that opens a
 * window for the page's fonts, colours and CSS, previewed in the editor.
 *
 * Settings come from `window.galerioPageStyle`, added by the Page_Style class.
 *
 * @param {Object} wp   WordPress's scripts.
 * @param {Object} data Settings from the server.
 */
( function ( wp, data ) {
	if ( ! wp || ! data ) {
		return;
	}

	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor;
	const {
		Button,
		ColorIndicator,
		ColorPicker,
		Dropdown,
		Modal,
		SelectControl,
		TabPanel,
		TextareaControl,
		Notice,
	} = wp.components;
	const { useSelect, useDispatch, select } = wp.data;
	const { useEntityProp } = wp.coreData;
	const {
		createElement: el,
		Fragment,
		useEffect,
		useRef,
		useState,
	} = wp.element;
	const { __, _n, sprintf } = wp.i18n;

	const PREVIEW_SOURCE = 'galerio-page-style';

	/**
	 * A short description of a style, for the closed panel.
	 *
	 * @param {Object} style Style.
	 * @return {string} Summary.
	 */
	function summary( style ) {
		const fonts = Object.keys( style.fonts || {} ).length;
		const colors = Object.keys( style.colors || {} ).length;
		const parts = [];

		if ( fonts ) {
			parts.push(
				sprintf(
					/* translators: %d: number of fonts. */
					_n( '%d font', '%d fonts', fonts, 'galerio-core' ),
					fonts
				)
			);
		}
		if ( colors ) {
			parts.push(
				sprintf(
					/* translators: %d: number of colours. */
					_n( '%d colour', '%d colours', colors, 'galerio-core' ),
					colors
				)
			);
		}
		if ( style.css ) {
			parts.push( __( 'custom CSS', 'galerio-core' ) );
		}

		return parts.length
			? sprintf(
					/* translators: %s: list such as "2 fonts, 3 colours, custom CSS". */
					__( 'Own style: %s.', 'galerio-core' ),
					parts.join( ', ' )
				)
			: __( 'Uses the site’s style.', 'galerio-core' );
	}

	/**
	 * A copy of a style with one preset set, or removed when the value is empty.
	 *
	 * @param {Object} style Style.
	 * @param {string} group "fonts" or "colors".
	 * @param {string} slug  Preset.
	 * @param {string} value New value.
	 * @return {Object} Style.
	 */
	function withPreset( style, group, slug, value ) {
		const values = Object.assign( {}, style[ group ] );
		if ( value ) {
			values[ slug ] = value;
		} else {
			delete values[ slug ];
		}
		return Object.assign( {}, style, { [ group ]: values } );
	}

	function FontsTab( { style, onChange } ) {
		return el(
			Fragment,
			null,
			el(
				'p',
				null,
				__(
					'Replace the theme’s fonts on this page only. Fonts added in the Font Library can be used here without making them part of the site’s style.',
					'galerio-core'
				)
			),
			data.presets.fonts.map( ( preset ) =>
				el( SelectControl, {
					key: preset.slug,
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
					label: preset.name,
					value: ( style.fonts || {} )[ preset.slug ] || '',
					options: [
						{
							label: __( 'The site’s font', 'galerio-core' ),
							value: '',
						},
					].concat(
						data.families.map( ( family ) => ( {
							label: family.name,
							value: family.slug,
						} ) )
					),
					onChange: ( value ) =>
						onChange(
							withPreset( style, 'fonts', preset.slug, value )
						),
				} )
			)
		);
	}

	function ColorsTab( { style, onChange } ) {
		return el(
			Fragment,
			null,
			el(
				'p',
				null,
				__(
					'Replace the theme’s colours on this page only.',
					'galerio-core'
				)
			),
			el(
				'ul',
				{ className: 'galerio-page-style__colors' },
				data.presets.colors.map( ( preset ) => {
					const own = ( style.colors || {} )[ preset.slug ];

					return el(
						'li',
						{ key: preset.slug },
						el( Dropdown, {
							popoverProps: { placement: 'left-start' },
							renderToggle: ( { isOpen, onToggle } ) =>
								el(
									Button,
									{
										onClick: onToggle,
										'aria-expanded': isOpen,
										className: 'galerio-page-style__color',
									},
									el( ColorIndicator, {
										colorValue: own || preset.value,
									} ),
									el( 'span', null, preset.name ),
									own &&
										el(
											'em',
											null,
											__( 'changed', 'galerio-core' )
										)
								),
							renderContent: () =>
								el(
									'div',
									{ style: { padding: '8px' } },
									el( ColorPicker, {
										color: own || preset.value,
										enableAlpha: true,
										onChange: ( value ) =>
											onChange(
												withPreset(
													style,
													'colors',
													preset.slug,
													value
												)
											),
									} ),
									el(
										Button,
										{
											variant: 'tertiary',
											disabled: ! own,
											onClick: () =>
												onChange(
													withPreset(
														style,
														'colors',
														preset.slug,
														''
													)
												),
										},
										__(
											'Use the site’s colour',
											'galerio-core'
										)
									)
								),
						} )
					);
				} )
			)
		);
	}

	function CssTab( { style, onChange } ) {
		if ( ! data.canEditCss ) {
			return el(
				Notice,
				{ status: 'info', isDismissible: false },
				style.css
					? __(
							'This page has custom CSS, which only administrators can change.',
							'galerio-core'
						)
					: __(
							'Only administrators can add custom CSS.',
							'galerio-core'
						)
			);
		}

		return el( TextareaControl, {
			__nextHasNoMarginBottom: true,
			className: 'galerio-page-style__css',
			label: __( 'Custom CSS', 'galerio-core' ),
			help: __(
				'Applies to this page only, after the settings above. Use it for what fonts and colours can’t do.',
				'galerio-core'
			),
			value: style.css || '',
			rows: 16,
			spellCheck: false,
			onChange: ( value ) =>
				onChange( Object.assign( {}, style, { css: value } ) ),
		} );
	}

	/**
	 * Show the page's style in the editor, using the same CSS as the page.
	 *
	 * @param {Object} style Style.
	 */
	function usePreview( style ) {
		const { updateEditorSettings } = useDispatch( 'core/editor' );
		const base = useRef( null );
		const key = JSON.stringify( style );

		useEffect( () => {
			let current = true;
			const timer = setTimeout( () => {
				wp.apiFetch( {
					path: '/galerio/v1/page-style/css',
					method: 'POST',
					data: { style },
				} )
					.then( ( response ) => {
						if ( ! current ) {
							return;
						}
						if ( ! base.current ) {
							base.current = (
								select( 'core/editor' ).getEditorSettings()
									.styles || []
							).filter(
								( item ) => item.source !== PREVIEW_SOURCE
							);
						}
						updateEditorSettings( {
							styles: response.css
								? base.current.concat( {
										css: response.css,
										source: PREVIEW_SOURCE,
									} )
								: base.current,
						} );
					} )
					.catch( () => {} );
			}, 300 );

			return () => {
				current = false;
				clearTimeout( timer );
			};
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ key ] );
	}

	function PageStylePanel() {
		const postType = useSelect(
			( store ) => store( 'core/editor' ).getCurrentPostType(),
			[]
		);
		const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
		const [ isOpen, setOpen ] = useState( false );
		const style = ( meta && meta[ data.meta ] ) || {};
		const isEmpty =
			! style.css &&
			! Object.keys( style.fonts || {} ).length &&
			! Object.keys( style.colors || {} ).length;

		usePreview( style );

		if ( ! meta ) {
			return null;
		}

		const setStyle = ( next ) =>
			setMeta( Object.assign( {}, meta, { [ data.meta ]: next } ) );

		const tabs = [
			{
				name: 'fonts',
				title: __( 'Fonts', 'galerio-core' ),
				Tab: FontsTab,
			},
			{
				name: 'colors',
				title: __( 'Colours', 'galerio-core' ),
				Tab: ColorsTab,
			},
			{ name: 'css', title: __( 'CSS', 'galerio-core' ), Tab: CssTab },
		];

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'galerio-page-style',
				title: __( 'Page style', 'galerio-core' ),
			},
			el( 'p', null, summary( style ) ),
			el(
				Button,
				{ variant: 'secondary', onClick: () => setOpen( true ) },
				__( 'Edit page style…', 'galerio-core' )
			),
			isOpen &&
				el(
					Modal,
					{
						title: __( 'Page style', 'galerio-core' ),
						size: 'medium',
						className: 'galerio-page-style',
						onRequestClose: () => setOpen( false ),
					},
					el( TabPanel, { tabs }, ( tab ) =>
						el( tab.Tab, { style, onChange: setStyle } )
					),
					el(
						'div',
						{ className: 'galerio-page-style__actions' },
						el(
							Button,
							{
								variant: 'tertiary',
								isDestructive: true,
								disabled: isEmpty,
								onClick: () => setStyle( {} ),
							},
							__( 'Use the site’s style', 'galerio-core' )
						),
						el(
							Button,
							{
								variant: 'primary',
								onClick: () => setOpen( false ),
							},
							__( 'Done', 'galerio-core' )
						)
					)
				)
		);
	}

	registerPlugin( 'galerio-page-style', { render: PageStylePanel } );
} )( window.wp, window.galerioPageStyle );
