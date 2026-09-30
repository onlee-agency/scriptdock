/**
 * S12: Import & Export. Bringing snippets in from another plugin or a file,
 * and taking them out again.
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Banner,
	Button,
	Card,
	FileDropzone,
	Icon,
	Modal,
	SwitchField,
	useToast,
} from '../components';
import * as api from './api';

/**
 * @param {Object} props      Props.
 * @param {Object} props.boot What can be imported, and where exports go.
 * @return {Element} The screen.
 */
export default function App( { boot } ) {
	const toast = useToast();
	const [ data ] = useState( boot.item );
	const [ activate, setActivate ] = useState( {} );
	const [ busy, setBusy ] = useState( '' );
	const [ file, setFile ] = useState( null );
	const [ fileActive, setFileActive ] = useState( false );
	const [ results, setResults ] = useState( null );
	const stillOn = data.sources.filter( ( source ) => source.active );

	const fail = ( error ) =>
		toast.show( { tone: 'error', message: api.errorMessage( error ) } );

	const runMigrate = ( source ) => {
		setBusy( source.key );
		api.migrate( source.key, !! activate[ source.key ] )
			.then( setResults )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	const runImport = () => {
		if ( ! file ) {
			return;
		}
		setBusy( 'file' );
		file.text()
			.then( ( json ) => api.importFile( json, fileActive ) )
			.then( ( result ) => {
				setResults( result );
				setFile( null );
			} )
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	const exportAll = () => {
		setBusy( 'export' );
		api.exportSnippets( [] )
			.then( ( payload ) =>
				api.download( payload.filename, payload.data )
			)
			.catch( fail )
			.finally( () => setBusy( '' ) );
	};

	return (
		<div className="sd-tools">
			<div className="sd-tools__head">
				<h1 className="sd-screen__title">
					{ __( 'Import & Export', 'scriptdock' ) }
				</h1>
				<p className="sd-tools__intro">
					{ __(
						'Move snippets in from another plugin or a file, and take a copy out whenever you like.',
						'scriptdock'
					) }
				</p>
			</div>

			<Card
				title={ __( 'Move from another plugin', 'scriptdock' ) }
				subtitle={ __(
					'ScriptDock reads what these plugins saved. Nothing is changed in them.',
					'scriptdock'
				) }
			>
				{ stillOn.length > 0 && (
					<Banner tone="warning" inline>
						{ sprintf(
							/* translators: %s: plugin name, for example "WPCode". */
							__(
								'%s is still active. Switch it off after importing, or the same code runs twice.',
								'scriptdock'
							),
							stillOn[ 0 ].label
						) }
					</Banner>
				) }

				{ data.sources.length === 0 ? (
					<p className="sd-tools__empty">
						{ __(
							'No other snippet plugins were found on this site.',
							'scriptdock'
						) }
					</p>
				) : (
					<ul className="sd-sources">
						{ data.sources.map( ( source ) => (
							<li key={ source.key } className="sd-sources__row">
								<span
									className="sd-sources__mark"
									aria-hidden="true"
								>
									<Icon
										name="plugin"
										size={ 16 }
										stroke={ 1.8 }
									/>
								</span>
								<span className="sd-sources__text">
									<span className="sd-sources__name">
										{ source.label }
										{ source.active && (
											<Badge tone="warning" size="sm">
												{ __(
													'Still active',
													'scriptdock'
												) }
											</Badge>
										) }
									</span>
									<span className="sd-sources__count">
										{ sprintf(
											/* translators: %d: number of snippets found. */
											_n(
												'%d snippet found',
												'%d snippets found',
												source.count,
												'scriptdock'
											),
											source.count
										) }
									</span>
								</span>
								<SwitchField
									label={
										<>
											{ __(
												'Keep them running',
												'scriptdock'
											) }
											<span className="sd-visually-hidden">
												{ ` (${ source.label })` }
											</span>
										</>
									}
									labelSize="sm"
									checked={ !! activate[ source.key ] }
									onChange={ ( on ) =>
										setActivate( ( old ) => ( {
											...old,
											[ source.key ]: on,
										} ) )
									}
								/>
								<Button
									size="compact"
									loading={ busy === source.key }
									loadingLabel={ __(
										'Importing…',
										'scriptdock'
									) }
									onClick={ () => runMigrate( source ) }
									aria-label={ sprintf(
										/* translators: %s: the plugin snippets come from, for example "WPCode". */
										__( 'Import from %s', 'scriptdock' ),
										source.label
									) }
								>
									{ __( 'Import', 'scriptdock' ) }
								</Button>
							</li>
						) ) }
					</ul>
				) }
			</Card>

			<Card
				title={ __( 'Import a file', 'scriptdock' ) }
				subtitle={ __(
					'A ScriptDock export, or a Code Snippets JSON file.',
					'scriptdock'
				) }
			>
				<FileDropzone
					label={ __( 'Import file', 'scriptdock' ) }
					accept="application/json,.json"
					title={ __(
						'Drop a .json export here, or browse',
						'scriptdock'
					) }
					file={ file }
					meta={ file ? size( file.size ) : '' }
					onFile={ setFile }
					onRemove={ () => setFile( null ) }
				/>
				<div className="sd-tools__row">
					<SwitchField
						label={ __( 'Keep snippets running', 'scriptdock' ) }
						checked={ fileActive }
						onChange={ setFileActive }
					/>
					<Button
						variant="primary"
						disabled={ ! file }
						loading={ busy === 'file' }
						loadingLabel={ __( 'Importing…', 'scriptdock' ) }
						onClick={ runImport }
					>
						{ __( 'Import', 'scriptdock' ) }
					</Button>
				</div>
			</Card>

			<Card
				title={ __( 'Export', 'scriptdock' ) }
				subtitle={ __(
					'A JSON file with every snippet, its settings and its rules.',
					'scriptdock'
				) }
			>
				<div className="sd-tools__row">
					<Button
						variant="primary"
						loading={ busy === 'export' }
						loadingLabel={ __( 'Preparing…', 'scriptdock' ) }
						onClick={ exportAll }
					>
						{ __( 'Export all snippets', 'scriptdock' ) }
					</Button>
					<p className="sd-tools__hint">
						{ __(
							'To export only some, pick them on the Snippets list and use Export there.',
							'scriptdock'
						) }
					</p>
				</div>
				<p className="sd-tools__cli">
					<code>{ data.export.cli }</code>
				</p>
			</Card>

			{ results && (
				<Results
					results={ results }
					onClose={ () => setResults( null ) }
				/>
			) }
		</div>
	);
}

/**
 * S12b: what an import did, and what it could not do.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.results Import results.
 * @param {Function} props.onClose Closes the modal.
 * @return {Element} The modal.
 */
function Results( { results, onClose } ) {
	return (
		<Modal
			open
			title={ __( 'Import finished', 'scriptdock' ) }
			subtitle={ results.notice.message }
			onClose={ onClose }
			footer={
				<>
					<Button variant="primary" href={ results.list }>
						{ __( 'View imported snippets', 'scriptdock' ) }
					</Button>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Stay here', 'scriptdock' ) }
					</Button>
				</>
			}
		>
			{ results.warnings.length === 0 ? (
				<p className="sd-tools__clean">
					{ __( 'Everything came across.', 'scriptdock' ) }
				</p>
			) : (
				<ul className="sd-tools__warnings">
					{ results.warnings.map( ( warning, index ) => (
						<li key={ index }>
							<Icon name="alert" size={ 14 } stroke={ 2 } />
							{ warning }
						</li>
					) ) }
				</ul>
			) }
		</Modal>
	);
}

/**
 * A file size a person can read.
 *
 * @param {number} bytes Size in bytes.
 * @return {string} For example "12 KB".
 */
function size( bytes ) {
	if ( bytes < 1024 ) {
		return sprintf(
			/* translators: %d: number of bytes. */
			__( '%d bytes', 'scriptdock' ),
			bytes
		);
	}
	return sprintf(
		/* translators: %d: size in kilobytes. */
		__( '%d KB', 'scriptdock' ),
		Math.round( bytes / 1024 )
	);
}
