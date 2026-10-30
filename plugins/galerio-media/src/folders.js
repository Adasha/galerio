/**
 * The folder tree in the Media Library (grid and list views) and the media
 * picker.
 *
 * The grid and the picker are built from the same "attachments browser" view,
 * so the tree is added to every browser as it renders: a column on the left
 * listing the shared folders and "My folders". Choosing a folder narrows the
 * files shown (sent as `query[galerio_folder]`), files dragged onto a folder
 * are filed there, and uploads made while a folder is open go into it.
 * Folders drag onto other folders of their space, or onto its heading to go
 * to the top, by the people who may change them.
 *
 * The list view is a plain admin page, so there the tree sits beside the
 * table: choosing a folder reloads the list filtered to it (`?galerio_folder=`),
 * rows drag onto folders, and "Add New Media File" uploads into the open folder.
 */
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Spinner } from '@wordpress/components';
import { createRoot, useEffect, useRef, useState } from '@wordpress/element';

import './style.scss';

const DRAG_TYPE = 'application/x-galerio-attachments';
const FOLDER_TYPE = 'application/x-galerio-folder';
const EXPANDED_KEY = 'galerio-media-expanded';

/**
 * Folder IDs the person had open, remembered in this browser.
 *
 * @return {Set<number>} Open folders.
 */
function loadExpanded() {
	try {
		return new Set(
			JSON.parse( window.localStorage.getItem( EXPANDED_KEY ) || '[]' )
		);
	} catch {
		return new Set();
	}
}

/**
 * Remember open folders.
 *
 * @param {Set<number>} expanded Open folders.
 */
function saveExpanded( expanded ) {
	try {
		window.localStorage.setItem(
			EXPANDED_KEY,
			JSON.stringify( [ ...expanded ] )
		);
	} catch {}
}

/**
 * The browser's files and uploader, so the tree can steer them.
 *
 * @param {Object} browser Attachments browser view.
 * @return {Object} Helpers.
 */
function library( browser ) {
	const { collection } = browser;

	return {
		chosen: () => collection.props.get( 'galerio_folder' ) || 0,

		choose( folder ) {
			if ( folder ) {
				collection.props.set( { galerio_folder: folder } );
			} else {
				collection.props.unset( 'galerio_folder' );
			}

			// Uploads go into the folder that's open.
			const value = typeof folder === 'number' ? folder : 0;
			const uploader = browser.controller?.uploader?.uploader?.uploader;
			if ( uploader?.settings?.multipart_params ) {
				uploader.settings.multipart_params.galerio_folder = value;
			}
			if ( window.wp?.Uploader?.defaults?.multipart_params ) {
				window.wp.Uploader.defaults.multipart_params.galerio_folder =
					value;
			}
		},

		moved( ids, leaves ) {
			if ( leaves ) {
				collection.remove( ids );
			}
			window.wp?.media?.model?.Query?.cleanQueries?.();
		},
	};
}

/**
 * The list view's folder choice, which lives in the page's address.
 *
 * @return {Object} Helpers, as library() gives the grid.
 */
function listLibrary() {
	const value = new URL( window.location.href ).searchParams.get(
		'galerio_folder'
	);
	const chosen = value === 'unfiled' ? 'unfiled' : Number( value ) || 0;

	return {
		chosen: () => chosen,

		choose( folder ) {
			const next = new URL( window.location.href );
			if ( folder ) {
				next.searchParams.set( 'galerio_folder', folder );
			} else {
				next.searchParams.delete( 'galerio_folder' );
			}
			next.searchParams.delete( 'paged' );
			window.location.assign( next.toString() );
		},

		// Rows that leave the folder shown go; otherwise the page reloads so
		// the Folders column is right.
		moved( ids, leaves ) {
			if ( ! leaves ) {
				window.location.reload();
				return;
			}
			ids.forEach( ( id ) =>
				document.getElementById( `post-${ id }` )?.remove()
			);
		},
	};
}

function FolderTree( { lib } ) {
	const [ data, setData ] = useState( null );
	const [ chosen, setChosen ] = useState( lib.chosen() );
	const [ expanded, setExpanded ] = useState( loadExpanded );
	const [ editing, setEditing ] = useState( null );
	const [ target, setTarget ] = useState( null );
	const [ dragging, setDragging ] = useState( null );
	const [ message, setMessage ] = useState( '' );
	const field = useRef();

	// The name field takes focus when it opens.
	useEffect( () => {
		field.current?.focus();
	}, [ editing?.mode, editing?.id, editing?.personal ] );

	const load = () =>
		apiFetch( { path: '/galerio-media/v1/folders' } )
			.then( setData )
			.catch( () => setData( { folders: [] } ) );

	useEffect( () => {
		load();
	}, [] );

	// The folders around the chosen one open, so it shows, e.g. when the
	// list view reloads with a folder inside a closed one.
	useEffect( () => {
		if ( ! data || typeof chosen !== 'number' ) {
			return;
		}
		const parents = new Map(
			data.folders.map( ( folder ) => [ folder.id, folder.parent ] )
		);
		const next = new Set( expanded );
		for ( let id = parents.get( chosen ); id; id = parents.get( id ) ) {
			next.add( id );
		}
		if ( next.size !== expanded.size ) {
			setExpanded( next );
			saveExpanded( next );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- Only when the folders or the choice change.
	}, [ data, chosen ] );

	if ( ! data ) {
		return (
			<div className="galerio-folders is-loading">
				<Spinner />
			</div>
		);
	}

	const byParent = new Map();
	data.folders.forEach( ( folder ) => {
		const list = byParent.get( folder.parent ) || [];
		list.push( folder );
		byParent.set( folder.parent, list );
	} );
	const find = ( id ) => data.folders.find( ( folder ) => folder.id === id );
	const current = typeof chosen === 'number' ? find( chosen ) : null;
	const root = data.folders.find( ( folder ) => folder.root );

	const choose = ( folder ) => {
		setChosen( folder );
		lib.choose( folder );
	};

	const toggle = ( id ) => {
		const next = new Set( expanded );
		if ( next.has( id ) ) {
			next.delete( id );
		} else {
			next.add( id );
		}
		setExpanded( next );
		saveExpanded( next );
	};

	const fail = ( error ) =>
		setMessage(
			error?.message || __( 'That didn’t work.', 'galerio-media' )
		);

	// Add a folder at the top of a space, or inside a folder.
	const startAdd = ( personal, parent = 0 ) =>
		setEditing( { mode: 'add', parent, personal, value: '' } );

	const save = ( event ) => {
		event.preventDefault();
		const name = editing.value.trim();
		if ( ! name ) {
			setEditing( null );
			return;
		}

		const request =
			editing.mode === 'add'
				? apiFetch( {
						path: '/galerio-media/v1/folders',
						method: 'POST',
						data: {
							name,
							parent: editing.parent,
							personal: editing.personal,
						},
					} ).then( ( response ) => {
						if ( editing.parent ) {
							const next = new Set( expanded ).add(
								editing.parent
							);
							setExpanded( next );
							saveExpanded( next );
						}
						return response;
					} )
				: apiFetch( {
						path: `/wp/v2/media-folders/${ editing.id }`,
						method: 'POST',
						data: { name },
					} );

		request
			.then( () => {
				setEditing( null );
				setMessage( '' );
				load();
			} )
			.catch( fail );
	};

	const remove = () => {
		if (
			// eslint-disable-next-line no-alert
			! window.confirm(
				sprintf(
					/* translators: %s: folder name. */
					__(
						'Delete the folder “%s”? Its files stay in the Media Library, and folders inside it move up a level.',
						'galerio-media'
					),
					current.name
				)
			)
		) {
			return;
		}

		apiFetch( {
			path: `/wp/v2/media-folders/${ current.id }?force=true`,
			method: 'DELETE',
		} )
			.then( () => {
				choose( 0 );
				load();
			} )
			.catch( fail );
	};

	// Dropping files on a folder files them there; on "Not in a folder" or
	// "My folders", takes them out of that space's folders.
	const drop = ( event, folder, owner ) => {
		event.preventDefault();
		setTarget( null );

		let ids;
		try {
			ids = JSON.parse( event.dataTransfer.getData( DRAG_TYPE ) );
		} catch {
			return;
		}

		// Filing only changes the files' folders in one space (the shared
		// folders, or someone's own), so they leave the view only when it
		// shows another folder of that space, or shows files in no shared
		// folder and they went into one.
		const space = folder ? find( folder )?.owner : owner;
		let leaves = false;
		if ( chosen === 'unfiled' ) {
			leaves = !! folder && space === 0;
		} else if ( chosen ) {
			leaves = chosen !== folder && find( chosen )?.owner === space;
		}

		apiFetch( {
			path: '/galerio-media/v1/file',
			method: 'POST',
			data: { folder, owner, attachments: ids },
		} )
			.then( ( response ) => {
				lib.moved( ids, leaves );
				setMessage(
					sprintf(
						/* translators: %d: number of files. */
						__( '%d moved.', 'galerio-media' ),
						response.filed
					)
				);
				load();
			} )
			.catch( fail );
	};

	// A folder being dragged can go into another folder of its own space
	// (shared, or the person's own), or to the top of it, but not into itself
	// or a folder inside it.
	const canMoveTo = ( folder, owner ) => {
		const moving = find( dragging );
		if (
			! moving ||
			moving.owner !== ( folder ? find( folder )?.owner : owner )
		) {
			return false;
		}
		for ( let id = folder; id; id = find( id )?.parent ) {
			if ( id === moving.id ) {
				return false;
			}
		}
		return ! folder || ! ( find( folder )?.root && ! moving.owner );
	};

	const moveFolder = ( parent ) => {
		const id = dragging;
		setDragging( null );
		apiFetch( {
			path: `/galerio-media/v1/folders/${ id }/move`,
			method: 'POST',
			data: { parent },
		} )
			.then( () => {
				if ( parent ) {
					const next = new Set( expanded ).add( parent );
					setExpanded( next );
					saveExpanded( next );
				}
				setMessage( __( 'Folder moved.', 'galerio-media' ) );
				load();
			} )
			.catch( fail );
	};

	// Where files, and folders, can be dropped. `files: false` takes folders only.
	const dropProps = ( key, folder, owner, { files = true } = {} ) => ( {
		onDragOver: ( event ) => {
			const types = event.dataTransfer.types;
			if (
				( files && types.includes( DRAG_TYPE ) ) ||
				( types.includes( FOLDER_TYPE ) && canMoveTo( folder, owner ) )
			) {
				event.preventDefault();
				event.dataTransfer.dropEffect = 'move';
				setTarget( key );
			}
		},
		onDragLeave: () => setTarget( null ),
		onDrop: ( event ) => {
			if ( event.dataTransfer.types.includes( FOLDER_TYPE ) ) {
				event.preventDefault();
				setTarget( null );
				moveFolder( folder );
			} else {
				drop( event, folder, owner );
			}
		},
	} );

	const item = ( key, label, value, extra = {} ) => (
		<li key={ key }>
			<Button
				className={ [
					'galerio-folders__item',
					chosen === value ? 'is-chosen' : '',
					target === key ? 'is-drop-target' : '',
				].join( ' ' ) }
				aria-current={ chosen === value ? 'true' : undefined }
				onClick={ () => choose( value ) }
				{ ...extra }
			>
				{ label }
			</Button>
		</li>
	);

	// The hidden root of "My folders" is that section's heading, so it isn't
	// listed among the shared folders.
	const branch = ( parent, depth ) =>
		( byParent.get( parent ) || [] )
			.filter( ( folder ) => ! folder.root )
			.map( ( folder ) => {
				const children = byParent.has( folder.id );
				const open = expanded.has( folder.id );

				return (
					<li key={ folder.id }>
						<div
							className={ [
								'galerio-folders__row',
								folder.owner ? 'is-personal' : '',
								chosen === folder.id ? 'is-chosen' : '',
								target === folder.id ? 'is-drop-target' : '',
							].join( ' ' ) }
							style={ { '--depth': depth } }
							{ ...dropProps(
								folder.id,
								folder.id,
								folder.owner
							) }
							draggable={ folder.canEdit }
							onDragStart={ ( event ) => {
								event.dataTransfer.setData(
									FOLDER_TYPE,
									String( folder.id )
								);
								event.dataTransfer.effectAllowed = 'move';
								setDragging( folder.id );
							} }
							onDragEnd={ () => {
								setDragging( null );
								setTarget( null );
							} }
						>
							{ children ? (
								<Button
									className="galerio-folders__toggle"
									icon={
										open
											? 'arrow-down-alt2'
											: 'arrow-right-alt2'
									}
									label={
										open
											? __(
													'Close folder',
													'galerio-media'
												)
											: __(
													'Open folder',
													'galerio-media'
												)
									}
									aria-expanded={ open }
									onClick={ () => toggle( folder.id ) }
									size="small"
								/>
							) : (
								<span className="galerio-folders__spacer" />
							) }
							<Button
								className="galerio-folders__item"
								title={ folder.name }
								aria-current={
									chosen === folder.id ? 'true' : undefined
								}
								onClick={ () => choose( folder.id ) }
							>
								<span
									className={ `galerio-folders__icon dashicons dashicons-${
										open && children
											? 'open-folder'
											: 'category'
									}` }
									aria-hidden="true"
								/>
								<span className="galerio-folders__name">
									{ folder.name }
								</span>
								{ folder.count !== null && (
									<span className="galerio-folders__count">
										{ folder.count }
									</span>
								) }
							</Button>
						</div>
						{ addingInside === folder.id && (
							<div
								className="galerio-folders__inline"
								style={ { '--depth': depth + 1 } }
							>
								{ editingForm }
							</div>
						) }
						{ children && open && (
							<ul>{ branch( folder.id, depth + 1 ) }</ul>
						) }
					</li>
				);
			} );

	const canAdd = ( personal ) =>
		personal ? data.personal : data.canAddShared;
	// A new subfolder's name is typed under its folder; other names under the
	// space's heading.
	const inside = editing?.mode === 'add' && find( editing.parent );
	const addingInside = inside && ! inside.root ? inside.id : 0;
	const editingForm = editing && (
		<form className="galerio-folders__form" onSubmit={ save }>
			{ inside && ! inside.root && (
				<p className="galerio-folders__hint">
					{ sprintf(
						/* translators: %s: folder name. */
						__( 'New folder inside “%s”', 'galerio-media' ),
						inside.name
					) }
				</p>
			) }
			<input
				type="text"
				ref={ field }
				aria-label={
					editing.mode === 'add'
						? __( 'New folder name', 'galerio-media' )
						: __( 'Folder name', 'galerio-media' )
				}
				value={ editing.value }
				onChange={ ( event ) =>
					setEditing( { ...editing, value: event.target.value } )
				}
				onKeyDown={ ( event ) =>
					event.key === 'Escape' && setEditing( null )
				}
			/>
			<Button variant="primary" type="submit" size="small">
				{ __( 'Save', 'galerio-media' ) }
			</Button>
			<Button
				variant="tertiary"
				size="small"
				onClick={ () => setEditing( null ) }
			>
				{ __( 'Cancel', 'galerio-media' ) }
			</Button>
		</form>
	);

	return (
		<nav
			className="galerio-folders"
			aria-label={ __( 'Folders', 'galerio-media' ) }
		>
			<ul className="galerio-folders__fixed">
				{ item( 'all', __( 'All files', 'galerio-media' ), 0 ) }
				{ item(
					'unfiled',
					__( 'Not in a folder', 'galerio-media' ),
					'unfiled',
					dropProps( 'unfiled', 0, 0 )
				) }
			</ul>

			{ /* A person's own folders come first, above the shared ones. */ }
			{ data.personal && (
				<>
					<div
						className={ [
							'galerio-folders__heading',
							target === 'mine' ? 'is-drop-target' : '',
						].join( ' ' ) }
						{ ...dropProps( 'mine', 0, data.user ) }
					>
						<h2>{ __( 'My folders', 'galerio-media' ) }</h2>
						<Button
							size="small"
							variant="tertiary"
							onClick={ () => startAdd( true ) }
						>
							{ __( 'New', 'galerio-media' ) }
						</Button>
					</div>
					{ editing &&
						! addingInside &&
						editing.personal &&
						editingForm }
					<ul className="galerio-folders__tree is-personal">
						{ root ? branch( root.id, 0 ) : null }
					</ul>
				</>
			) }

			<div
				className={ [
					'galerio-folders__heading',
					target === 'shared' ? 'is-drop-target' : '',
				].join( ' ' ) }
				{ ...dropProps( 'shared', 0, 0, { files: false } ) }
			>
				<h2>{ __( 'Folders', 'galerio-media' ) }</h2>
				{ canAdd( false ) && (
					<Button
						size="small"
						variant="tertiary"
						onClick={ () => startAdd( false ) }
					>
						{ __( 'New', 'galerio-media' ) }
					</Button>
				) }
			</div>
			{ editing && ! addingInside && ! editing.personal && editingForm }
			<ul className="galerio-folders__tree">{ branch( 0, 0 ) }</ul>

			{ current && ( current.canEdit || current.canDelete ) && (
				<div className="galerio-folders__actions">
					{ canAdd( !! current.owner ) && (
						<Button
							size="small"
							variant="secondary"
							onClick={ () =>
								startAdd( !! current.owner, current.id )
							}
						>
							{ __( 'New folder inside', 'galerio-media' ) }
						</Button>
					) }
					{ current.canEdit && (
						<Button
							size="small"
							variant="secondary"
							onClick={ () =>
								setEditing( {
									mode: 'rename',
									id: current.id,
									personal: !! current.owner,
									value: current.name,
								} )
							}
						>
							{ __( 'Rename', 'galerio-media' ) }
						</Button>
					) }
					{ current.canDelete && (
						<Button
							size="small"
							variant="secondary"
							isDestructive
							onClick={ remove }
						>
							{ __( 'Delete', 'galerio-media' ) }
						</Button>
					) }
				</div>
			) }

			<p className="galerio-folders__message" aria-live="polite">
				{ message }
			</p>
		</nav>
	);
}

/**
 * Let files in the browser be dragged onto folders: one, or all of those
 * selected when the dragged one is among them.
 *
 * @param {Object} browser Attachments browser view.
 */
function makeDraggable( browser ) {
	const mark = () =>
		browser.el
			.querySelectorAll( 'li.attachment:not([draggable])' )
			.forEach( ( item ) => item.setAttribute( 'draggable', 'true' ) );

	new window.MutationObserver( mark ).observe( browser.el, {
		childList: true,
		subtree: true,
	} );
	mark();

	browser.el.addEventListener( 'dragstart', ( event ) => {
		const item = event.target.closest?.( 'li.attachment' );
		if ( ! item ) {
			return;
		}

		const id = Number( item.dataset.id );
		const selection = browser.options.selection;
		const ids =
			selection && selection.length > 1 && selection.get( id )
				? selection.pluck( 'id' )
				: [ id ];

		event.dataTransfer.setData( DRAG_TYPE, JSON.stringify( ids ) );
		event.dataTransfer.effectAllowed = 'move';
	} );

	// WordPress's uploader takes any drag over the library for files to upload
	// and shows its "Drop files to upload" cover, which can stay up after the
	// drop. Dragging files or folders to folders isn't uploading, so it stops
	// short of it.
	browser.el.addEventListener( 'dragover', ( event ) => {
		const types = event.dataTransfer?.types || [];
		if ( types.includes( DRAG_TYPE ) || types.includes( FOLDER_TYPE ) ) {
			event.stopPropagation();
		}
	} );
}

const media = window.wp?.media;
const Browser = media?.view?.AttachmentsBrowser;

if ( Browser ) {
	const render = Browser.prototype.render;
	const remove = Browser.prototype.remove;

	Browser.prototype.render = function () {
		render.apply( this, arguments );

		if ( ! this.galerioFolders ) {
			const container = document.createElement( 'div' );
			container.className = 'galerio-folders-column';
			this.el.prepend( container );
			this.el.classList.add( 'has-galerio-folders' );
			this.galerioFolders = createRoot( container );
			this.galerioFolders.render(
				<FolderTree lib={ library( this ) } />
			);
			makeDraggable( this );
		}

		return this;
	};

	Browser.prototype.remove = function () {
		this.galerioFolders?.unmount();
		return remove.apply( this, arguments );
	};
}

/**
 * Let rows of the list view be dragged onto folders: one, or all of those
 * ticked when the dragged one is among them.
 *
 * @param {HTMLElement} list The table's body.
 */
function makeRowsDraggable( list ) {
	list.querySelectorAll( 'tr[id^="post-"]' ).forEach( ( row ) =>
		row.setAttribute( 'draggable', 'true' )
	);

	list.addEventListener( 'dragstart', ( event ) => {
		const row = event.target.closest?.( 'tr[id^="post-"]' );
		if ( ! row ) {
			return;
		}

		const id = Number( row.id.slice( 5 ) );
		const ticked = Array.from(
			list.querySelectorAll( 'input[name="media[]"]:checked' ),
			( box ) => Number( box.value )
		);
		const ids =
			ticked.length > 1 && ticked.includes( id ) ? ticked : [ id ];

		event.dataTransfer.setData( DRAG_TYPE, JSON.stringify( ids ) );
		event.dataTransfer.effectAllowed = 'move';
	} );
}

/**
 * The tree beside the Media Library's list view.
 */
function mountListView() {
	const form = document.getElementById( 'posts-filter' );
	if ( ! document.body.classList.contains( 'upload-php' ) || ! form ) {
		return;
	}

	const lib = listLibrary();
	const chosen = lib.chosen();

	// The tree takes over from the folder dropdown; a hidden field keeps the
	// folder when the list's other filters are applied.
	const select = document.getElementById( 'galerio_folder' );
	if ( select ) {
		form.querySelector( 'label[for="galerio_folder"]' )?.remove();
		const keep = document.createElement( 'input' );
		keep.type = 'hidden';
		keep.name = 'galerio_folder';
		keep.value = chosen ? String( chosen ) : '';
		select.replaceWith( keep );
	}

	// "Add New Media File" uploads into the open folder.
	if ( typeof chosen === 'number' && chosen ) {
		document
			.querySelectorAll( '.wrap .page-title-action' )
			.forEach( ( link ) => {
				const url = new URL( link.href );
				url.searchParams.set( 'galerio_folder', chosen );
				link.href = url.toString();
			} );
	}

	const layout = document.createElement( 'div' );
	layout.className = 'galerio-folders-layout';
	const column = document.createElement( 'div' );
	column.className = 'galerio-folders-column';
	form.before( layout );
	layout.append( column, form );

	createRoot( column ).render( <FolderTree lib={ lib } /> );

	const list = document.getElementById( 'the-list' );
	if ( list ) {
		makeRowsDraggable( list );
	}
}

// The grid view has no list form, so this only acts on the list view.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mountListView );
} else {
	mountListView();
}
