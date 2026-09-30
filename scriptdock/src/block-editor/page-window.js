/**
 * S08b: the "Page code" window, opened from the block editor panel.
 *
 * Vertical tabs for the seven places code can go, one editor at a time. It
 * writes straight into the post, so closing it keeps the code and updating
 * the page saves it.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Badge, Button, CodeEditor, Icon, Modal, Tabs } from '../components';

/**
 * @param {Object}   props         Props.
 * @param {Object}   props.boot    Panel bootstrap data.
 * @param {Object}   props.data    Page code.
 * @param {Function} props.update  Changes page code.
 * @param {string}   props.title   Page title.
 * @param {string}   props.slot    Slot to open on.
 * @param {Function} props.onClose Closes the window.
 * @return {Element} The window.
 */
export default function PageWindow( {
	boot,
	data,
	update,
	title,
	slot,
	onClose,
} ) {
	const [ current, setCurrent ] = useState( slot || boot.slots[ 0 ].key );
	const [ theme, setTheme ] = useState( boot.theme );
	const active =
		boot.slots.find( ( item ) => item.key === current ) || boot.slots[ 0 ];
	const settings = boot.codeEditors
		? boot.codeEditors[ active.language ]
		: null;

	return (
		<Modal
			open
			size="lg"
			className="sd-pagewin"
			title={ sprintf(
				/* translators: %s: page title. */
				__( 'Page code · %s', 'scriptdock' ),
				title || __( '(no title)', 'scriptdock' )
			) }
			headerExtra={
				<Badge tone="muted">
					{ __( 'Only this page', 'scriptdock' ) }
				</Badge>
			}
			onClose={ onClose }
			footer={
				<>
					<span className="sd-pagewin__note">
						<Icon name="info" size={ 16 } />
						{ __(
							'Saved when you update the page.',
							'scriptdock'
						) }
					</span>
					<Button variant="primary" dot="check" onClick={ onClose }>
						{ __( 'Done', 'scriptdock' ) }
					</Button>
				</>
			}
		>
			<div className="sd-pagewin__layout">
				<Tabs
					variant="vertical"
					idPrefix="sd-pagewin"
					label={ __( 'Where the code goes', 'scriptdock' ) }
					selected={ current }
					onSelect={ setCurrent }
					className="sd-pagewin__tabs"
					tabs={ boot.slots.map( ( item ) => ( {
						id: item.key,
						label: item.label,
						dot: data[ item.key ].trim() ? 'filled' : undefined,
					} ) ) }
				/>

				<div
					id={ `sd-pagewin-panel-${ active.key }` }
					role="tabpanel"
					aria-labelledby={ `sd-pagewin-tab-${ active.key }` }
					className="sd-pagewin__panel"
				>
					<div className="sd-pagewin__intro">
						<span className="sd-pagewin__title">
							{ active.title }
						</span>
						<span className="sd-pagewin__desc">
							{ active.description }
						</span>
					</div>

					<CodeEditor
						key={ active.key }
						value={ data[ active.key ] }
						onChange={ ( code ) =>
							update( { [ active.key ]: code } )
						}
						type={ active.language }
						settings={ settings }
						theme={ theme }
						onThemeChange={ setTheme }
						label={ active.title }
						fileName={ fileName( title, active ) }
						title={ active.title }
						smartTags={ boot.smartTags }
						placeholder={ placeholder( active ) }
					/>
				</div>
			</div>
		</Modal>
	);
}

/**
 * A file name for the editor's toolbar, from the page title and the slot.
 *
 * @param {string} title Page title.
 * @param {Object} slot  Slot.
 * @return {string} For example "pricing-page.css".
 */
function fileName( title, slot ) {
	const stem =
		( title || __( 'page', 'scriptdock' ) )
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' ) || 'page';
	const extensions = { html: 'html', css: 'css', js: 'js' };
	return `${ stem }-${ slot.key }.${ extensions[ slot.language ] }`;
}

/**
 * What an empty slot suggests.
 *
 * @param {Object} slot Slot.
 * @return {string} Placeholder.
 */
function placeholder( slot ) {
	if ( slot.language === 'css' ) {
		return __( '.my-class { color: #111; }', 'scriptdock' );
	}
	if ( slot.language === 'js' ) {
		return __( "console.log( 'Hello from this page' );", 'scriptdock' );
	}
	return __( '<!-- Paste the code you were given -->', 'scriptdock' );
}
