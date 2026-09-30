/**
 * S10: the Library. Ready-made snippets that ship with the plugin — nothing
 * is downloaded, and nothing is added until you say so.
 */
import { useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Badge,
	Button,
	EmptyState,
	Illustration,
	Pills,
	SearchField,
	TemplateCard,
	TypeChip,
	useToast,
} from '../components';
import AddModal from './add-modal';
import CategoryArt from './category-art';

/**
 * @param {Object} props      Props.
 * @param {Object} props.boot The library and what the site already has.
 * @return {Element} The screen.
 */
export default function App( { boot } ) {
	const toast = useToast();
	const [ data, setData ] = useState( boot.item );
	const [ category, setCategory ] = useState( 'all' );
	const [ search, setSearch ] = useState( '' );
	const [ adding, setAdding ] = useState( null );

	const shown = useMemo( () => {
		const words = search.trim().toLowerCase();
		return data.templates.filter( ( template ) => {
			if ( category !== 'all' && template.category !== category ) {
				return false;
			}
			if ( ! words ) {
				return true;
			}
			return `${ template.title } ${ template.description }`
				.toLowerCase()
				.includes( words );
		} );
	}, [ data.templates, category, search ] );

	const added = ( result, template ) => {
		setData( ( old ) => ( {
			...old,
			templates: old.templates.map( ( item ) =>
				item.id === template.id
					? {
							...item,
							added: true,
							edit_url: result.created[ 0 ].edit_url,
						}
					: item
			),
		} ) );
		setAdding( null );
		toast.show( {
			message: result.notice.message,
			action: {
				label: __( 'Open snippet', 'scriptdock' ),
				href: result.created[ 0 ].edit_url,
			},
		} );
	};

	return (
		<div className="sd-library">
			<div className="sd-library__hero">
				<div className="sd-library__heading">
					<h1 className="sd-screen__title">
						{ __( 'Library', 'scriptdock' ) }
						<span className="sd-count-chip">
							{ sprintf(
								/* translators: %d: number of templates. */
								_n(
									'%d ready-made snippet',
									'%d ready-made snippets',
									data.total,
									'scriptdock'
								),
								data.total
							) }
						</span>
					</h1>
					<p className="sd-library__intro">
						{ __(
							'Everything here ships with the plugin. Nothing is downloaded, and nothing runs until you add it.',
							'scriptdock'
						) }
					</p>
				</div>
				<span className="sd-library__glow" aria-hidden="true" />
			</div>

			<div className="sd-library__tools">
				<Pills
					label={ __( 'Categories', 'scriptdock' ) }
					items={ data.categories.map( ( entry ) => ( {
						id: entry.key,
						label: entry.label,
						count: entry.count,
					} ) ) }
					current={ category }
					onSelect={ setCategory }
				/>
				<SearchField
					label={ __( 'Search the library', 'scriptdock' ) }
					value={ search }
					onChange={ setSearch }
					placeholder={ __( 'Search templates…', 'scriptdock' ) }
				/>
			</div>

			{ shown.length === 0 ? (
				<EmptyState
					illustration={ <Illustration name="empty-snippets" /> }
					title={ __( 'Nothing matches that', 'scriptdock' ) }
					text={ __(
						'Try fewer words, or another category.',
						'scriptdock'
					) }
					actions={
						<Button
							onClick={ () => {
								setSearch( '' );
								setCategory( 'all' );
							} }
						>
							{ __( 'Show everything', 'scriptdock' ) }
						</Button>
					}
				/>
			) : (
				<section
					className="sd-library__grid"
					aria-labelledby="sd-library-grid"
				>
					<h2 id="sd-library-grid" className="sd-visually-hidden">
						{ __( 'Templates', 'scriptdock' ) }
					</h2>
					{ shown.map( ( template ) => (
						<TemplateCard
							key={ template.id }
							title={ template.title }
							description={ template.description }
							art={
								<CategoryArt category={ template.category } />
							}
							state={ state( template ) }
							blockedLabel={ template.blocked }
							openHref={ template.edit_url }
							chips={
								<>
									{ template.types.map( ( type ) => (
										<TypeChip key={ type } type={ type } />
									) ) }
									<Badge tone="muted" size="sm">
										{ template.placement }
									</Badge>
									{ template.consent && (
										<Badge tone="info" size="sm">
											{ __(
												'Waits for consent',
												'scriptdock'
											) }
										</Badge>
									) }
								</>
							}
						>
							<Button
								variant="secondary"
								size="compact"
								dot="plus"
								onClick={ () => setAdding( template ) }
							>
								{ __( 'Add', 'scriptdock' ) }
								<span className="sd-visually-hidden">
									{ ' ' + template.title }
								</span>
							</Button>
						</TemplateCard>
					) ) }
				</section>
			) }

			{ adding && (
				<AddModal
					template={ adding }
					consent={ boot.consent }
					onClose={ () => setAdding( null ) }
					onAdded={ ( result ) => added( result, adding ) }
					onError={ ( message ) =>
						toast.show( { tone: 'error', message } )
					}
				/>
			) }
		</div>
	);
}

/**
 * Whether a card offers Add, says it is already here, or explains why it
 * cannot be used.
 *
 * @param {Object} template Template.
 * @return {string} default, added or blocked.
 */
function state( template ) {
	if ( template.blocked ) {
		return 'blocked';
	}
	return template.added ? 'added' : 'default';
}
