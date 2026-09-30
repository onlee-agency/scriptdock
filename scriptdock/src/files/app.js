/**
 * S11: Site Files. The small text files a site is expected to serve at its
 * root, written here instead of over FTP.
 *
 * A real file on the server always wins, so each card says which one is
 * answering.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Banner,
	Button,
	Card,
	Icon,
	SaveBar,
	SegmentedControl,
	TextArea,
	useToast,
} from '../components';
import * as api from './api';

/**
 * @param {Object} props      Props.
 * @param {Object} props.boot The screen's data.
 * @return {Element} The screen.
 */
export default function App( { boot } ) {
	const toast = useToast();
	const [ saved, setSaved ] = useState( boot.item );
	const [ draft, setDraft ] = useState( () => toDraft( boot.item ) );
	const [ saving, setSaving ] = useState( false );
	const dirty = changed( draft, saved );
	const cards = [ ...saved.files, saved.robots ];

	useEffect( () => {
		const onKeyDown = ( event ) => {
			const meta = event.metaKey || event.ctrlKey;
			if ( ! meta || event.key !== 's' || event.defaultPrevented ) {
				return;
			}
			event.preventDefault();
			if ( dirty ) {
				save();
			}
		};
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	} );

	const save = () => {
		setSaving( true );
		api.saveFiles( draft )
			.then( ( result ) => {
				setSaved( result.item );
				setDraft( toDraft( result.item ) );
				toast.show( { message: result.notice.message } );
			} )
			.catch( ( problem ) =>
				toast.show( {
					tone: 'error',
					message: api.errorMessage( problem ),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	return (
		<div className="sd-files">
			<div className="sd-files__head">
				<h1 className="sd-screen__title">
					{ __( 'Site Files', 'scriptdock' ) }
				</h1>
				<p className="sd-files__intro">
					{ __(
						'Small text files that live at the root of the site. ScriptDock serves them, so there is nothing to upload.',
						'scriptdock'
					) }
				</p>
			</div>

			{ boot.item.subfolder && (
				<Banner
					tone="warning"
					title={ __( 'WordPress is in a subfolder', 'scriptdock' ) }
				>
					{ sprintf(
						/* translators: %s: the folder WordPress is installed in, for example "/blog". */
						__(
							'This site runs from %s, so crawlers look for these files at the domain root, where ScriptDock cannot serve them. Put real files there instead.',
							'scriptdock'
						),
						boot.item.subfolder
					) }
				</Banner>
			) }

			{ ! boot.item.blog_public && (
				<Banner
					tone="warning"
					title={ __(
						'Search engines are discouraged',
						'scriptdock'
					) }
				>
					{ __(
						'WordPress is set to ask search engines not to index this site, so robots.txt says so too.',
						'scriptdock'
					) }
				</Banner>
			) }

			{ cards.map( ( file ) => (
				<Card
					key={ file.key }
					title={
						<span className="sd-files__name">
							<code>{ file.name }</code>
							<StatePill state={ file.state } />
						</span>
					}
					subtitle={ file.description }
					action={
						file.state === 'live' ? (
							<a
								className="sd-files__view"
								href={ file.url }
								target="_blank"
								rel="noreferrer"
							>
								{ __( 'View file', 'scriptdock' ) }
								<span className="sd-visually-hidden">
									{ ' ' +
										sprintf(
											/* translators: %s: file name, for example "ads.txt". */
											__(
												'%s (opens in a new tab)',
												'scriptdock'
											),
											file.name
										) }
								</span>
								<Icon
									name="external"
									size={ 13 }
									stroke={ 2 }
								/>
							</a>
						) : null
					}
				>
					{ file.state === 'overridden' && (
						<p className="sd-files__note">
							{ sprintf(
								/* translators: %s: the file's path, for example "/ads.txt". */
								__(
									'A real file already sits at %s on the server, and that one is served. Remove it to use this one.',
									'scriptdock'
								),
								file.path
							) }
						</p>
					) }

					{ file.key === 'robots_txt' && (
						<div className="sd-files__mode">
							<SegmentedControl
								label={ __(
									'How these rules are used',
									'scriptdock'
								) }
								value={ draft.robots_mode }
								onChange={ ( mode ) =>
									setDraft( ( old ) => ( {
										...old,
										robots_mode: mode,
									} ) )
								}
								options={ [
									{
										value: 'append',
										label: __(
											'Add to WordPress rules',
											'scriptdock'
										),
									},
									{
										value: 'replace',
										label: __(
											'Replace them',
											'scriptdock'
										),
									},
								] }
							/>
						</div>
					) }

					<TextArea
						label={ sprintf(
							/* translators: %s: file name, for example "ads.txt". */
							__( 'Contents of %s', 'scriptdock' ),
							file.name
						) }
						hideLabel
						mono
						rows={ 6 }
						value={ draft[ file.key ] }
						placeholder={ file.example }
						onChange={ ( value ) =>
							setDraft( ( old ) => ( {
								...old,
								[ file.key ]: value,
							} ) )
						}
					/>

					<div className="sd-files__row">
						<Button
							variant="ghost"
							size="compact"
							disabled={ draft[ file.key ].trim() !== '' }
							onClick={ () =>
								setDraft( ( old ) => ( {
									...old,
									[ file.key ]: file.example,
								} ) )
							}
						>
							{ __( 'Insert example', 'scriptdock' ) }
						</Button>
						<span className="sd-files__path">{ file.path }</span>
					</div>
				</Card>
			) ) }

			{ dirty && (
				<SaveBar
					saving={ saving }
					onDiscard={ () => setDraft( toDraft( saved ) ) }
					onSave={ save }
				/>
			) }
		</div>
	);
}

/**
 * Live, off, or a real file is answering instead.
 *
 * @param {Object} props       Props.
 * @param {string} props.state live, off or overridden.
 * @return {Element} The pill.
 */
function StatePill( { state } ) {
	if ( state === 'live' ) {
		return (
			<Badge tone="success" icon="check" size="sm">
				{ __( 'Live', 'scriptdock' ) }
			</Badge>
		);
	}
	if ( state === 'overridden' ) {
		return (
			<Badge tone="warning" icon="alert" size="sm">
				{ __( 'Overridden by a server file', 'scriptdock' ) }
			</Badge>
		);
	}
	return (
		<Badge tone="muted" size="sm">
			{ __( 'Off', 'scriptdock' ) }
		</Badge>
	);
}

/**
 * The editable copy of what is saved.
 *
 * @param {Object} item Saved data.
 * @return {Object} Draft, by file key.
 */
function toDraft( item ) {
	const draft = { robots_mode: item.robots.mode };
	item.files.forEach( ( file ) => {
		draft[ file.key ] = file.content;
	} );
	draft[ item.robots.key ] = item.robots.content;
	return draft;
}

/**
 * Whether anything differs from what is saved.
 *
 * @param {Object} draft Draft.
 * @param {Object} item  Saved data.
 * @return {boolean} True when there is something to save.
 */
function changed( draft, item ) {
	if ( draft.robots_mode !== item.robots.mode ) {
		return true;
	}
	if ( draft[ item.robots.key ] !== item.robots.content ) {
		return true;
	}
	return item.files.some( ( file ) => draft[ file.key ] !== file.content );
}
