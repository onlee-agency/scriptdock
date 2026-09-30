/**
 * The small thin-line drawing on a library card, one per category, each with
 * the ember dot. Decorative: the card's title says what it is.
 */

/**
 * @param {Object} props          Props.
 * @param {string} props.category Category key.
 * @param {number} props.size     Size in pixels.
 * @return {Element} The drawing.
 */
export default function CategoryArt( { category, size = 56 } ) {
	const Drawing = DRAWINGS[ category ] || DRAWINGS.content;
	return (
		<svg
			className="sd-catart"
			width={ size }
			height={ size }
			viewBox="0 0 48 48"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			<Drawing />
		</svg>
	);
}

/** A rising line with a marked point: analytics and pixels. */
const Tracking = () => (
	<>
		<path d="M8 36V12" />
		<path d="M8 36h32" />
		<path d="M13 30l7-7 6 5 10-12" />
		<circle cx="36" cy="16" r="3" className="sd-catart__dot" />
	</>
);

/** A gauge with its needle: performance. */
const Performance = () => (
	<>
		<path d="M8 32a16 16 0 0 1 32 0" />
		<path d="M24 32l9-8" />
		<path d="M8 32h6M34 32h6" />
		<circle cx="24" cy="32" r="2.5" className="sd-catart__dot" />
	</>
);

/** A shield: security. */
const Security = () => (
	<>
		<path d="M24 8l13 5v10c0 9-5.6 14.8-13 17-7.4-2.2-13-8-13-17V13z" />
		<path d="M18 24l4.5 4.5L31 20" />
		<circle cx="24" cy="8" r="2.5" className="sd-catart__dot" />
	</>
);

/** Sliders: admin. */
const Admin = () => (
	<>
		<path d="M10 16h28M10 24h28M10 32h28" />
		<circle cx="19" cy="16" r="3" />
		<circle cx="30" cy="24" r="3" className="sd-catart__dot" />
		<circle cx="16" cy="32" r="3" />
	</>
);

/** A page with lines: content and design. */
const Content = () => (
	<>
		<rect x="10" y="8" width="28" height="32" rx="3" />
		<path d="M16 18h16M16 25h16M16 32h10" />
		<circle cx="36" cy="12" r="3" className="sd-catart__dot" />
	</>
);

/** A key: the login page. */
const Login = () => (
	<>
		<circle cx="18" cy="20" r="7" />
		<path d="M23 25l13 13" />
		<path d="M31 33l4-4M35 37l4-4" />
		<circle cx="18" cy="20" r="2.5" className="sd-catart__dot" />
	</>
);

/** A shopping bag: WooCommerce. */
const Woo = () => (
	<>
		<path d="M12 16h24l-2.5 22a3 3 0 0 1-3 2.6H17.5a3 3 0 0 1-3-2.6z" />
		<path d="M19 20v-4a5 5 0 0 1 10 0v4" />
		<circle cx="24" cy="27" r="2.5" className="sd-catart__dot" />
	</>
);

const DRAWINGS = {
	tracking: Tracking,
	performance: Performance,
	security: Security,
	admin: Admin,
	content: Content,
	login: Login,
	woocommerce: Woo,
};
