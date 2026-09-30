/**
 * What ScriptDock adds to the block editor: the page scripts panel (S08) and
 * the Snippet block (S16).
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { __, _n, sprintf } from '@wordpress/i18n';
import Panel from './panel';
import usePageCode, { pageCount } from './page-code';
import registerSnippetBlock from './snippet-block';

/**
 * ScriptDock's dot, the panel's mark throughout the editor.
 *
 * @return {Element} The dot.
 */
function Dot() {
	return <span className="sd-sidebar-dot" aria-hidden="true" />;
}

/**
 * The sidebar and the button that opens it. The button carries an ember
 * badge while the page has code of its own or snippets switched off.
 *
 * @param {Object} props      Props.
 * @param {Object} props.boot Panel bootstrap data.
 * @return {Element} The sidebar.
 */
function Sidebar( { boot } ) {
	const { data } = usePageCode( boot.metaKey );
	const count = pageCount( data, boot.snippets.length );
	const title = __( 'ScriptDock', 'scriptdock' );

	return (
		<>
			<PluginSidebarMoreMenuItem
				target="scriptdock-page-code"
				icon={ <Dot /> }
			>
				{ title }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="scriptdock-page-code"
				title={ title }
				className="sd-sidebar"
				icon={
					<span className="sd-sidebar-icon">
						<Dot />
						{ count > 0 && (
							<span className="sd-sidebar-icon__badge">
								{ count }
							</span>
						) }
						<span className="screen-reader-text">
							{ count > 0
								? sprintf(
										/* translators: %d: how many things ScriptDock does on this page. */
										_n(
											'%d ScriptDock change on this page',
											'%d ScriptDock changes on this page',
											count,
											'scriptdock'
										),
										count
									)
								: __(
										'Nothing from ScriptDock on this page',
										'scriptdock'
									) }
						</span>
					</span>
				}
			>
				<Panel boot={ boot } />
			</PluginSidebar>
		</>
	);
}

const boot = window.sdBlockEditor || {};

registerSnippetBlock();

if ( boot.page ) {
	registerPlugin( 'scriptdock-page-code', {
		icon: <Dot />,
		render: () => <Sidebar boot={ boot.page } />,
	} );
}
