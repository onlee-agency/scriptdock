/**
 * S08a: the ScriptDock panel in the block editor sidebar.
 *
 * Two things live here: the code this page carries on its own, and which
 * site-wide snippets are allowed to run on it. Both are part of the post, so
 * nothing saves until the writer updates the page.
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Banner, Icon, Switch, TypeChip } from '../components';
import usePageCode, { lineCount } from './page-code';
import PageWindow from './page-window';

/**
 * @param {Object} props      Props.
 * @param {Object} props.boot Panel bootstrap data.
 * @return {Element} The panel.
 */
export default function Panel( { boot } ) {
	const { data, update, title } = usePageCode( boot.metaKey );
	const [ open, setOpen ] = useState( '' );
	const off = data.disable_all ? boot.snippets.length : data.disable.length;

	const toggleSnippet = ( id, on ) =>
		update( {
			disable: on
				? data.disable.filter( ( item ) => item !== id )
				: [ ...data.disable, id ],
		} );

	return (
		<div className="sd-app sd-pagepanel">
			{ ! boot.trusted && (
				<div className="sd-pagepanel__section">
					<Banner tone="danger" inline>
						{ __(
							'The code on this page was changed outside ScriptDock, so it is not running. Look it over — updating the page approves it.',
							'scriptdock'
						) }
					</Banner>
				</div>
			) }

			<div className="sd-pagepanel__section">
				<h3 className="sd-pagepanel__heading">
					{ __( 'Page code', 'scriptdock' ) }
				</h3>
				<ul className="sd-pagepanel__slots">
					{ boot.slots.map( ( slot ) => {
						const lines = lineCount( data[ slot.key ] );
						return (
							<li key={ slot.key } className="sd-pagepanel__slot">
								<button
									type="button"
									className="sd-pagepanel__slot-open"
									onClick={ () => setOpen( slot.key ) }
								>
									<span className="sd-pagepanel__slot-name">
										{ slot.label }
									</span>
									<span
										className={
											lines
												? 'sd-pagepanel__slot-state is-filled'
												: 'sd-pagepanel__slot-state'
										}
									>
										{ lines > 0 && (
											<span
												className="sd-pagepanel__slot-dot"
												aria-hidden="true"
											/>
										) }
										{ lines
											? sprintf(
													/* translators: %d: number of lines of code. */
													_n(
														'%d line',
														'%d lines',
														lines,
														'scriptdock'
													),
													lines
												)
											: __( 'Empty', 'scriptdock' ) }
									</span>
								</button>
							</li>
						);
					} ) }
				</ul>
				<button
					type="button"
					className="sd-pagepanel__edit"
					onClick={ () => setOpen( boot.slots[ 0 ].key ) }
				>
					{ __( 'Edit page code', 'scriptdock' ) }
				</button>
			</div>

			<div className="sd-pagepanel__section">
				<h3 className="sd-pagepanel__heading">
					{ __( 'Site-wide snippets here', 'scriptdock' ) }
				</h3>

				{ boot.snippets.length === 0 ? (
					<p className="sd-pagepanel__empty">
						{ __(
							'No site-wide snippets are running at the moment.',
							'scriptdock'
						) }
					</p>
				) : (
					<ul className="sd-pagepanel__snippets">
						{ boot.snippets.map( ( snippet ) => {
							const on =
								! data.disable_all &&
								! data.disable.includes( snippet.id );
							return (
								<li
									key={ snippet.id }
									className={
										on
											? 'sd-pagepanel__snippet'
											: 'sd-pagepanel__snippet is-off'
									}
								>
									<TypeChip
										type={ snippet.type }
										size="xs"
										label={ shortType( snippet ) }
									/>
									<span
										className="sd-pagepanel__snippet-name"
										dir="auto"
										title={ `${ snippet.title } · ${ snippet.location }` }
									>
										{ snippet.title }
									</span>
									<Switch
										size="sm"
										checked={ on }
										paused={ data.disable_all }
										label={ sprintf(
											/* translators: %s: snippet title. */
											__(
												'Run %s on this page',
												'scriptdock'
											),
											snippet.title
										) }
										onChange={ ( next ) =>
											toggleSnippet( snippet.id, next )
										}
									/>
								</li>
							);
						} ) }
					</ul>
				) }

				{ off > 0 && (
					<Banner tone="warning" inline>
						{ sprintf(
							/* translators: %d: number of snippets switched off. */
							_n(
								'%d snippet is off here. It still runs everywhere else.',
								'%d snippets are off here. They still run everywhere else.',
								off,
								'scriptdock'
							),
							off
						) }
					</Banner>
				) }

				<div className="sd-pagepanel__all">
					<span id="sd-pagepanel-all">
						{ __(
							'Turn off all site-wide code here',
							'scriptdock'
						) }
					</span>
					<Switch
						size="sm"
						checked={ data.disable_all }
						aria-labelledby="sd-pagepanel-all"
						onChange={ ( next ) => update( { disable_all: next } ) }
					/>
				</div>
				<p className="sd-pagepanel__note">{ boot.phpNote }</p>
			</div>

			<div className="sd-pagepanel__section sd-pagepanel__section--last">
				<a
					className="sd-pagepanel__manage"
					href={ boot.manageUrl }
					target="_blank"
					rel="noreferrer"
				>
					{ __( 'Manage snippets', 'scriptdock' ) }
					<Icon name="external" size={ 12 } stroke={ 2 } />
					<span className="sd-visually-hidden">
						{ __( '(opens in a new tab)', 'scriptdock' ) }
					</span>
				</a>
			</div>

			{ open && (
				<PageWindow
					boot={ boot }
					data={ data }
					update={ update }
					title={ title }
					slot={ open }
					onClose={ () => setOpen( '' ) }
				/>
			) }
		</div>
	);
}

/**
 * A short type label for the narrow panel: "HTML", "CSS", "JS".
 *
 * @param {Object} snippet Snippet.
 * @return {string} Label.
 */
function shortType( snippet ) {
	return snippet.type === 'js' ? __( 'JS', 'scriptdock' ) : snippet.typeLabel;
}
