/**
 * S10b: adding a template. The code on the left with its placeholders marked,
 * what it needs on the right.
 */
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Button,
	CodePreview,
	Modal,
	Select,
	SwitchField,
	TextField,
	TypeChip,
} from '../components';
import { CONSENT_CATEGORIES } from '../editor/consent';
import * as api from './api';

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.template Template being added.
 * @param {Object}   props.consent  Whether a consent plugin is present.
 * @param {Function} props.onClose  Closes the modal.
 * @param {Function} props.onAdded  Called with what was created.
 * @param {Function} props.onError  Called with a message when it fails.
 * @return {Element} The modal.
 */
export default function AddModal( {
	template,
	consent,
	onClose,
	onAdded,
	onError,
} ) {
	const [ values, setValues ] = useState( () => {
		const start = {};
		template.fields.forEach( ( field ) => {
			start[ field.key ] = field.default || '';
		} );
		return start;
	} );
	const [ category, setCategory ] = useState( template.consent || '' );
	const [ activate, setActivate ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ problem, setProblem ] = useState( null );

	const add = () => {
		setBusy( true );
		setProblem( null );
		api.addTemplate( template.id, {
			values,
			activate,
			consent: category,
		} )
			.then( onAdded )
			.catch( ( error ) => {
				setBusy( false );
				if ( error && error.data && error.data.field ) {
					setProblem( {
						field: error.data.field,
						message: error.message,
					} );
					return;
				}
				onError( api.errorMessage( error ) );
			} );
	};

	const parts = template.snippets;

	return (
		<Modal
			open
			size="lg"
			className="sd-addmodal"
			title={ template.title }
			subtitle={ template.description }
			onClose={ onClose }
			footer={
				<>
					<Button
						variant="primary"
						loading={ busy }
						loadingLabel={ __( 'Adding…', 'scriptdock' ) }
						onClick={ add }
					>
						{ parts.length > 1
							? sprintf(
									/* translators: %d: how many snippets it creates. */
									__( 'Add %d snippets', 'scriptdock' ),
									parts.length
								)
							: __( 'Add snippet', 'scriptdock' ) }
					</Button>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Cancel', 'scriptdock' ) }
					</Button>
				</>
			}
		>
			<div className="sd-addmodal__body">
				<div className="sd-addmodal__code">
					{ parts.length > 1 && (
						<p className="sd-addmodal__parts">
							{ sprintf(
								/* translators: %d: how many snippets it creates. */
								_n(
									'This adds %d snippet:',
									'This adds %d snippets:',
									parts.length,
									'scriptdock'
								),
								parts.length
							) }
						</p>
					) }
					{ parts.map( ( part, index ) => (
						<div key={ index } className="sd-addmodal__part">
							<p className="sd-addmodal__partline">
								<TypeChip type={ part.type } />
								<span>{ part.title }</span>
								<span className="sd-addmodal__where">
									{ part.placement }
								</span>
							</p>
							<CodePreview
								code={ fill( part.code, values, template ) }
								type={ part.type }
								numbered
								locked={ false }
								title={ part.title }
							/>
						</div>
					) ) }
				</div>

				<div className="sd-addmodal__form">
					{ template.fields.map( ( field ) => (
						<TextField
							key={ field.key }
							label={ field.label }
							value={ values[ field.key ] }
							placeholder={ field.placeholder }
							help={ field.help }
							error={
								problem && problem.field === field.key
									? problem.message
									: ''
							}
							onChange={ ( value ) =>
								setValues( ( old ) => ( {
									...old,
									[ field.key ]: value,
								} ) )
							}
						/>
					) ) }

					<div className="sd-addmodal__where-card">
						<h3 className="sd-overline">
							{ __( 'Where it runs', 'scriptdock' ) }
						</h3>
						<p className="sd-addmodal__placement">
							{ template.placement }
						</p>
						<p className="sd-addmodal__hint">
							{ __(
								'You can narrow this down in the snippet once it is added.',
								'scriptdock'
							) }
						</p>
					</div>

					<Select
						label={ __( 'Wait for cookie consent', 'scriptdock' ) }
						value={ category }
						onChange={ setCategory }
						options={ CONSENT_CATEGORIES }
						help={
							consent && consent.api
								? ''
								: __(
										'No consent plugin with the WP Consent API was found, so code runs straight away.',
										'scriptdock'
									)
						}
					/>

					<SwitchField
						label={ __( 'Run it straight away', 'scriptdock' ) }
						checked={ activate }
						onChange={ setActivate }
					/>
				</div>
			</div>
		</Modal>
	);
}

/**
 * Puts what has been typed into the code preview, so the snippet is seen as
 * it will be saved.
 *
 * @param {string} code     Template code.
 * @param {Object} values   Field values.
 * @param {Object} template Template.
 * @return {string} Code.
 */
function fill( code, values, template ) {
	let filled = code;
	template.fields.forEach( ( field ) => {
		// An empty field keeps its %%token%%, which the preview marks, so it
		// is clear what still needs filling in.
		const value = ( values[ field.key ] || '' ).trim();
		if ( value ) {
			filled = filled.split( `%%${ field.key }%%` ).join( value );
		}
	} );
	return filled;
}
