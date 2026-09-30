/**
 * S09: Header & Footer. Three areas of code that run on every page, with one
 * save bar for the lot.
 */
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Banner,
	Button,
	Card,
	CodeEditor,
	NumberStepper,
	SaveBar,
	useToast,
} from '../components';
import * as api from './api';

/**
 * @param {Object} props      Props.
 * @param {Object} props.boot The screen's data and the code editor settings.
 * @return {Element} The screen.
 */
export default function App( { boot } ) {
	const toast = useToast();
	const [ saved, setSaved ] = useState( boot.item );
	const [ draft, setDraft ] = useState( () => toDraft( boot.item ) );
	const [ saving, setSaving ] = useState( false );
	const dirty = changed( draft, saved );

	// ⌘S saves, the way the editor does.
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

	const set = ( key, change ) =>
		setDraft( ( old ) => ( {
			...old,
			[ key ]: { ...old[ key ], ...change },
		} ) );

	const save = () => {
		setSaving( true );
		api.saveGlobal( payload( draft ) )
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
		<div className="sd-global">
			<div className="sd-global__head">
				<h1 className="sd-screen__title">
					{ __( 'Header & Footer', 'scriptdock' ) }
				</h1>
				<p className="sd-global__intro">
					{ __(
						'The quickest way to add code to every page. For conditions, per-page code or PHP, create a snippet instead.',
						'scriptdock'
					) }
				</p>
			</div>

			{ ! saved.trusted && (
				<Banner
					tone="danger"
					icon="shield-alert"
					title={ __(
						'This code changed outside ScriptDock',
						'scriptdock'
					) }
				>
					{ __(
						'It is not being printed until you look at it. Saving here approves the code as it stands.',
						'scriptdock'
					) }
				</Banner>
			) }

			{ saved.areas.map( ( area ) => (
				<Card
					key={ area.key }
					title={ area.label }
					subtitle={ area.description }
				>
					<CodeEditor
						value={ draft[ area.key ].code }
						onChange={ ( code ) => set( area.key, { code } ) }
						type="html"
						settings={
							boot.codeEditors ? boot.codeEditors.html : null
						}
						fileName={ `${ area.key }.html` }
						label={ sprintf(
							/* translators: %s: area name, for example "Header". */
							__( '%s code', 'scriptdock' ),
							area.label
						) }
						theme={ boot.theme }
						placeholder={ placeholder( area.key ) }
						onSave={ () => dirty && save() }
					/>
					<div className="sd-global__row">
						<NumberStepper
							label={ __( 'Priority', 'scriptdock' ) }
							context={ area.label }
							help={ __( 'Lower runs first', 'scriptdock' ) }
							value={ draft[ area.key ].priority }
							min={ -9999 }
							max={ 9999 }
							onChange={ ( priority ) =>
								set( area.key, { priority } )
							}
						/>
						<p className="sd-global__hook">
							{ sprintf(
								/* translators: %s: WordPress hook, for example "wp_head". */
								__( 'Printed on %s', 'scriptdock' ),
								area.hook
							) }
						</p>
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

			<p className="sd-global__foot">
				{ __(
					'Code here runs on the front end only, and never in the admin.',
					'scriptdock'
				) }
				<Button
					variant="tertiary"
					size="compact"
					href={ boot.urls.new }
				>
					{ __( 'Create a snippet instead', 'scriptdock' ) }
				</Button>
			</p>
		</div>
	);
}

/**
 * The editable copy of what is saved.
 *
 * @param {Object} item Saved data.
 * @return {Object} Draft, by area key.
 */
function toDraft( item ) {
	const draft = {};
	item.areas.forEach( ( area ) => {
		draft[ area.key ] = { code: area.code, priority: area.priority };
	} );
	return draft;
}

/**
 * What the save call sends.
 *
 * @param {Object} draft Draft.
 * @return {Object} Fields.
 */
function payload( draft ) {
	const data = {};
	Object.keys( draft ).forEach( ( key ) => {
		data[ key ] = draft[ key ].code;
		data[ `${ key }_priority` ] = draft[ key ].priority;
	} );
	return data;
}

/**
 * Whether anything differs from what is saved.
 *
 * @param {Object} draft Draft.
 * @param {Object} item  Saved data.
 * @return {boolean} True when there is something to save.
 */
function changed( draft, item ) {
	return item.areas.some(
		( area ) =>
			draft[ area.key ].code !== area.code ||
			draft[ area.key ].priority !== area.priority
	);
}

/**
 * What an empty area suggests.
 *
 * @param {string} key head, body or footer.
 * @return {string} Placeholder code.
 */
function placeholder( key ) {
	if ( key === 'head' ) {
		return '<!-- Verification tags, fonts, analytics -->\n<meta name="google-site-verification" content="…">';
	}
	if ( key === 'body' ) {
		return '<!-- Google Tag Manager (noscript) -->\n<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXX"></iframe></noscript>';
	}
	return '<!-- Chat widgets, late-loading scripts -->';
}
