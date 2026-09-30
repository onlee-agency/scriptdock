/**
 * "Where it runs": the plain-language sentence, the four facts (placement,
 * pages, audience, schedule) and what the placement needs you to know, such
 * as the shortcode or the hook name. Edit targeting opens the wizard.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import sentenceParts from '../targeting/sentence';
import {
	Button,
	CopyChip,
	Icon,
	Skeleton,
	TargetingSentence,
} from '../components';

const PLACEMENT_ICONS = {
	site_header: 'head',
	site_footer: 'footer',
	before_paragraph: 'paragraph',
	after_paragraph: 'paragraph',
	custom_hook: 'hook',
	shortcode: 'shortcode',
	on_demand: 'run',
	php_everywhere: 'code',
	php_frontend: 'code',
	php_admin: 'code',
};

const FACT_ICONS = {
	content: 'page',
	rules: 'filter',
	audience: 'user',
	time: 'clock',
};

/**
 * @param {Object}   props                 Props.
 * @param {Object}   props.targeting       Targeting from the API, for the
 *                                         draft's placement and rules.
 * @param {boolean}  props.pending         The targeting is being worked out.
 * @param {Object}   props.draft           Draft.
 * @param {Object}   props.item            Saved snippet.
 * @param {Function} props.onEditTargeting Opens the targeting wizard.
 * @param {boolean}  props.canEdit         May change it.
 * @return {Element} The card.
 */
export default function WhereCard( {
	targeting,
	pending,
	draft,
	item,
	onEditTargeting,
	canEdit,
} ) {
	const facts = targeting.facts || [];
	const hook = draft.location_args && draft.location_args.hook;
	const shortcode = item.id ? `[scriptdock id="${ item.id }"]` : '';

	return (
		<section className="sd-card sd-where" aria-labelledby="sd-where-label">
			<div className="sd-where__head">
				<h2 id="sd-where-label" className="sd-overline">
					{ __( 'Where it runs', 'scriptdock' ) }
				</h2>
				{ canEdit && (
					<Button dot="chevron-right" onClick={ onEditTargeting }>
						{ __( 'Edit targeting', 'scriptdock' ) }
					</Button>
				) }
			</div>
			<div className="sd-where__sentence" aria-live="polite">
				{ pending ? (
					<Skeleton width="70%" shimmer />
				) : (
					<TargetingSentence
						parts={ sentenceParts( targeting.parts || [] ) }
						size="lg"
					/>
				) }
			</div>
			<ul className="sd-where__facts">
				{ facts.map( ( fact ) => (
					<li key={ fact.key } className="sd-where__fact">
						<Icon
							name={
								fact.key === 'placement'
									? PLACEMENT_ICONS[ draft.location ] ||
										'window'
									: FACT_ICONS[ fact.key ] || 'info'
							}
							size={ 18 }
							stroke={ 1.5 }
						/>
						<div className="sd-where__fact-text">
							<span className="sd-where__fact-label">
								{ fact.label }
							</span>
							<span className="sd-where__fact-value">
								{ fact.value }
							</span>
						</div>
					</li>
				) ) }
			</ul>

			{ draft.location === 'shortcode' && (
				<div className="sd-where__extra">
					{ shortcode ? (
						<div className="sd-where__shortcode">
							<CopyChip
								value={ shortcode }
								label={ __(
									'Copy the shortcode',
									'scriptdock'
								) }
							/>
							<span className="sd-where__note">
								{ __(
									'or add the ScriptDock Snippet block.',
									'scriptdock'
								) }
							</span>
						</div>
					) : (
						<span className="sd-where__note">
							{ __(
								'Save the snippet to get its shortcode.',
								'scriptdock'
							) }
						</span>
					) }
					{ item.usage && (
						<p className="sd-where__usage">
							<Icon name="info" size={ 16 } stroke={ 1.8 } />
							<span>
								{ item.usage.count
									? sprintf(
											/* translators: %d: number of pages and posts. */
											_n(
												'Used on %d page. Page and audience rules still apply on top.',
												'Used on %d pages. Page and audience rules still apply on top.',
												item.usage.count,
												'scriptdock'
											),
											item.usage.count
										)
									: __(
											'Not placed on any page yet. Page and audience rules still apply on top.',
											'scriptdock'
										) }
							</span>
						</p>
					) }
				</div>
			) }

			{ draft.location === 'custom_hook' && ! hook && (
				<p className="sd-where__warning" role="note">
					<Icon name="warning" size={ 16 } stroke={ 1.8 } />
					{ __(
						'No hook name yet. Add one in targeting before you switch it on.',
						'scriptdock'
					) }
				</p>
			) }
		</section>
	);
}
