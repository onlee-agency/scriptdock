/**
 * Manage tags (S14): every tag with how many snippets use it, rename in
 * place, delete with a confirmation, merge when a rename matches another
 * tag, and new tags. Replaces WordPress's tag screen for snippets.
 */
import { Fragment, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { Button, ConfirmDialog, IconButton, Modal } from '../components';
import { errorMessage, tagsApi } from './api';

/**
 * @param {Object}   props         Props.
 * @param {boolean}  props.open    Open.
 * @param {Array}    props.tags    Tags with counts.
 * @param {Function} props.onTags  Called with the new tag list.
 * @param {Function} props.onClose Closes the dialog.
 * @return {Element} The dialog.
 */
export default function TagsModal( { open, tags, onTags, onClose } ) {
	const [ editing, setEditing ] = useState( null ); // Tag ID, or 'new'.
	const [ name, setName ] = useState( '' );
	const [ problem, setProblem ] = useState( null ); // { message, mergeInto }.
	const [ saving, setSaving ] = useState( false );
	const [ deleting, setDeleting ] = useState( null );
	const input = useRef();

	useEffect( () => {
		if ( editing !== null && input.current ) {
			input.current.focus();
			input.current.select();
		}
	}, [ editing ] );

	const refresh = () => tagsApi.list().then( onTags );

	const start = ( tag ) => {
		setEditing( tag ? tag.id : 'new' );
		setName( tag ? tag.name : '' );
		setProblem( null );
	};

	const cancel = () => {
		setEditing( null );
		setProblem( null );
	};

	const save = () => {
		const value = name.trim();
		if ( ! value ) {
			setProblem( { message: __( 'Enter a tag name.', 'scriptdock' ) } );
			return;
		}
		setSaving( true );
		const request =
			editing === 'new'
				? tagsApi.create( value )
				: tagsApi.rename( editing, value );
		request
			.then( () => refresh() )
			.then( () => {
				speak(
					editing === 'new'
						? __( 'Tag created.', 'scriptdock' )
						: __( 'Tag renamed.', 'scriptdock' )
				);
				setEditing( null );
			} )
			.catch( ( error ) => {
				const existing =
					error &&
					error.code === 'scriptdock_tag_exists' &&
					error.data &&
					tags.find( ( tag ) => tag.id === error.data.id );
				setProblem( {
					message: errorMessage( error ),
					mergeInto: editing !== 'new' && existing ? existing : null,
				} );
			} )
			.finally( () => setSaving( false ) );
	};

	const merge = ( into ) => {
		setSaving( true );
		tagsApi
			.merge( editing, into.id )
			.then( () => refresh() )
			.then( () => {
				speak( __( 'Tags merged.', 'scriptdock' ) );
				setEditing( null );
				setProblem( null );
			} )
			.catch( ( error ) =>
				setProblem( { message: errorMessage( error ) } )
			)
			.finally( () => setSaving( false ) );
	};

	const remove = () => {
		const tag = deleting;
		setDeleting( null );
		tagsApi
			.remove( tag.id )
			.then( () => refresh() )
			.then( () => speak( __( 'Tag deleted.', 'scriptdock' ) ) )
			.catch( ( error ) =>
				setProblem( { message: errorMessage( error ) } )
			);
	};

	const onKeyDown = ( event ) => {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			save();
		} else if ( event.key === 'Escape' ) {
			// Leave the dialog open; just stop editing.
			event.preventDefault();
			event.stopPropagation();
			cancel();
		}
	};

	const editor = (
		<li className="sd-tags-manager__row is-editing">
			<label className="sd-visually-hidden" htmlFor="sd-tag-name">
				{ editing === 'new'
					? __( 'New tag name', 'scriptdock' )
					: __( 'Tag name', 'scriptdock' ) }
			</label>
			<input
				ref={ input }
				id="sd-tag-name"
				type="text"
				className="sd-input"
				value={ name }
				onChange={ ( event ) => setName( event.target.value ) }
				onKeyDown={ onKeyDown }
				aria-invalid={ problem ? true : undefined }
				aria-describedby={ problem ? 'sd-tag-problem' : undefined }
				maxLength={ 190 }
			/>
			<span className="sd-tags-manager__buttons">
				<Button
					variant="primary"
					size="sm"
					onClick={ save }
					loading={ saving }
				>
					{ __( 'Save', 'scriptdock' ) }
				</Button>
				<Button variant="tertiary" size="sm" onClick={ cancel }>
					{ __( 'Cancel', 'scriptdock' ) }
				</Button>
			</span>
			{ problem && (
				<p
					id="sd-tag-problem"
					className="sd-tags-manager__problem"
					role="alert"
				>
					{ problem.message }
					{ problem.mergeInto && (
						<Button
							size="sm"
							onClick={ () => merge( problem.mergeInto ) }
						>
							{ sprintf(
								/* translators: %s: tag name. */
								__( 'Merge into “%s”', 'scriptdock' ),
								problem.mergeInto.name
							) }
						</Button>
					) }
				</p>
			) }
		</li>
	);

	return (
		<>
			<Modal
				open={ open }
				onClose={ onClose }
				title={ __( 'Manage tags', 'scriptdock' ) }
				subtitle={ __(
					'Tags are shared across all snippets.',
					'scriptdock'
				) }
				className="sd-tags-manager"
				footer={
					<>
						<Button
							className="sd-tags-manager__new"
							onClick={ () => start( null ) }
						>
							{ __( '+ New tag', 'scriptdock' ) }
						</Button>
						<Button variant="primary" onClick={ onClose }>
							{ __( 'Done', 'scriptdock' ) }
						</Button>
					</>
				}
			>
				{ tags.length === 0 && editing !== 'new' && (
					<p className="sd-tags-manager__empty">
						{ __(
							'No tags yet. Tags help you find snippets by purpose, such as analytics or design.',
							'scriptdock'
						) }
					</p>
				) }
				<ul className="sd-tags-manager__list">
					{ tags.map( ( tag ) =>
						editing === tag.id ? (
							<Fragment key={ tag.id }>{ editor }</Fragment>
						) : (
							<li key={ tag.id } className="sd-tags-manager__row">
								<span
									className="sd-tags-manager__name"
									dir="auto"
								>
									{ tag.name }
								</span>
								<span className="sd-tags-manager__count">
									{ tag.count
										? sprintf(
												/* translators: %d: number of snippets. */
												_n(
													'%d snippet',
													'%d snippets',
													tag.count,
													'scriptdock'
												),
												tag.count
											)
										: __( 'Not used', 'scriptdock' ) }
								</span>
								<span className="sd-tags-manager__buttons">
									<Button
										size="sm"
										onClick={ () => start( tag ) }
										aria-label={ sprintf(
											/* translators: %s: tag name. */
											__( 'Rename %s tag', 'scriptdock' ),
											tag.name
										) }
									>
										{ __( 'Rename', 'scriptdock' ) }
									</Button>
									<IconButton
										icon="trash"
										variant="ghost"
										label={ sprintf(
											/* translators: %s: tag name. */
											__( 'Delete %s tag', 'scriptdock' ),
											tag.name
										) }
										size="sm"
										onClick={ () => setDeleting( tag ) }
									/>
								</span>
							</li>
						)
					) }
					{ editing === 'new' && editor }
				</ul>
			</Modal>
			<ConfirmDialog
				open={ !! deleting }
				title={
					deleting
						? sprintf(
								/* translators: %s: tag name. */
								__( 'Delete the “%s” tag?', 'scriptdock' ),
								deleting.name
							)
						: ''
				}
				onCancel={ () => setDeleting( null ) }
				actions={
					<>
						<Button variant="danger-solid" onClick={ remove }>
							{ __( 'Delete tag', 'scriptdock' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ () => setDeleting( null ) }
						>
							{ __( 'Cancel', 'scriptdock' ) }
						</Button>
					</>
				}
			>
				{ deleting && deleting.count
					? sprintf(
							/* translators: %d: number of snippets. */
							_n(
								'It comes off %d snippet. The snippet itself stays.',
								'It comes off %d snippets. The snippets themselves stay.',
								deleting.count,
								'scriptdock'
							),
							deleting.count
						)
					: __( 'No snippet uses it.', 'scriptdock' ) }
			</ConfirmDialog>
		</>
	);
}
