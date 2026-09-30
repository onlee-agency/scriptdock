/**
 * DEV ONLY. The component gallery: every shared component in every state,
 * with the design's sample data, laid out like ScriptDock Components.dc.html
 * so the two can be compared side by side. Never ships with the plugin.
 */
import { createRoot, useRef, useState } from '@wordpress/element';
import axe from 'axe-core';
import './gallery.css';
import {
	Badge,
	Banner,
	BottomSheet,
	Breadcrumb,
	BulkBar,
	Button,
	Card,
	Checkbox,
	CodeEditor,
	CodePreview,
	Combobox,
	ConfirmDialog,
	CopyChip,
	Drawer,
	DropdownMenu,
	Illustration,
	EmptyState,
	FileDropzone,
	IconButton,
	KeyValueField,
	KeyValueList,
	Link,
	LintStatus,
	Modal,
	NumberStepper,
	Pagination,
	Pills,
	PluginSourceRow,
	ProgressBar,
	ProgressRing,
	Radio,
	SaveBar,
	SearchField,
	SegmentedControl,
	Select,
	SettingsRow,
	SkeletonCard,
	SmartTagPalette,
	SplitButton,
	StatTile,
	Status,
	Stepper,
	StepperCompact,
	Switch,
	SwitchField,
	Table,
	TabPanel,
	Tabs,
	TargetingSentence,
	TemplateCard,
	TextArea,
	TextField,
	TimeRange,
	TokenInput,
	ToastProvider,
	Tooltip,
	TypeChip,
	WeekdayChips,
	useToast,
} from '../../../scriptdock/src/components';

window.sdAxe = axe;

const editorSettings =
	( window.sdGallery && window.sdGallery.editorSettings ) || {};

const SMART_TAGS = [
	{
		label: 'Site & page',
		tags: [
			{ tag: 'site_name', description: 'Lumen Coffee Roasters' },
			{ tag: 'page_title', description: "The current page's title" },
			{ tag: 'page_url', description: 'Full URL of the current page' },
			{ tag: 'home_url', description: 'lumencoffee.com' },
		],
	},
	{
		label: 'Post',
		tags: [
			{ tag: 'post_id', description: 'Numeric ID of this post' },
			{ tag: 'post_author', description: 'Display name of the author' },
			{
				tag: 'post_categories',
				description: 'Comma-separated category names',
			},
		],
	},
	{
		label: 'User & time',
		tags: [
			{ tag: 'user_id', description: '0 when logged out' },
			{ tag: 'user_role', description: 'First role of the current user' },
			{ tag: 'date', description: "Today, in the site's format" },
		],
	},
	{
		label: 'WooCommerce',
		available: false,
		note: 'WooCommerce tags render empty outside checkout and order pages. They stay listed so you know they exist.',
		tags: [
			{ tag: 'wc_order_id', description: 'On the thank-you page only' },
			{ tag: 'wc_order_total', description: 'Order total, unformatted' },
			{ tag: 'wc_cart_total', description: 'Current cart value' },
		],
	},
];

const GA4_HTML = `<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-8XK2N4P1QZ"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('config', 'G-8XK2N4P1QZ', { page_title: '{{page_title|js}}' });
</script>
`;

const WHOLESALE_PHP = `add_filter( 'woocommerce_get_price_html', function ( $price, $product ) {
	if ( ! current_user_can( 'wholesale_customer' ) ) {
		return $price
	}
	return wc_price( $product->get_meta( '_wholesale_price' ) ) . ' <small>net</small>';
}, 10, 2 );
`;

const GA4_TEMPLATE = `<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=%%MEASUREMENT_ID%%"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '%%MEASUREMENT_ID%%');
</script>`;

const PAGES = [
	{ value: 12, label: 'Brewing guides', meta: '/brewing-guides/' },
	{ value: 13, label: 'Pour-over', meta: '/brewing-guides/pour-over/' },
	{
		value: 14,
		label: 'Cold brew at home, the easy way',
		meta: 'Post · Recipes',
	},
	{ value: 15, label: 'About us', meta: '/about/' },
	{ value: 16, label: 'Pricing', meta: '/pricing/' },
];

const ROWS = [
	{
		id: 1,
		title: 'GA4 tag',
		sub: 'Google Analytics 4 · G-8XK2N4P1QZ',
		type: 'html',
		where: 'Site header',
		scope: 'Entire site',
		badge: [ 'Statistics', 'muted' ],
		priority: 5,
		updated: '2 hours ago',
		active: true,
	},
	{
		id: 2,
		title: 'Returning-customer discount',
		sub: 'Switched off after a fatal error on line 12',
		subDanger: true,
		type: 'php',
		where: 'Run everywhere',
		scope: '—',
		badge: [ 'Error', 'outline-danger' ],
		priority: 10,
		updated: '25 min ago',
		active: false,
		error: true,
	},
	{
		id: 3,
		title: 'Hotjar (old)',
		sub: 'Changed outside ScriptDock 1 hour ago · paused',
		type: 'html',
		where: 'Site header',
		scope: 'Entire site',
		badge: [ 'Needs review', 'danger', 'shield-review' ],
		priority: 10,
		updated: '1 hour ago',
		paused: true,
	},
	{
		id: 4,
		title: 'Chat widget (Crisp)',
		sub: 'Loads after the first scroll or tap to keep pages fast',
		type: 'js',
		where: 'Site footer',
		scope: 'Except Cart, Checkout',
		badge: [ 'On interaction', 'muted' ],
		priority: 10,
		updated: '2 days ago',
		active: true,
	},
	{
		id: 5,
		title: 'Brand fonts & colours',
		sub: 'Saving…',
		type: 'css',
		where: 'Site header',
		scope: 'Entire site',
		badge: [ 'Cached file', 'success' ],
		priority: 5,
		updated: '3 weeks ago',
		active: true,
		busy: true,
	},
	{
		id: 6,
		title: 'Wholesale prices',
		sub: 'You cannot switch PHP snippets on this site',
		type: 'php',
		where: 'Front end only',
		scope: 'Role: Wholesale customer',
		badge: [ 'Conditional', 'muted' ],
		priority: 20,
		updated: '6 days ago',
		active: true,
		locked: true,
	},
];

function Section( { title, note, children } ) {
	return (
		<section className="sd-card gallery-section" aria-label={ title }>
			<div className="sd-card__header">
				<h2 className="sd-h2">{ title }</h2>
				{ note && <span className="sd-card__subtitle">{ note }</span> }
			</div>
			{ children }
		</section>
	);
}

function Row( { label, children } ) {
	return (
		<div className="gallery-row">
			<span className="gallery-row__label">{ label }</span>
			<div className="gallery-row__items">{ children }</div>
		</div>
	);
}

function Buttons() {
	return (
		<Section
			title="sd-Button"
			note="sm 32 · md 40 · lg 48 · flush-left label with the orange circle on the right"
		>
			<Row label="primary">
				<Button variant="primary" dot="check">
					Save snippet
				</Button>
				<Button variant="primary" dot="check" disabled>
					Save snippet
				</Button>
				<Button variant="primary" loading loadingLabel="Saving…">
					Save snippet
				</Button>
			</Row>
			<Row label="secondary">
				<Button>Import</Button>
				<Button disabled>Import</Button>
				<Button dot="plus">Add</Button>
			</Row>
			<Row label="tertiary · accent · danger">
				<Button variant="tertiary">Discard</Button>
				<Button variant="accent">Add &amp; activate</Button>
				<Button variant="danger">Move to trash</Button>
				<Button variant="danger-solid">Delete permanently</Button>
			</Row>
			<Row label="sizes">
				<Button variant="primary" size="sm">
					sm 32
				</Button>
				<Button variant="primary">md 40</Button>
				<Button variant="primary" size="lg" dot="plus">
					lg 48
				</Button>
			</Row>
			<Row label="icon · split · copy · link">
				<IconButton
					icon="chevron-left"
					label="Previous"
					size="lg"
					stroke={ 1.6 }
					className="sd-flip"
				/>
				<IconButton
					icon="chevron-right"
					label="Next"
					size="lg"
					stroke={ 1.6 }
					className="sd-flip"
				/>
				<IconButton icon="more" label="More actions" variant="soft" />
				<IconButton
					icon="close"
					label="Close"
					variant="ink"
					iconSize={ 16 }
					stroke={ 2 }
				/>
				<SplitButton
					label="Save"
					onClick={ () => {} }
					items={ [
						{ label: 'Save and deactivate', icon: 'shield' },
						{ label: 'Save as a copy', icon: 'copy' },
					] }
				/>
				<CopyChip value={ '[scriptdock id="214"]' } />
				<Link href="#">View file</Link>
			</Row>
		</Section>
	);
}

function Switches() {
	const toast = useToast();
	const [ on, setOn ] = useState( true );
	const [ off, setOff ] = useState( false );
	const [ keep, setKeep ] = useState( false );
	return (
		<Section
			title="sd-Switch"
			note="Ink track, orange-gradient knob, check glyph inside the knob so colour is never the only signal."
		>
			<Row label="states">
				<Switch
					checked={ off }
					onChange={ setOff }
					label="Off example"
				/>
				<Switch checked={ on } onChange={ setOn } label="On example" />
				<Switch
					checked
					busy
					label="Brand fonts saving"
					onChange={ () => {} }
				/>
				<Switch
					checked
					locked
					label="Wholesale prices, locked: you cannot edit PHP"
					onChange={ () => {} }
				/>
				<Switch
					locked
					label="Disabled and locked"
					onChange={ () => {} }
				/>
				<Switch
					paused
					label="Hotjar (old) paused pending review"
					onChange={ () => {} }
					onBlockedClick={ () =>
						toast.show( {
							message: 'Paused switch: this opens History',
						} )
					}
				/>
				<Switch
					size="sm"
					checked={ off }
					onChange={ setOff }
					label="Small off"
				/>
				<Switch
					size="sm"
					checked={ on }
					onChange={ setOn }
					label="Small on"
				/>
			</Row>
			<Row label="with label">
				<SwitchField
					label="Keep active"
					checked={ keep }
					onChange={ setKeep }
				/>
				<SwitchField
					label="Activate now"
					labelSize="md"
					checked={ ! keep }
					onChange={ ( value ) => setKeep( ! value ) }
				/>
			</Row>
		</Section>
	);
}

function Selection() {
	const [ checks, setChecks ] = useState( {
		pages: false,
		posts: true,
		products: true,
	} );
	const [ radio, setRadio ] = useState( 'replace' );
	const [ output, setOutput ] = useState( 'inline' );
	const [ days, setDays ] = useState( [ 1, 2, 3, 4, 5 ] );
	const names = [
		'Monday',
		'Tuesday',
		'Wednesday',
		'Thursday',
		'Friday',
		'Saturday',
		'Sunday',
	];
	return (
		<Section title="sd-Checkbox · sd-Radio · sd-SegmentedControl · sd-WeekdayChips">
			<Row label="checkbox">
				<Checkbox
					label="Pages"
					checked={ checks.pages }
					onChange={ ( value ) =>
						setChecks( { ...checks, pages: value } )
					}
				/>
				<Checkbox
					label="Posts"
					checked={ checks.posts }
					onChange={ ( value ) =>
						setChecks( { ...checks, posts: value } )
					}
				/>
				<Checkbox
					label="Mixed"
					indeterminate
					onChange={ () => {} }
					hint="indeterminate"
				/>
				<Checkbox
					label="Workshops"
					disabled
					onChange={ () => {} }
					hint="disabled"
				/>
			</Row>
			<Row label="radio">
				<Radio
					name="robots"
					label="Add to WordPress rules"
					checked={ radio === 'append' }
					onChange={ () => setRadio( 'append' ) }
				/>
				<Radio
					name="robots"
					label="Replace it"
					checked={ radio === 'replace' }
					onChange={ () => setRadio( 'replace' ) }
				/>
				<Radio
					name="robots"
					label="Disabled option"
					disabled
					onChange={ () => {} }
				/>
			</Row>
			<Row label="segmented">
				<SegmentedControl
					label="Output"
					value={ output }
					onChange={ setOutput }
					options={ [
						{ value: 'inline', label: 'Inline in the page' },
						{ value: 'file', label: 'As a cached file' },
					] }
				/>
				<SegmentedControl
					label="Output for Between posts"
					value="inline"
					onChange={ () => {} }
					options={ [
						{ value: 'inline', label: 'Inline' },
						{
							value: 'file',
							label: 'Cached file',
							disabled: true,
							reason: "Between posts can't load files.",
						},
					] }
				/>
			</Row>
			<Row label="weekdays">
				<WeekdayChips
					label="Days"
					value={ days }
					onChange={ setDays }
					days={ [
						'Mon',
						'Tue',
						'Wed',
						'Thu',
						'Fri',
						'Sat',
						'Sun',
					].map( ( label, index ) => ( {
						value: index + 1,
						label,
						name: names[ index ],
					} ) ) }
				/>
			</Row>
		</Section>
	);
}

function Inputs() {
	const [ title, setTitle ] = useState( 'GA4 tag' );
	const [ id, setId ] = useState( 'GA-12345' );
	const [ notes, setNotes ] = useState( 'Google Analytics 4 · G-8XK2N4P1QZ' );
	const [ priority, setPriority ] = useState( 10 );
	const [ consent, setConsent ] = useState( 'statistics' );
	const [ time, setTime ] = useState( { from: '09:00', to: '17:00' } );
	const [ tags, setTags ] = useState( [
		{ value: 'analytics', label: 'analytics' },
		{ value: 'tracking', label: 'tracking' },
	] );
	const [ field, setField ] = useState( {
		key: '_wholesale_price',
		value: 'yes',
	} );
	const [ search, setSearch ] = useState( '' );
	const [ file, setFile ] = useState( null );
	const [ picked, setPicked ] = useState( null );
	const searchPages = ( query ) =>
		new Promise( ( resolve ) =>
			setTimeout(
				() =>
					resolve(
						PAGES.filter( ( page ) =>
							page.label
								.toLowerCase()
								.includes( query.toLowerCase() )
						)
					),
				400
			)
		);
	return (
		<Section title="sd-TextField · sd-TextArea · sd-NumberStepper · sd-Select · sd-Combobox · sd-TokenInput · sd-SearchField · sd-KeyValueField · sd-TimeRange · sd-FileDropzone">
			<div className="gallery-grid">
				<TextField
					label="Snippet title"
					value={ title }
					onChange={ setTitle }
				/>
				<TextField
					label="Measurement ID"
					value={ id }
					onChange={ setId }
					mono
					error={
						/^G-[A-Z0-9]{4,}$/.test( id )
							? undefined
							: 'Use the format G-XXXXXXXXXX'
					}
				/>
				<TextField
					label="Hook name"
					value="woocommerce_after_cart"
					onChange={ () => {} }
					mono
					disabled
					help="disabled · mono variant"
				/>
				<TextArea
					label="Notes"
					value={ notes }
					onChange={ setNotes }
					maxLength={ 300 }
				/>
				<NumberStepper
					label="Priority"
					value={ priority }
					onChange={ setPriority }
					min={ 0 }
					max={ 999 }
					help="Lower runs first."
				/>
				<Select
					label="Require cookie consent"
					value={ consent }
					onChange={ setConsent }
					help="Complianz detected — categories map automatically."
					options={ [
						{ value: '', label: 'Not required' },
						{ value: 'statistics', label: 'Statistics' },
						{
							value: 'statistics-anonymous',
							label: 'Anonymous statistics',
						},
						{ value: 'marketing', label: 'Marketing' },
						{ value: 'preferences', label: 'Preferences' },
						{ value: 'functional', label: 'Functional' },
					] }
				/>
				<TimeRange
					label="Time window"
					from={ time.from }
					to={ time.to }
					onChange={ setTime }
					note="Site timezone: America/New_York"
				/>
			</div>
			<div className="gallery-grid">
				<TokenInput
					label="Tags"
					value={ tags }
					onChange={ setTags }
					allowCreate
					placeholder="Add a tag…"
					suggestions={ [
						{ value: 'seasonal', label: 'seasonal', count: 3 },
						{ value: 'design', label: 'design', count: 4 },
						{
							value: 'woocommerce',
							label: 'woocommerce',
							count: 2,
						},
					] }
				/>
				<div>
					<Combobox
						label="Specific post or page"
						placeholder="Search pages and posts…"
						onSearch={ searchPages }
						onSelect={ setPicked }
						status="Searching 312 items…"
					/>
					{ picked && (
						<p className="sd-small">Picked: { picked.label }</p>
					) }
				</div>
				<div className="sd-field">
					<span className="sd-field__label">Custom field</span>
					<KeyValueField
						keyLabel="Field name"
						valueLabel="Value"
						keyValue={ field.key }
						value={ field.value }
						onChange={ ( next ) =>
							setField( { key: next.key, value: next.value } )
						}
						onRemove={ () => {} }
					/>
				</div>
				<SearchField
					label="Search snippets"
					placeholder="Search snippets, code or tags…"
					value={ search }
					onChange={ setSearch }
					shortcut
				/>
				<FileDropzone
					label="Import file"
					accept=".json"
					title="Drop a .json export here"
					file={ file }
					meta={ file ? '48 KB · 12 snippets' : undefined }
					onFile={ setFile }
					onRemove={ () => setFile( null ) }
				/>
			</div>
		</Section>
	);
}

function DataDisplay() {
	const [ selected, setSelected ] = useState( [ 1, 4, 5 ] );
	const [ sort, setSort ] = useState( { key: 'updated', direction: 'desc' } );
	const [ page, setPage ] = useState( 1 );
	const [ rows, setRows ] = useState( ROWS );
	const toggle = ( row, value ) =>
		setRows(
			rows.map( ( item ) =>
				item.id === row.id ? { ...item, active: value } : item
			)
		);
	return (
		<Section title="sd-TypeChip · sd-Badge · sd-StatusDot · sd-StatTile · sd-Table · sd-BulkBar">
			<Row label="type chips">
				{ [ 'php', 'html', 'css', 'js', 'universal' ].map( ( type ) => (
					<TypeChip key={ type } type={ type } />
				) ) }
				<TypeChip type="php" size="sm" />
				<TypeChip type="css" size="sm" />
			</Row>
			<Row label="badges">
				<Badge tone="danger" icon="shield-review">
					Needs review
				</Badge>
				<Badge tone="danger" icon="alert">
					Error
				</Badge>
				<Badge>Test mode</Badge>
				<Badge tone="muted">Conditional</Badge>
				<Badge icon="clock">Scheduled</Badge>
				<Badge tone="warning">Expired</Badge>
				<Badge tone="muted">Consent: Marketing</Badge>
				<Badge tone="success">Cached file</Badge>
				<Status state="running">Running</Status>
				<Status state="inactive">Inactive</Status>
				<Status state="error">Error</Status>
			</Row>
			<div className="gallery-tiles">
				<StatTile
					label="Running"
					value={ 20 }
					caption="snippets on the front end"
					running
					href="#"
				/>
				<StatTile
					label="Inactive"
					value={ 3 }
					caption="saved but switched off"
					href="#"
				/>
				<StatTile
					label="Errors"
					value={ 1 }
					caption="Returning-customer discount"
					tone="danger"
					href="#"
				/>
				<StatTile
					label="Page scripts"
					value={ 4 }
					caption="pages with their own code"
					href="#"
				/>
			</div>
			<Table
				caption="Snippets"
				rows={ rows }
				selection={ {
					selected,
					onChange: setSelected,
					label: ( row ) => `Select ${ row.title }`,
				} }
				sort={ {
					...sort,
					onChange: ( key, direction ) =>
						setSort( { key, direction } ),
				} }
				onRowClick={ ( row ) =>
					window.console.log( 'open', row.title )
				}
				rowClassName={ ( row ) => ( row.error ? 'is-danger' : '' ) }
				columns={ [
					{
						key: 'status',
						label: 'Status',
						width: '60px',
						render: ( row ) => (
							<Switch
								checked={ !! row.active }
								paused={ row.paused }
								locked={ row.locked }
								busy={ row.busy }
								label={ `${ row.title } ${ row.active ? 'active' : 'inactive' }` }
								onChange={ ( value ) => toggle( row, value ) }
								onBlockedClick={ () =>
									window.console.log(
										'open history',
										row.title
									)
								}
							/>
						),
					},
					{
						key: 'title',
						label: 'Snippet',
						width: '32%',
						sortable: true,
						render: ( row ) => (
							<span className="sd-table__title">
								<a className="sd-table__primary" href="#edit">
									{ row.title }
								</a>
								<span
									className={
										row.subDanger
											? 'sd-table__sub is-danger'
											: 'sd-table__sub'
									}
								>
									{ row.sub }
								</span>
							</span>
						),
					},
					{
						key: 'type',
						label: 'Type',
						width: '116px',
						render: ( row ) => (
							<TypeChip type={ row.type } size="sm" />
						),
					},
					{
						key: 'where',
						label: 'Where it runs',
						render: ( row ) => (
							<span className="sd-table__title">
								<span className="sd-small gallery-strong">
									{ row.where }
								</span>
								<span className="sd-table__sub">
									{ row.scope }
								</span>
							</span>
						),
					},
					{
						key: 'badges',
						label: 'Badges',
						render: ( row ) => (
							<Badge
								tone={ row.badge[ 1 ] }
								icon={ row.badge[ 2 ] }
								size="sm"
							>
								{ row.badge[ 0 ] }
							</Badge>
						),
					},
					{
						key: 'updated',
						label: 'Updated',
						width: '96px',
						sortable: true,
						render: ( row ) => (
							<span className="sd-table__muted">
								{ row.updated }
							</span>
						),
					},
					{
						key: 'menu',
						label: 'Actions',
						hideLabel: true,
						width: '48px',
						render: ( row ) => (
							<DropdownMenu
								label={ `Actions for ${ row.title }` }
								align="end"
								items={ [
									{ label: 'Edit', icon: 'edit' },
									{ label: 'Duplicate', icon: 'copy' },
									{ label: 'Export', icon: 'upload' },
									{
										label: 'History',
										icon: 'history',
										shortcut: '⌘H',
									},
									{
										label: 'Copy shortcode',
										icon: 'shortcode',
										disabled: true,
										reason: 'Only for "Shortcode or block only" snippets',
									},
									{ separator: true },
									{
										label: 'Move to trash',
										icon: 'trash',
										danger: true,
									},
								] }
							/>
						),
					},
				] }
				footer={
					<Pagination
						page={ page }
						perPage={ 20 }
						total={ 24 }
						onChange={ setPage }
					/>
				}
			/>
			<div className="gallery-center">
				<BulkBar
					count={ selected.length }
					onClear={ () => setSelected( [] ) }
				>
					<Button variant="on-ink">Activate</Button>
					<Button variant="on-ink">Deactivate</Button>
					<Button variant="on-ink">Add tag</Button>
					<Button variant="on-ink">Export</Button>
					<Button variant="on-ink" className="sd-button--danger-text">
						Move to trash
					</Button>
				</BulkBar>
			</div>
			<Table
				caption="Loading"
				rows={ [] }
				loading
				skeletonRows={ 2 }
				density="compact"
				columns={ [
					{ key: 'status', label: 'Status', width: '60px' },
					{ key: 'title', label: 'Snippet' },
					{ key: 'type', label: 'Type', width: '116px' },
					{ key: 'where', label: 'Where it runs' },
				] }
			/>
		</Section>
	);
}

function Feedback() {
	const toast = useToast();
	const [ confirm, setConfirm ] = useState( false );
	return (
		<Section title="sd-Banner · sd-Toast · sd-ConfirmDialog · sd-EmptyState · sd-ProgressRing">
			<div className="gallery-stack">
				<Banner
					tone="danger"
					title={
						'“Returning-customer discount” was switched off to keep your site running'
					}
					actions={
						<>
							<Button variant="primary" size="compact">
								Fix it
							</Button>
							<Button variant="tertiary" size="compact">
								Dismiss
							</Button>
						</>
					}
				>
					<code>
						Call to undefined function wc_get_customer_order_count()
					</code>{ ' ' }
					on line 12, while loading <code>/cart/</code>. We emailed
					maya@lumencoffee.com.
				</Banner>
				<Banner
					tone="warning"
					actions={
						<Button variant="outline" size="compact">
							Exit safe mode
						</Button>
					}
				>
					<strong>Safe mode is on in this browser.</strong> No
					snippets are running for you, so you can fix things.
				</Banner>
				<Banner
					tone="success"
					actions={ <a href="#imported">View imported snippets</a> }
				>
					<strong>Imported 12 snippets · 9 active.</strong> Two need a
					look.
				</Banner>
				<Banner tone="info">
					No consent plugin detected. Snippets set to wait for consent
					will not load until one is active.
				</Banner>
				<Banner tone="warning" inline>
					Device and logged-out rules may not work with full-page
					caching.
				</Banner>
			</div>
			<Row label="toast · dialog">
				<Button
					onClick={ () =>
						toast.show( { message: 'Snippet saved and active' } )
					}
				>
					Show success toast
				</Button>
				<Button
					onClick={ () =>
						toast.show( {
							message: 'Couldn’t activate “Hotjar (old)”',
							tone: 'error',
							action: { label: 'Retry', onClick: () => {} },
						} )
					}
				>
					Show error toast
				</Button>
				<Button variant="danger" onClick={ () => setConfirm( true ) }>
					Approve all…
				</Button>
			</Row>
			<ConfirmDialog
				open={ confirm }
				title="Approve all 1 flagged snippet?"
				onCancel={ () => setConfirm( false ) }
				actions={
					<>
						<Button
							variant="secondary"
							onClick={ () => setConfirm( false ) }
						>
							Cancel
						</Button>
						<Button
							variant="danger-solid"
							onClick={ () => setConfirm( false ) }
						>
							Approve all
						</Button>
					</>
				}
				aside={ <a href="#history">View history</a> }
			>
				Approving marks the current code as trusted and lets it run
				again. If you did not make these changes, review the history
				first.
			</ConfirmDialog>
			<div className="gallery-grid">
				<EmptyState
					title="Add code to your site in seconds"
					text="Tracking pixels, custom CSS, PHP hooks. ScriptDock tests every snippet before it goes live."
					actions={
						<>
							<Button variant="primary" dot="plus">
								New snippet
							</Button>
							<Button>Browse the library</Button>
							<Button variant="tertiary">Import</Button>
						</>
					}
				/>
				<div className="gallery-stack">
					<Card>
						<div className="gallery-inline">
							<ProgressRing
								value={ 3 }
								max={ 4 }
								label="3 of 4 steps done"
							/>
							<div>
								<strong className="sd-h3">
									Getting started
								</strong>
								<p className="sd-small">
									One step left: choose which content types
									get page scripts.
								</p>
							</div>
						</div>
					</Card>
					<ProgressBar
						label="Importing from WPCode"
						value={ 9 }
						max={ 12 }
						count="9 of 12"
					/>
					<SkeletonCard />
					<EmptyState
						compact
						title={ 'No snippets match “checkout banner”' }
						text="Try fewer words, or clear the Location filter."
						actions={ <Button>Clear filters</Button> }
					/>
				</div>
			</div>
		</Section>
	);
}

const CONTENT_TABS = [
	{ id: 'pages', label: 'Pages', count: 3, filled: true },
	{ id: 'posts', label: 'Posts', count: 'All' },
	{ id: 'products', label: 'Products' },
	{ id: 'urls', label: 'URL rules', disabled: true },
];

const SLOT_TABS = [
	{ id: 'head', label: 'Header', dot: 'has' },
	{ id: 'body', label: 'Body' },
	{ id: 'footer', label: 'Footer' },
	{ id: 'css', label: 'CSS', dot: 'has' },
	{ id: 'site', label: 'Site-wide snippets', dot: 'warning' },
];

function Navigation() {
	const [ tab, setTab ] = useState( 'pages' );
	const [ pill, setPill ] = useState( 'all' );
	const [ slot, setSlot ] = useState( 'head' );
	return (
		<Section title="sd-Stepper · sd-Tabs · sd-Breadcrumb">
			<Stepper
				label="Targeting steps"
				onStep={ () => {} }
				steps={ [
					{
						id: 'placement',
						label: 'Placement',
						note: 'complete',
						state: 'complete',
					},
					{
						id: 'pages',
						label: 'Pages & content',
						note: 'current',
						state: 'current',
					},
					{
						id: 'audience',
						label: 'Audience',
						note: 'Optional',
						state: 'upcoming',
					},
					{
						id: 'schedule',
						label: 'Schedule',
						note: 'Optional',
						state: 'upcoming',
					},
					{
						id: 'review',
						label: 'Review',
						note: 'error state',
						state: 'error',
					},
				] }
			/>
			<div className="gallery-narrow">
				<StepperCompact
					index={ 2 }
					total={ 5 }
					name="Pages & content"
				/>
			</div>
			<div className="gallery-grid">
				<div>
					<Tabs
						label="Content"
						idPrefix="gallery-content"
						selected={ tab }
						onSelect={ setTab }
						tabs={ CONTENT_TABS }
					/>
					{ CONTENT_TABS.map( ( item ) => (
						<TabPanel
							key={ item.id }
							idPrefix="gallery-content"
							id={ item.id }
							hidden={ item.id !== tab }
						>
							<p className="sd-small">{ item.label } panel</p>
						</TabPanel>
					) ) }
				</div>
				<Pills
					label="Status"
					current={ pill }
					onSelect={ setPill }
					items={ [
						{ id: 'all', label: 'All', count: 24 },
						{ id: 'active', label: 'Active', count: 20 },
						{ id: 'inactive', label: 'Inactive', count: 3 },
						{
							id: 'review',
							label: 'Needs review',
							count: 1,
							tone: 'danger',
						},
						{ id: 'trash', label: 'Trash', count: 2 },
					] }
				/>
				<div className="gallery-inline">
					<Tabs
						label="Page code"
						variant="vertical"
						idPrefix="gallery-slots"
						selected={ slot }
						onSelect={ setSlot }
						tabs={ SLOT_TABS }
					/>
					{ SLOT_TABS.map( ( item ) => (
						<TabPanel
							key={ item.id }
							idPrefix="gallery-slots"
							id={ item.id }
							hidden={ item.id !== slot }
						>
							<p className="sd-small">{ item.label } code</p>
						</TabPanel>
					) ) }
				</div>
				<Breadcrumb
					items={ [
						{ label: 'Snippets', href: '#' },
						{ label: 'GA4 tag' },
					] }
				/>
			</div>
		</Section>
	);
}

function Overlays() {
	const [ modal, setModal ] = useState( false );
	const [ drawer, setDrawer ] = useState( false );
	const [ wide, setWide ] = useState( false );
	const [ sheet, setSheet ] = useState( false );
	return (
		<Section title="sd-Modal · sd-Drawer · sd-BottomSheet · sd-DropdownMenu">
			<Row label="open">
				<Button onClick={ () => setModal( true ) }>Modal</Button>
				<Button onClick={ () => setDrawer( true ) }>Drawer 560</Button>
				<Button onClick={ () => setWide( true ) }>Drawer 720</Button>
				<Button onClick={ () => setSheet( true ) }>Bottom sheet</Button>
				<DropdownMenu
					label="Snippet actions"
					renderToggle={ ( props ) => (
						<Button { ...props }>Row menu</Button>
					) }
					items={ [
						{ label: 'Edit', icon: 'edit' },
						{ label: 'Duplicate', icon: 'copy' },
						{ label: 'History', icon: 'history', shortcut: '⌘H' },
						{ separator: true },
						{ label: 'Move to trash', icon: 'trash', danger: true },
					] }
				/>
				<Tooltip text="Changed outside ScriptDock 1 hour ago">
					<button
						type="button"
						className="sd-button sd-button--secondary"
					>
						Hover for tooltip
					</button>
				</Tooltip>
			</Row>
			<Modal
				open={ modal }
				onClose={ () => setModal( false ) }
				title="Where should GA4 tag run?"
				tinted
				footer={
					<>
						<Button onClick={ () => setModal( false ) }>
							Back
						</Button>
						<span className="sd-modal__footer-note">
							Step 2 of 5
						</span>
						<Button variant="primary" dot="chevron-right">
							Next
						</Button>
					</>
				}
			>
				<p className="sd-small">
					Scrolling body. The full-screen variant covers the WordPress
					toolbar at z-index 100010.
				</p>
			</Modal>
			<Drawer
				open={ drawer }
				onClose={ () => setDrawer( false ) }
				title="GA4 tag"
				subtitle="HTML · Site header · Active"
				closeLabel="Close quick view"
			>
				<p className="sd-small">
					Quick view: code preview, targeting summary, last error,
					history link.
				</p>
			</Drawer>
			<Drawer
				open={ wide }
				onClose={ () => setWide( false ) }
				wide
				title="History · Hotjar (old)"
				subtitle="12 revisions kept. Older ones are removed automatically."
				closeLabel="Close history"
			>
				<p className="sd-small">History drawer, 720px.</p>
			</Drawer>
			<BottomSheet
				open={ sheet }
				onClose={ () => setSheet( false ) }
				title="3 pages selected"
			>
				<p className="sd-small">
					Mobile filters and targeting summary.
				</p>
				<Button variant="primary" onClick={ () => setSheet( false ) }>
					Done
				</Button>
			</BottomSheet>
		</Section>
	);
}

function Code() {
	const [ html, setHtml ] = useState( GA4_HTML );
	const [ php, setPhp ] = useState( WHOLESALE_PHP );
	const [ theme, setTheme ] = useState( 'light' );
	const editor = useRef();
	const fakePhpLint = ( code ) =>
		new Promise( ( resolve ) =>
			setTimeout(
				() =>
					resolve(
						/return \$price\n/.test( code )
							? {
									line: 3,
									message: "syntax error, unexpected '}'",
								}
							: null
					),
				300
			)
		);
	return (
		<Section title="sd-CodeEditor · sd-LintStatus · sd-CodePreview · sd-SmartTagPalette">
			<div className="gallery-grid gallery-grid--wide">
				<CodeEditor
					value={ html }
					onChange={ setHtml }
					type="html"
					fileName="ga4-tag.html"
					theme={ theme }
					onThemeChange={ setTheme }
					settings={ editorSettings.html }
					smartTags={ SMART_TAGS }
					onSave={ () => window.console.log( 'save' ) }
				/>
				<CodeEditor
					ref={ editor }
					value={ php }
					onChange={ setPhp }
					type="php"
					fileName="wholesale-prices.php"
					theme="dark"
					settings={ editorSettings.php }
					lintPhp={ fakePhpLint }
					onSave={ () => window.console.log( 'save' ) }
				/>
			</div>
			<Row label="lint status">
				<LintStatus state="ok" size="lg" />
				<LintStatus
					state="error"
					size="lg"
					text="Line 3 · unexpected '}'"
				/>
				<LintStatus state="checking" size="lg" />
				<LintStatus state="readonly" size="lg" />
				<Button
					size="sm"
					onClick={ () => editor.current.jumpToLine( 3 ) }
				>
					Jump to line 3
				</Button>
			</Row>
			<div className="gallery-grid">
				<CodePreview
					code={ GA4_TEMPLATE }
					type="html"
					numbered
					note="Highlighted parts are filled in from the fields on the right. You can edit the whole snippet after adding it."
				/>
				<SmartTagPalette
					groups={ SMART_TAGS }
					onInsert={ ( tag ) => window.console.log( 'insert', tag ) }
				/>
			</div>
		</Section>
	);
}

function Surfaces() {
	const [ id, setId ] = useState( '' );
	const [ keep, setKeep ] = useState( false );
	const [ auto, setAuto ] = useState( true );
	const [ email, setEmail ] = useState( true );
	const [ dirty, setDirty ] = useState( true );
	return (
		<Section title="sd-TemplateCard · sd-PluginSourceRow · sd-SettingsRow · sd-KeyValueList · sd-TargetingSentence · sd-SaveBar">
			<div className="gallery-grid">
				<TemplateCard
					title="Google Analytics 4"
					description="Adds the GA4 gtag to every page and passes the page title through."
					chips={
						<>
							<TypeChip type="html" size="sm" />
							<span className="sd-template__chip">
								Site header
							</span>
							<span className="sd-template__chip">
								Waits for consent
							</span>
						</>
					}
				>
					<TextField
						label="Measurement ID"
						hideLabel
						mono
						placeholder="G-XXXXXXXXXX"
						value={ id }
						onChange={ setId }
					/>
					<div className="sd-template__footer">
						<Button dot="plus">Add</Button>
					</div>
				</TemplateCard>
				<TemplateCard
					title="Meta Pixel"
					description="Base pixel code for Facebook and Instagram ads."
					state="added"
					openHref="#snippet"
				/>
				<TemplateCard
					title="GA4 purchase event"
					description="Sends order value and items on the thank-you page."
					state="blocked"
					blockedLabel="Requires WooCommerce"
				/>
			</div>
			<div className="gallery-grid">
				<div className="gallery-stack">
					<PluginSourceRow
						name="WPCode"
						badge={
							<Badge tone="warning" size="sm">
								Still active
							</Badge>
						}
						meta="12 snippets found · 9 would be active"
						actions={
							<>
								<SwitchField
									label="Keep active"
									size="sm"
									checked={ keep }
									onChange={ setKeep }
								/>
								<Button variant="primary" size="compact">
									Import 12
								</Button>
							</>
						}
					/>
					<div className="sd-settings-group">
						<SettingsRow
							title="Switch off broken snippets automatically"
							description="If a snippet causes a fatal error, ScriptDock disables it instead of letting your site go down. Strongly recommended."
							control={
								<Switch
									checked={ auto }
									onChange={ setAuto }
									label="Auto-deactivate broken snippets"
								/>
							}
						/>
						<SettingsRow
							title="Email me when that happens"
							description="Sent to maya@lumencoffee.com with the error, the line number and a link to fix it."
							control={
								<Switch
									checked={ email }
									onChange={ setEmail }
									label="Email alerts"
								/>
							}
						/>
					</div>
				</div>
				<Card>
					<KeyValueList
						title="Where it runs"
						items={ [
							{
								icon: 'head',
								label: 'Placement',
								value: 'Site header',
							},
							{
								icon: 'page',
								label: 'Pages',
								value: '3 pages, all posts in News',
							},
							{
								icon: 'user',
								label: 'Audience',
								value: 'Logged-out visitors on mobile',
							},
							{
								icon: 'clock',
								label: 'Schedule',
								value: 'Mon–Fri 09:00–17:00 until Oct 31',
							},
						] }
					/>
				</Card>
			</div>
			<div className="gallery-sentence">
				<TargetingSentence
					size="lg"
					parts={ [
						'Runs in the ',
						{ label: 'site header', href: '#placement' },
						' on ',
						{ label: '3 pages', href: '#pages' },
						' and ',
						{ label: 'all posts in News', href: '#pages' },
						', for ',
						{ label: 'logged-out visitors', href: '#audience' },
						' on ',
						{ label: 'mobile & tablet', href: '#audience' },
						', ',
						{
							label: 'no schedule yet',
							href: '#schedule',
							unset: true,
						},
						'.',
					] }
				/>
			</div>
			{ dirty && (
				<SaveBar
					label="Unsaved changes in Header"
					onDiscard={ () => setDirty( false ) }
					onSave={ () => setDirty( false ) }
				/>
			) }
			{ ! dirty && (
				<Button onClick={ () => setDirty( true ) }>
					Show the save bar again
				</Button>
			) }
		</Section>
	);
}

function Illustrations() {
	return (
		<Section
			title="sd-Illustration"
			note="One set: 1.2px ink lines, two lighter strengths, one ember dot (r 10, glow r 20). The dot means your code."
		>
			<div className="gallery-illustrations">
				<figure>
					<Illustration name="welcome" />
					<figcaption>
						Welcome · onboarding and the Overview hero
					</figcaption>
				</figure>
				<figure>
					<Illustration name="empty-snippets" />
					<figcaption>
						Empty snippets · the list’s empty state
					</figcaption>
				</figure>
				<figure>
					<Illustration name="crash-recovered" />
					<figcaption>
						Crash recovered · after a snippet is switched off
					</figcaption>
				</figure>
			</div>
			<Row label="at 150px">
				<Illustration name="welcome" width={ 150 } />
				<Illustration name="empty-snippets" width={ 150 } />
				<Illustration name="crash-recovered" width={ 150 } />
			</Row>
		</Section>
	);
}

function Menus() {
	const [ types, setTypes ] = useState( [ 'html' ] );
	const [ places, setPlaces ] = useState( [ 'site_header' ] );
	const [ sort, setSort ] = useState( 'modified' );
	const toggle = ( list, setList, value ) => ( on ) =>
		setList(
			on ? [ ...list, value ] : list.filter( ( item ) => item !== value )
		);
	return (
		<Section
			title="sd-DropdownMenu · choices"
			note="Checkbox items keep the menu open; radio items close it; groups are labelled for screen readers."
		>
			<Row label="filters">
				<DropdownMenu
					label="Type"
					renderToggle={ ( props ) => (
						<Button { ...props }>Type ({ types.length })</Button>
					) }
					items={ [ 'php', 'html', 'css', 'js' ].map( ( type ) => ( {
						type: 'checkbox',
						label: type.toUpperCase(),
						hint: 3,
						checked: types.includes( type ),
						onChange: toggle( types, setTypes, type ),
					} ) ) }
				/>
				<DropdownMenu
					label="Location"
					renderToggle={ ( props ) => (
						<Button { ...props }>
							Location ({ places.length })
						</Button>
					) }
					items={ [
						{
							group: 'Page layout',
							items: [ 'site_header', 'site_footer' ].map(
								( key ) => ( {
									type: 'checkbox',
									label:
										key === 'site_header'
											? 'Site header'
											: 'Site footer',
									checked: places.includes( key ),
									onChange: toggle( places, setPlaces, key ),
								} )
							),
						},
						{
							group: 'Inside content',
							items: [
								{
									type: 'checkbox',
									label: 'Before the content',
									checked: places.includes( 'before' ),
									onChange: toggle(
										places,
										setPlaces,
										'before'
									),
								},
							],
						},
					] }
				/>
				<DropdownMenu
					label="Sort by"
					align="end"
					renderToggle={ ( props ) => (
						<Button { ...props }>Sort: { sort }</Button>
					) }
					items={ [ 'modified', 'title', 'priority' ].map(
						( key ) => ( {
							type: 'radio',
							label: key,
							checked: sort === key,
							onClick: () => setSort( key ),
						} )
					) }
				/>
			</Row>
		</Section>
	);
}

const SECTIONS = {
	illustrations: Illustrations,
	menus: Menus,
	buttons: Buttons,
	switches: Switches,
	selection: Selection,
	inputs: Inputs,
	data: DataDisplay,
	feedback: Feedback,
	navigation: Navigation,
	overlays: Overlays,
	code: Code,
	surfaces: Surfaces,
};

function Gallery() {
	// ?only=table renders one section, for screenshots.
	const only = new window.URLSearchParams( window.location.search ).get(
		'only'
	);
	const shown = only && SECTIONS[ only ] ? [ only ] : Object.keys( SECTIONS );
	return (
		<ToastProvider>
			<div className="gallery">
				<div className="gallery-intro">
					<h1 className="sd-h1">Component library</h1>
					<p className="sd-body">
						Every shared component, in every state. Dev only:
						compare with ScriptDock Components.dc.html.
					</p>
				</div>
				{ shown.map( ( key ) => {
					const Part = SECTIONS[ key ];
					return <Part key={ key } />;
				} ) }
			</div>
		</ToastProvider>
	);
}

const root = document.getElementById( 'sd-gallery' );
if ( root ) {
	createRoot( root ).render( <Gallery /> );
}
