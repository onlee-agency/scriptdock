/**
 * The Snippets screen (S03): header, status pills, toolbar, the table (or
 * cards on phones), bulk actions, pagination, the trash, the needs-review
 * view, the quick view and Manage tags.
 */
import {
	Fragment,
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { useMediaQuery } from '@wordpress/compose';
import { __, _n, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import {
	BulkBar,
	Button,
	ConfirmDialog,
	Modal,
	Pagination,
	Select,
	TokenInput,
	copyText,
	useToast,
} from '../components';
import * as api from './api';
import { initialQuery, isNarrowed, queryUrl, withoutFilters } from './query';
import SnippetTable from './snippet-table';
import SnippetCards from './snippet-cards';
import Toolbar, { facets as buildFacets } from './toolbar';
import TrashView from './trash-view';
import QuickView from './quick-view';
import TagsModal from './tags-modal';
import {
	EmptyList,
	EmptyView,
	Header,
	LoadError,
	NoResults,
	ReviewBanner,
	StatusPills,
} from './parts';

const PER_PAGE_CHOICES = [ 20, 50, 100 ];

/**
 * @param {Object} props      Props.
 * @param {Object} props.boot What the server printed for the first view.
 * @return {Element} The screen.
 */
export default function App( { boot } ) {
	const toast = useToast();
	const [ query, setQuery ] = useState( () => initialQuery( boot.query ) );
	const [ perPage, setPerPage ] = useState( boot.perPage );
	const [ density, setDensity ] = useState( boot.density );
	const [ hidden, setHidden ] = useState( boot.hiddenColumns || [] );
	const [ data, setData ] = useState( boot.initial );
	const [ loading, setLoading ] = useState( ! boot.initial );
	const [ loadError, setLoadError ] = useState( '' );
	const [ tags, setTags ] = useState( boot.tags || [] );
	const [ selected, setSelected ] = useState( [] );
	const [ busy, setBusy ] = useState( {} );
	const [ quick, setQuick ] = useState( null ); // { item, changes }.
	const [ tagsOpen, setTagsOpen ] = useState( !! boot.openTags );
	const [ confirm, setConfirm ] = useState( null );
	const [ tagging, setTagging ] = useState( false );

	// Closing Manage tags leaves focus on the Tags filter it is opened from,
	// also when the page opened it straight away and nothing had focus.
	const tagsWasOpen = useRef( tagsOpen );
	useEffect( () => {
		if ( tagsWasOpen.current && ! tagsOpen ) {
			document
				.querySelector( '.sd-filter-button[data-facet="tag"]' )
				?.focus();
		}
		tagsWasOpen.current = tagsOpen;
	}, [ tagsOpen ] );

	const phone = useMediaQuery( '(max-width: 782px)' );
	const tablet = useMediaQuery( '(max-width: 1024px)' );

	const counts = data ? data.counts : { all: 0, trash: 0 };
	const items = data ? data.items : [];
	const skipFirst = useRef( !! boot.initial );
	const request = useRef( null );

	// Load the list whenever the query or page size changes.
	const load = useCallback(
		( { quiet = false } = {} ) => {
			if ( request.current ) {
				request.current.abort();
			}
			const controller = new window.AbortController();
			request.current = controller;
			if ( ! quiet ) {
				setLoading( true );
			}
			setLoadError( '' );
			return api
				.fetchList( query, perPage, controller.signal )
				.then( ( response ) => {
					setData( response );
					setLoading( false );
					return response;
				} )
				.catch( ( error ) => {
					if ( api.wasAborted( error ) ) {
						return null;
					}
					setLoading( false );
					setLoadError( api.errorMessage( error ) );
					return null;
				} );
		},
		[ query, perPage ]
	);

	useEffect( () => {
		if ( skipFirst.current ) {
			skipFirst.current = false;
			return;
		}
		load();
	}, [ load ] );

	// The URL follows the query, so a filtered view can be bookmarked.
	useEffect( () => {
		window.history.replaceState(
			null,
			'',
			queryUrl( boot.urls.list, query )
		);
	}, [ query, boot.urls.list ] );

	// Screen Options column toggles: WordPress saves them; the table follows.
	useEffect( () => {
		const onToggle = ( event ) => {
			const box = event.target;
			if (
				! box.classList ||
				! box.classList.contains( 'hide-column-tog' )
			) {
				return;
			}
			const column = box.value;
			setHidden( ( list ) =>
				box.checked
					? list.filter( ( key ) => key !== column )
					: [ ...new Set( [ ...list, column ] ) ]
			);
		};
		document.addEventListener( 'change', onToggle );
		return () => document.removeEventListener( 'change', onToggle );
	}, [] );

	const facets = useMemo(
		() => buildFacets( boot, tags, data ? data.months : [] ),
		[ boot, tags, data ]
	);

	/**
	 * Changes the query; any change but the page goes back to page one and
	 * clears the selection.
	 *
	 * @param {Object} change Query change.
	 */
	const changeQuery = useCallback( ( change ) => {
		setQuery( ( current ) => ( {
			...current,
			...change,
			page: change.page || 1,
		} ) );
		if ( ! change.page ) {
			setSelected( [] );
		}
	}, [] );

	/**
	 * Puts a changed row and fresh counts from an action's response into
	 * the list, without reloading it.
	 *
	 * @param {Object} response Response: item and counts.
	 */
	const applyItem = ( response ) => {
		setData( ( current ) =>
			current
				? {
						...current,
						counts: response.counts || current.counts,
						items: response.item
							? current.items.map( ( row ) =>
									row.id === response.item.id
										? response.item
										: row
								)
							: current.items,
					}
				: current
		);
	};

	const setRowBusy = ( id, value ) =>
		setBusy( ( current ) => {
			const next = { ...current };
			if ( value ) {
				next[ id ] = value;
			} else {
				delete next[ id ];
			}
			return next;
		} );

	/**
	 * What a failed switch-on can offer: Fix it for code errors, Review for
	 * changed code, Retry for everything else.
	 *
	 * @param {Object}   item  Snippet.
	 * @param {Object}   error Error.
	 * @param {Function} retry Tries again.
	 * @return {Object|undefined} Toast action.
	 */
	const failureAction = ( item, error, retry ) => {
		const code = error && error.code;
		if (
			[
				'scriptdock_test_fatal',
				'scriptdock_test_error',
				'scriptdock_syntax',
			].includes( code )
		) {
			return {
				label: __( 'Fix it', 'scriptdock' ),
				onClick: () => window.location.assign( item.edit_url ),
			};
		}
		if ( code === 'scriptdock_untrusted' ) {
			return {
				label: __( 'Review', 'scriptdock' ),
				onClick: () => setQuick( { item, changes: true } ),
			};
		}
		if ( code === 'scriptdock_forbidden' ) {
			return undefined;
		}
		return { label: __( 'Retry', 'scriptdock' ), onClick: retry };
	};

	// Switching on or off: the switch moves at once and goes back on failure.
	const toggle = ( item, on ) => {
		setRowBusy( item.id, 'toggle' );
		setData( ( current ) => ( {
			...current,
			items: current.items.map( ( row ) =>
				row.id === item.id ? { ...row, active: on } : row
			),
		} ) );
		api.act( item.id, on ? 'activate' : 'deactivate' )
			.then( ( response ) => {
				applyItem( response );
				speak(
					on
						? sprintf(
								/* translators: %s: snippet title. */
								__( '%s switched on.', 'scriptdock' ),
								item.title
							)
						: sprintf(
								/* translators: %s: snippet title. */
								__( '%s switched off.', 'scriptdock' ),
								item.title
							)
				);
				// A snippet that disappears from this view leaves the page on the next load.
				if ( [ 'active', 'inactive' ].includes( query.view ) ) {
					load( { quiet: true } );
				}
			} )
			.catch( ( error ) => {
				setData( ( current ) => ( {
					...current,
					items: current.items.map( ( row ) =>
						row.id === item.id
							? { ...row, active: item.active }
							: row
					),
				} ) );
				const message = on
					? sprintf(
							/* translators: 1: snippet title, 2: why. */
							__(
								'Couldn’t switch on “%1$s”: %2$s',
								'scriptdock'
							),
							item.title,
							api.errorMessage( error )
						)
					: sprintf(
							/* translators: 1: snippet title, 2: why. */
							__(
								'Couldn’t switch off “%1$s”: %2$s',
								'scriptdock'
							),
							item.title,
							api.errorMessage( error )
						);
				toast.show( {
					tone: 'error',
					message,
					action: failureAction( item, error, () =>
						toggle( item, on )
					),
					duration: 8000,
				} );
				// A failed trial records an error on the snippet; show it.
				load( { quiet: true } );
			} )
			.finally( () => setRowBusy( item.id, null ) );
	};

	const exportSome = ( ids ) =>
		api
			.exportSnippets( ids )
			.then( ( response ) => {
				api.download( response.filename, response.data );
				speak( __( 'Export downloaded.', 'scriptdock' ) );
			} )
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
				} )
			);

	const duplicate = ( item ) =>
		api
			.act( item.id, 'duplicate' )
			.then( ( response ) => {
				load( { quiet: true } );
				toast.show( {
					message: __(
						'Duplicated. The copy is switched off.',
						'scriptdock'
					),
					action: response.item && {
						label: __( 'Open copy', 'scriptdock' ),
						onClick: () =>
							window.location.assign( response.item.edit_url ),
					},
				} );
			} )
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
				} )
			);

	const untrash = ( item ) => {
		setRowBusy( item.id, 'untrash' );
		return api
			.act( item.id, 'untrash' )
			.then( () => {
				load( { quiet: true } );
				speak(
					sprintf(
						/* translators: %s: snippet title. */
						__( '%s restored. It is switched off.', 'scriptdock' ),
						item.title
					)
				);
			} )
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
				} )
			)
			.finally( () => setRowBusy( item.id, null ) );
	};

	// Trash is reversible, so it happens at once with an Undo.
	const trash = ( item ) =>
		api
			.trash( item.id )
			.then( () => {
				setQuick( null );
				load( { quiet: true } );
				toast.show( {
					message: sprintf(
						/* translators: %s: snippet title. */
						__( '“%s” moved to the trash.', 'scriptdock' ),
						item.title
					),
					action: {
						label: __( 'Undo', 'scriptdock' ),
						onClick: () => untrash( item ),
					},
				} );
			} )
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
				} )
			);

	const approve = ( item ) =>
		api
			.act( item.id, 'approve' )
			.then( ( response ) => {
				applyItem( response );
				setQuick( null );
				load( { quiet: true } );
				toast.show( {
					message: sprintf(
						/* translators: %s: snippet title. */
						__(
							'“%s” approved. Its code is trusted again.',
							'scriptdock'
						),
						item.title
					),
				} );
			} )
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
					action: failureAction( item, error, () => approve( item ) ),
				} )
			);

	const copyShortcode = ( item ) =>
		copyText( item.shortcode )
			.then( () =>
				toast.show( {
					message: __( 'Shortcode copied.', 'scriptdock' ),
				} )
			)
			.catch( () =>
				toast.show( {
					tone: 'error',
					message: __(
						'Couldn’t copy. Select the shortcode and copy it yourself.',
						'scriptdock'
					),
				} )
			);

	const menuItems = ( item ) => [
		{
			label: __( 'Edit', 'scriptdock' ),
			icon: 'edit',
			href: item.edit_url,
		},
		{
			label: __( 'Duplicate', 'scriptdock' ),
			icon: 'copy',
			disabled: ! item.can_edit,
			reason: boot.phpReason,
			onClick: () => duplicate( item ),
		},
		{
			label: __( 'Export', 'scriptdock' ),
			icon: 'download',
			onClick: () => exportSome( [ item.id ] ),
		},
		{
			label: __( 'History', 'scriptdock' ),
			icon: 'history',
			onClick: () => setQuick( { item, changes: false } ),
		},
		{
			label: __( 'Copy shortcode', 'scriptdock' ),
			icon: 'shortcode',
			disabled: ! item.shortcode,
			reason: __(
				'Only snippets placed with a shortcode or block have one.',
				'scriptdock'
			),
			onClick: () => copyShortcode( item ),
		},
		{ separator: true },
		{
			label: __( 'Move to trash', 'scriptdock' ),
			icon: 'trash',
			danger: true,
			onClick: () => trash( item ),
		},
	];

	// Bulk actions: each snippet succeeds or fails on its own.
	const runBulk = ( action, extra = {} ) => {
		const ids = [ ...selected ];
		return api
			.bulk( action, ids, extra )
			.then( ( response ) => {
				setSelected( [] );
				load( { quiet: true } );
				const done = response.done.length;
				const message = bulkMessage( action, done );
				toast.show( {
					message,
					action:
						action === 'trash' && done
							? {
									label: __( 'Undo', 'scriptdock' ),
									onClick: () =>
										api
											.bulk( 'untrash', response.done )
											.then( () =>
												load( { quiet: true } )
											),
								}
							: undefined,
				} );
				response.failed.forEach( ( failure ) =>
					toast.show( {
						tone: 'error',
						message: sprintf(
							/* translators: 1: snippet title, 2: why. */
							__( '“%1$s”: %2$s', 'scriptdock' ),
							failure.title,
							failure.message
						),
						duration: 8000,
					} )
				);
				if ( action === 'add_tag' ) {
					api.tagsApi.list().then( setTags );
				}
			} )
			.catch( ( error ) => {
				load( { quiet: true } );
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
					duration: 8000,
				} );
			} );
	};

	const emptyTrash = () => {
		setConfirm( null );
		api.emptyTrash()
			.then( ( response ) => {
				load( { quiet: true } );
				toast.show( {
					message: sprintf(
						/* translators: %d: number of snippets deleted. */
						_n(
							'%d snippet deleted for good.',
							'%d snippets deleted for good.',
							response.deleted.length,
							'scriptdock'
						),
						response.deleted.length
					),
				} );
			} )
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
				} )
			);
	};

	const deleteForGood = ( item ) => {
		setConfirm( null );
		api.deleteForGood( item.id )
			.then( () => {
				load( { quiet: true } );
				speak(
					sprintf(
						/* translators: %s: snippet title. */
						__( '%s deleted for good.', 'scriptdock' ),
						item.title
					)
				);
			} )
			.catch( ( error ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( error ),
				} )
			);
	};

	const savePerPage = ( value ) => {
		setPerPage( value );
		changeQuery( {} );
		api.savePreferences( { per_page: value } ).catch( () => {} );
	};

	const saveDensity = ( value ) => {
		setDensity( value );
		api.savePreferences( { density: value } ).catch( () => {} );
	};

	const clearFilters = () =>
		setQuery( ( current ) => withoutFilters( current ) );

	const noResultsHint = () => {
		const names = [];
		facets.forEach( ( facet ) => {
			const value = query[ facet.key ];
			if (
				( Array.isArray( value ) && value.length ) ||
				( ! Array.isArray( value ) && value )
			) {
				names.push( facet.label );
			}
		} );
		if ( query.search && ! names.length ) {
			return __(
				'Try fewer words, or search for part of the code.',
				'scriptdock'
			);
		}
		if ( names.length === 1 && query.search ) {
			return sprintf(
				/* translators: %s: filter name, for example "Location". */
				__( 'Try fewer words, or clear the %s filter.', 'scriptdock' ),
				names[ 0 ]
			);
		}
		if ( names.length === 1 ) {
			return sprintf(
				/* translators: %s: filter name, for example "Location". */
				__( 'Clear the %s filter to see more.', 'scriptdock' ),
				names[ 0 ]
			);
		}
		return __( 'Clear a filter or two to see more.', 'scriptdock' );
	};

	const trashing = query.view === 'trash';
	const reviewing = query.view === 'review';
	const nothingAtAll = counts.all === 0 && counts.trash === 0 && ! loading;
	const total = data ? data.total : 0;

	let body;
	if ( loadError && ! data ) {
		body = <LoadError message={ loadError } onRetry={ () => load() } />;
	} else if ( nothingAtAll ) {
		body = <EmptyList urls={ boot.urls } />;
	} else if ( trashing ) {
		body = (
			<TrashView
				items={ items }
				total={ counts.trash }
				loading={ loading }
				days={ boot.trashDays }
				busy={ busy }
				onRestore={ untrash }
				onDelete={ ( item ) => setConfirm( { kind: 'delete', item } ) }
				onEmpty={ () => setConfirm( { kind: 'empty' } ) }
			/>
		);
	} else if ( ! loading && ! items.length && isNarrowed( query ) ) {
		body = (
			<NoResults
				search={ query.search }
				hint={ noResultsHint() }
				onClear={ clearFilters }
			/>
		);
	} else if ( ! loading && ! items.length ) {
		body = <EmptyView view={ query.view } />;
	} else {
		const shared = {
			items,
			busy,
			onToggle: toggle,
			onBlocked: ( item ) =>
				setQuick( { item, changes: ! item.trusted } ),
			onQuickView: ( item ) => setQuick( { item, changes: false } ),
			menuItems,
		};
		const pagination = (
			<Pagination
				page={ data ? data.page : 1 }
				perPage={ perPage }
				total={ total }
				onChange={ ( page ) => changeQuery( { page } ) }
			>
				<span className="sd-snippets__per-page">
					<Select
						label={ __( 'Per page', 'scriptdock' ) }
						value={ String( perPage ) }
						// Screen Options allows any number; show it too.
						options={ [
							...new Set( [ ...PER_PAGE_CHOICES, perPage ] ),
						]
							.sort( ( a, b ) => a - b )
							.map( ( value ) => ( {
								value: String( value ),
								label: String( value ),
							} ) ) }
						onChange={ ( value ) => savePerPage( Number( value ) ) }
					/>
				</span>
			</Pagination>
		);
		body = phone ? (
			<>
				<SnippetCards { ...shared } loading={ loading } />
				{ pagination }
			</>
		) : (
			<div className="sd-snippets__table">
				<SnippetTable
					{ ...shared }
					loading={ loading }
					skeletons={ Math.min( perPage, 6 ) }
					density={ density }
					hidden={ hidden }
					folded={ tablet ? [ 'badges', 'priority' ] : [] }
					reviewing={ reviewing }
					query={ query }
					onSort={ ( orderby, order ) =>
						changeQuery( { orderby, order } )
					}
					selection={ {
						selected,
						onChange: setSelected,
						label: ( item ) =>
							sprintf(
								/* translators: %s: snippet title. */
								__( 'Select %s', 'scriptdock' ),
								item.title
							),
					} }
				/>
				{ pagination }
			</div>
		);
	}

	return (
		<div className={ `sd-snippets__screen is-${ density }` }>
			<Header count={ counts.all } urls={ boot.urls } small={ tablet } />
			{ ! nothingAtAll && (
				<>
					<StatusPills
						counts={ counts }
						query={ query }
						base={ boot.urls.list }
						short={ phone }
						onSelect={ ( view ) => changeQuery( { view } ) }
					/>
					{ ! trashing && (
						<Toolbar
							query={ query }
							onQuery={ changeQuery }
							facets={ facets }
							compact={ tablet }
							density={ density }
							onDensity={ saveDensity }
							onManageTags={ () => setTagsOpen( true ) }
							total={ total }
						/>
					) }
					{ reviewing && counts.review > 0 && (
						<ReviewBanner
							count={ counts.review }
							canApprove={ items.every(
								( item ) => item.trusted || item.can_edit
							) }
							onApprove={ () =>
								setConfirm( { kind: 'approve' } )
							}
						/>
					) }
					{ loadError && data && (
						<LoadError
							message={ loadError }
							onRetry={ () => load() }
						/>
					) }
				</>
			) }
			<div
				className="sd-snippets__body"
				aria-busy={ loading || undefined }
			>
				{ body }
			</div>

			{ selected.length > 0 && ! trashing && (
				<div className="sd-snippets__bulk">
					<BulkBar
						count={ selected.length }
						onClear={ () => setSelected( [] ) }
					>
						<Button
							variant="on-ink"
							onClick={ () => runBulk( 'activate' ) }
						>
							{ __( 'Activate', 'scriptdock' ) }
						</Button>
						<Button
							variant="on-ink"
							onClick={ () => runBulk( 'deactivate' ) }
						>
							{ __( 'Deactivate', 'scriptdock' ) }
						</Button>
						{ reviewing && (
							<Button
								variant="on-ink"
								onClick={ () => runBulk( 'approve' ) }
							>
								{ __( 'Approve', 'scriptdock' ) }
							</Button>
						) }
						<Button
							variant="on-ink"
							onClick={ () => setTagging( true ) }
						>
							{ __( 'Add tag', 'scriptdock' ) }
						</Button>
						<Button
							variant="on-ink"
							onClick={ () => exportSome( selected ) }
						>
							{ __( 'Export', 'scriptdock' ) }
						</Button>
						<Button
							variant="on-ink"
							className="sd-button--danger-text"
							onClick={ () => runBulk( 'trash' ) }
						>
							{ __( 'Move to trash', 'scriptdock' ) }
						</Button>
					</BulkBar>
				</div>
			) }

			{ quick && (
				<QuickView
					item={
						items.find( ( row ) => row.id === quick.item.id ) ||
						quick.item
					}
					showChanges={ quick.changes }
					onClose={ () => setQuick( null ) }
					onApprove={ approve }
					onDuplicate={ duplicate }
					onExport={ ( item ) => exportSome( [ item.id ] ) }
					onTrash={ trash }
				/>
			) }

			<TagsModal
				open={ tagsOpen }
				tags={ tags }
				onTags={ ( list ) => {
					setTags( list );
					load( { quiet: true } );
				} }
				onClose={ () => setTagsOpen( false ) }
			/>

			<AddTag
				open={ tagging }
				count={ selected.length }
				tags={ tags }
				onClose={ () => setTagging( false ) }
				onAdd={ ( name ) => {
					setTagging( false );
					runBulk( 'add_tag', { tag: name } );
				} }
			/>

			<Confirmations
				confirm={ confirm }
				counts={ counts }
				onCancel={ () => setConfirm( null ) }
				onEmpty={ emptyTrash }
				onDelete={ deleteForGood }
				onApproveAll={ () => {
					setConfirm( null );
					api.bulk(
						'approve',
						items
							.filter( ( item ) => ! item.trusted )
							.map( ( item ) => item.id )
					)
						.then( ( response ) => {
							setSelected( [] );
							load( { quiet: true } );
							toast.show( {
								message: sprintf(
									/* translators: %d: number of snippets. */
									_n(
										'%d snippet approved.',
										'%d snippets approved.',
										response.done.length,
										'scriptdock'
									),
									response.done.length
								),
							} );
						} )
						.catch( ( error ) =>
							toast.show( {
								tone: 'error',
								message: api.errorMessage( error ),
							} )
						);
				} }
			/>
		</div>
	);
}

/**
 * What a bulk action did, in words.
 *
 * @param {string} action Action.
 * @param {number} done   Snippets it changed.
 * @return {string} Message.
 */
function bulkMessage( action, done ) {
	switch ( action ) {
		case 'activate':
			return sprintf(
				/* translators: %d: number of snippets. */
				_n(
					'%d snippet switched on.',
					'%d snippets switched on.',
					done,
					'scriptdock'
				),
				done
			);
		case 'deactivate':
			return sprintf(
				/* translators: %d: number of snippets. */
				_n(
					'%d snippet switched off.',
					'%d snippets switched off.',
					done,
					'scriptdock'
				),
				done
			);
		case 'approve':
			return sprintf(
				/* translators: %d: number of snippets. */
				_n(
					'%d snippet approved.',
					'%d snippets approved.',
					done,
					'scriptdock'
				),
				done
			);
		case 'trash':
			return sprintf(
				/* translators: %d: number of snippets. */
				_n(
					'%d snippet moved to the trash.',
					'%d snippets moved to the trash.',
					done,
					'scriptdock'
				),
				done
			);
		default:
			return sprintf(
				/* translators: %d: number of snippets. */
				_n(
					'Tag added to %d snippet.',
					'Tag added to %d snippets.',
					done,
					'scriptdock'
				),
				done
			);
	}
}

/**
 * Add a tag to the selected snippets: pick one or type a new one.
 *
 * @param {Object}   props         Props.
 * @param {boolean}  props.open    Open.
 * @param {number}   props.count   Snippets selected.
 * @param {Array}    props.tags    Existing tags.
 * @param {Function} props.onClose Closes it.
 * @param {Function} props.onAdd   Called with the tag name.
 * @return {Element} The dialog.
 */
function AddTag( { open, count, tags, onClose, onAdd } ) {
	const [ value, setValue ] = useState( [] );
	useEffect( () => {
		if ( open ) {
			setValue( [] );
		}
	}, [ open ] );
	const choice = value[ 0 ];
	return (
		<Modal
			open={ open }
			onClose={ onClose }
			size="sm"
			title={ sprintf(
				/* translators: %d: number of snippets. */
				_n(
					'Add a tag to %d snippet',
					'Add a tag to %d snippets',
					count,
					'scriptdock'
				),
				count
			) }
			footer={
				<>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Cancel', 'scriptdock' ) }
					</Button>
					<Button
						variant="primary"
						disabled={ ! choice }
						onClick={ () => onAdd( choice.label ) }
					>
						{ __( 'Add tag', 'scriptdock' ) }
					</Button>
				</>
			}
		>
			<TokenInput
				label={ __( 'Tag', 'scriptdock' ) }
				value={ value }
				onChange={ ( next ) => setValue( next.slice( -1 ) ) }
				suggestions={ tags.map( ( tag ) => ( {
					value: tag.id,
					label: tag.name,
				} ) ) }
				allowCreate
				placeholder={ __(
					'Pick a tag or type a new one',
					'scriptdock'
				) }
			/>
		</Modal>
	);
}

/**
 * The questions asked before something can't be undone.
 *
 * @param {Object}   props              Props.
 * @param {Object}   props.confirm      What to confirm: kind and item.
 * @param {Object}   props.counts       Counts.
 * @param {Function} props.onCancel     Cancels.
 * @param {Function} props.onEmpty      Empties the trash.
 * @param {Function} props.onDelete     Deletes one snippet for good.
 * @param {Function} props.onApproveAll Approves every changed snippet.
 * @return {Element} The dialog.
 */
function Confirmations( {
	confirm,
	counts,
	onCancel,
	onEmpty,
	onDelete,
	onApproveAll,
} ) {
	const kind = confirm && confirm.kind;
	const copy = {
		empty: {
			title: __( 'Empty the trash?', 'scriptdock' ),
			text: sprintf(
				/* translators: %d: number of snippets. */
				_n(
					'%d snippet will be deleted for good. This can’t be undone.',
					'%d snippets will be deleted for good. This can’t be undone.',
					counts.trash || 0,
					'scriptdock'
				),
				counts.trash || 0
			),
			button: __( 'Empty trash', 'scriptdock' ),
			onConfirm: onEmpty,
		},
		delete: confirm &&
			confirm.item && {
				title: sprintf(
					/* translators: %s: snippet title. */
					__( 'Delete “%s” for good?', 'scriptdock' ),
					confirm.item.title
				),
				text: __(
					'Its code and its history go with it. This can’t be undone.',
					'scriptdock'
				),
				button: __( 'Delete permanently', 'scriptdock' ),
				onConfirm: () => onDelete( confirm.item ),
			},
		approve: {
			title: __( 'Approve every changed snippet?', 'scriptdock' ),
			text: __(
				'Their current code becomes trusted and paused snippets start running again. Only approve code you have compared and recognise.',
				'scriptdock'
			),
			button: __( 'Approve all', 'scriptdock' ),
			onConfirm: onApproveAll,
		},
	}[ kind ];

	return (
		<ConfirmDialog
			open={ !! copy }
			title={ copy ? copy.title : '' }
			onCancel={ onCancel }
			actions={
				copy && (
					<Fragment>
						<Button
							variant="danger-solid"
							onClick={ copy.onConfirm }
						>
							{ copy.button }
						</Button>
						<Button variant="tertiary" onClick={ onCancel }>
							{ __( 'Cancel', 'scriptdock' ) }
						</Button>
					</Fragment>
				)
			}
		>
			{ copy ? copy.text : '' }
		</ConfirmDialog>
	);
}
