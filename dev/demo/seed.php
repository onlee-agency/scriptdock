<?php
/**
 * DEV ONLY - never ships. Seeds the Lumen Coffee demo site with the sample
 * data from design-brief/05-sample-data.md: people, pages, posts, products,
 * and the 24 snippets with their states (paused for review, switched off
 * after an error, two in the trash) and believable times.
 *
 * Run once on a fresh install, from dev/demo:
 *   docker compose run --rm -v "$PWD:/demo" cli eval-file /demo/seed.php --user=maya
 *
 * @package ScriptDock
 */

use ScriptDock\Post_Type;
use ScriptDock\Snippet;

if ( get_posts( array( 'post_type' => Post_Type::NAME, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) ) ) {
	WP_CLI::error( 'This site already has snippets. Seed a fresh install.' );
}

global $wpdb;
$now = time();

/**
 * Sets a post's dates, as if it was last saved $ago seconds ago.
 */
$age = static function ( $id, $ago, $created = null ) use ( $wpdb, $now ) {
	$modified = $now - $ago;
	$created  = null === $created ? $modified : $now - $created;
	$wpdb->update(
		$wpdb->posts,
		array(
			'post_date'         => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $created ) ),
			'post_date_gmt'     => gmdate( 'Y-m-d H:i:s', $created ),
			'post_modified'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $modified ) ),
			'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', $modified ),
		),
		array( 'ID' => $id )
	);
	clean_post_cache( $id );
};

$minute = MINUTE_IN_SECONDS;
$hour   = HOUR_IN_SECONDS;
$day    = DAY_IN_SECONDS;
$week   = WEEK_IN_SECONDS;
$month  = MONTH_IN_SECONDS;

WP_CLI::log( 'Site and people' );
update_option( 'blogname', 'Lumen Coffee Roasters' );
update_option( 'blogdescription', 'Small-batch coffee, roasted fresh every week' );
update_option( 'timezone_string', 'America/New_York' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'date_format', 'M j, Y' );
// The demo is an established site, so first-run setup is already behind it.
ScriptDock\Admin\Onboarding_Page::finish();

$maya = get_user_by( 'login', 'maya' );
wp_update_user(
	array(
		'ID'           => $maya->ID,
		'display_name' => 'Maya Chen',
		'first_name'   => 'Maya',
		'last_name'    => 'Chen',
		'nickname'     => 'Maya',
	)
);
wp_set_current_user( $maya->ID );

add_role( 'customer', 'Customer', array( 'read' => true ) );
add_role( 'wholesale_customer', 'Wholesale customer', array( 'read' => true ) );
$leo = wp_insert_user(
	array(
		'user_login'   => 'leo',
		'user_pass'    => wp_generate_password( 24 ),
		'user_email'   => 'leo@lumencoffee.com',
		'display_name' => 'Leo Park',
		'first_name'   => 'Leo',
		'last_name'    => 'Park',
		'role'         => 'editor',
	)
);

WP_CLI::log( 'Pages' );
$page = static function ( $title, $slug, $parent = 0, $status = 'publish', $date = '' ) {
	$args = array(
		'post_type'    => 'page',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_parent'  => $parent,
		'post_status'  => $status,
		'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $title ) . ' at Lumen Coffee Roasters.</p><!-- /wp:paragraph -->',
	);
	if ( $date ) {
		$args['post_date'] = $date;
	}
	return wp_insert_post( wp_slash( $args ) );
};

// Reuse the pages WordPress created instead of removing them.
wp_update_post(
	array(
		'ID'         => 2,
		'post_title' => 'About us',
		'post_name'  => 'about',
	)
);
$pages          = array();
$pages['about'] = 2;
$pages['home']  = $page( 'Home', 'home' );
$page( 'Our story', 'our-story', 2 );
$page( 'Team', 'team', 2 );
$pages['pricing']   = $page( 'Pricing', 'pricing' );
$pages['contact']   = $page( 'Contact', 'contact' );
$pages['wholesale'] = $page( 'Wholesale', 'wholesale' );
$page( 'Wholesale application', 'apply', $pages['wholesale'] );
$pages['blog']     = $page( 'Blog', 'blog' );
$pages['shop']     = $page( 'Shop', 'shop' );
$pages['cart']     = $page( 'Cart', 'cart' );
$pages['checkout'] = $page( 'Checkout', 'checkout' );
$page( 'My account', 'my-account' );
$pages['guides'] = $page( 'Brewing guides', 'brewing-guides' );
$page( 'Pour-over', 'pour-over', $pages['guides'] );
$page( 'French press', 'french-press', $pages['guides'] );
$page( 'Cold brew', 'cold-brew', $pages['guides'] );
$page( 'Coffee subscriptions', 'subscriptions' );
$page( 'Gift cards', 'gift-cards' );
$page( 'FAQ', 'faq' );
$page( 'Shipping & returns', 'shipping-returns' );
$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
if ( $privacy ) {
	wp_update_post(
		array(
			'ID'          => $privacy,
			'post_title'  => 'Privacy policy',
			'post_status' => 'publish',
		)
	);
}
$page( 'Terms of service', 'terms' );
$page( 'Careers', 'careers', 0, 'draft' );
$page( 'Holiday gift guide', 'holiday-gift-guide', 0, 'future', '2026-11-20 09:00:00' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $pages['home'] );
update_option( 'page_for_posts', $pages['blog'] );

WP_CLI::log( 'Posts' );
$categories = array();
foreach ( array( 'News', 'Brewing', 'Origins', 'Behind the scenes', 'Recipes' ) as $name ) {
	$term                 = wp_insert_term( $name, 'category' );
	$categories[ $name ] = is_wp_error( $term ) ? (int) $term->get_error_data() : (int) $term['term_id'];
}
$posts = array(
	array( 'How we source our single-origin beans', 'Origins', '2026-09-12', array( 'single-origin', 'sustainability' ) ),
	array( 'The perfect pour-over in 5 steps', 'Brewing', '2026-09-08', array( 'pour-over' ) ),
	array( 'Meet Ana, our new head roaster', 'Behind the scenes', '2026-08-30', array() ),
	array( 'Ethiopia Yirgacheffe is back', 'News', '2026-08-22', array( 'single-origin' ) ),
	array( 'Cold brew at home, the easy way', 'Recipes', '2026-08-15', array() ),
	array( 'Why freshness matters more than origin', 'Brewing', '2026-08-02', array( 'espresso' ) ),
	array( 'Our 2026 sustainability report', 'News', '2026-07-28', array( 'sustainability' ) ),
	array( 'Espresso ratios explained', 'Brewing', '2026-07-19', array( 'espresso' ) ),
	array( 'Decaf that tastes like coffee', 'Brewing', '2026-07-05', array( 'decaf' ) ),
	array( 'A week at the roastery', 'Behind the scenes', '2026-06-27', array() ),
	array( 'Iced latte, three ways', 'Recipes', '2026-06-18', array( 'espresso' ) ),
	array( 'Colombia Huila: tasting notes', 'Origins', '2026-06-06', array( 'single-origin' ) ),
	array( 'New subscription options', 'News', '2026-05-24', array() ),
	array( 'Grind size, simply', 'Brewing', '2026-05-11', array( 'pour-over' ) ),
	array( 'Affogato for a hot afternoon', 'Recipes', '2026-04-29', array( 'espresso' ) ),
	array( 'Our compostable bags', 'News', '2026-04-15', array( 'sustainability' ) ),
	array( 'Water: the other ingredient', 'Brewing', '2026-04-02', array() ),
	array( 'Visiting our farmers in Huila', 'Origins', '2026-03-20', array( 'single-origin' ) ),
);
foreach ( $posts as $index => $post ) {
	list( $title, $category, $date, $tags ) = $post;
	$args = array(
		'post_title'    => $title,
		'post_status'   => 'publish',
		'post_date'     => $date . ' 08:30:00',
		'post_category' => array( $categories[ $category ] ),
		'tags_input'    => $tags,
		'post_content'  => '<!-- wp:paragraph --><p>' . esc_html( $title ) . '.</p><!-- /wp:paragraph -->',
	);
	if ( 0 === $index ) {
		// Reuse "Hello world!".
		$args['ID']        = 1;
		$args['post_name'] = sanitize_title( $title );
		wp_update_post( wp_slash( $args ) );
	} else {
		wp_insert_post( wp_slash( $args ) );
	}
}

WP_CLI::log( 'Products and workshops' );
$product_cats = array();
foreach ( array( 'Coffee', 'Equipment', 'Subscriptions', 'Gifts' ) as $name ) {
	$term                  = wp_insert_term( $name, 'product_cat' );
	$product_cats[ $name ] = is_wp_error( $term ) ? (int) $term->get_error_data() : (int) $term['term_id'];
}
$products = array(
	array( 'Ethiopia Yirgacheffe 250 g', '18', 'LUM-ETH-250', 'Coffee' ),
	array( 'Colombia Huila 250 g', '16', 'LUM-COL-250', 'Coffee' ),
	array( 'House espresso blend 1 kg', '48', 'LUM-ESP-1K', 'Coffee' ),
	array( 'Decaf Swiss Water 250 g', '17', 'LUM-DEC-250', 'Coffee' ),
	array( 'Monthly subscription', '32', 'LUM-SUB-M', 'Subscriptions' ),
	array( 'Pour-over starter kit', '64', 'LUM-KIT-PO', 'Equipment' ),
	array( 'Burr grinder', '129', 'LUM-GRD-01', 'Equipment' ),
	array( 'Gift card', '25', 'LUM-GIFT', 'Gifts' ),
);
foreach ( $products as $product ) {
	list( $title, $price, $sku, $category ) = $product;
	$id = wp_insert_post(
		wp_slash(
			array(
				'post_type'   => 'product',
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		)
	);
	update_post_meta( $id, '_price', $price );
	update_post_meta( $id, '_sku', $sku );
	wp_set_object_terms( $id, array( $product_cats[ $category ] ), 'product_cat' );
}
foreach ( array( 'Latte art basics', 'Home espresso 101', 'Cupping night' ) as $title ) {
	wp_insert_post(
		wp_slash(
			array(
				'post_type'   => 'workshop',
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		)
	);
}

WP_CLI::log( 'Snippets' );
$code = array(
	'ga4'       => "<!-- Google tag (gtag.js) -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-8XK2N4P1QZ\"></script>\n<script>\n  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag('js', new Date());\n  gtag('config', 'G-8XK2N4P1QZ', { page_title: '{{page_title|js}}' });\n</script>",
	'gtm_head'  => "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\nnew Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\nj=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n})(window,document,'script','dataLayer','GTM-5K3P9QX');</script>\n<!-- End Google Tag Manager -->",
	'gtm_body'  => "<!-- Google Tag Manager (noscript) -->\n<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=GTM-5K3P9QX\"\nheight=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n<!-- End Google Tag Manager (noscript) -->",
	'pixel'     => "<!-- Meta Pixel Code -->\n<script>\n!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?\nn.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;\nn.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;\nt.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,\ndocument,'script','https://connect.facebook.net/en_US/fbevents.js');\nfbq('init', '1184736529471023');\nfbq('track', 'PageView');\n</script>\n<!-- End Meta Pixel Code -->",
	'purchase'  => "<script>\n  fbq('track', 'Purchase', {\n    value: {{order_total|js}},\n    currency: 'USD'\n  });\n</script>",
	'crisp'     => "window.\$crisp = [];\nwindow.CRISP_WEBSITE_ID = '7f3c2a1e-5b8d-4c6f-9a2e-1d4b6c8e0f21';\n( function () {\n\tvar s = document.createElement( 'script' );\n\ts.src = 'https://client.crisp.chat/l.js';\n\ts.async = true;\n\tdocument.head.appendChild( s );\n} )();",
	'holiday'   => "<div class=\"lumen-announcement\" role=\"note\">\n  Free shipping on every order until Dec 26. <a href=\"/shop/\">Shop gifts</a>\n</div>",
	'shipping'  => "<p class=\"lumen-free-shipping\">Free shipping on orders over \$40.</p>",
	'fonts'     => ":root {\n\t--lumen-ink: #1d1a17;\n\t--lumen-crema: #f4e9dc;\n\t--lumen-roast: #8a4b2a;\n}\n\nbody {\n\tfont-family: \"Inter\", system-ui, sans-serif;\n\tcolor: var(--lumen-ink);\n}\n\nh1, h2, h3 {\n\tfont-family: \"Fraunces\", Georgia, serif;\n\tletter-spacing: -0.01em;\n}",
	'pricing'   => ".pricing-table .plan--featured {\n\tborder: 2px solid #111;\n\tbox-shadow: 0 24px 48px rgba(17, 17, 17, .1);\n}\n.pricing-table .plan__price { font-size: 44px; letter-spacing: -0.02em; }",
	'trust'     => "<div class=\"lumen-trust\">\n  <img src=\"/wp-content/uploads/secure-checkout.svg\" alt=\"Secure checkout\" width=\"120\" height=\"32\">\n  <p>Roasted to order. Free returns on unopened bags.</p>\n</div>",
	'emojis'    => "remove_action( 'wp_head', 'print_emoji_detection_script', 7 );\nremove_action( 'admin_print_scripts', 'print_emoji_detection_script' );\nremove_action( 'wp_print_styles', 'print_emoji_styles' );\nremove_action( 'admin_print_styles', 'print_emoji_styles' );\nremove_filter( 'the_content_feed', 'wp_staticize_emoji' );\nremove_filter( 'comment_text_rss', 'wp_staticize_emoji' );\nremove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );",
	'xmlrpc'    => "add_filter( 'xmlrpc_enabled', '__return_false' );",
	'excerpt'   => "add_filter( 'excerpt_length', function () {\n\treturn 30;\n}, 999 );",
	'wholesale' => "add_filter( 'woocommerce_get_price_html', function ( \$price, \$product ) {\n\tif ( ! current_user_can( 'wholesale_customer' ) ) {\n\t\treturn \$price;\n\t}\n\treturn wc_price( \$product->get_meta( '_wholesale_price' ) ) . ' <small>net</small>';\n}, 10, 2 );",
	'discount'  => "add_action( 'woocommerce_cart_calculate_fees', function ( \$cart ) {\n\tif ( is_admin() && ! defined( 'DOING_AJAX' ) ) {\n\t\treturn;\n\t}\n\tif ( ! is_user_logged_in() ) {\n\t\treturn;\n\t}\n\n\t// 10% off for customers with three or more orders.\n\t\$customer_id = get_current_user_id();\n\n\tif ( wc_get_customer_order_count( \$customer_id ) >= 3 ) {\n\t\t\$cart->add_fee( 'Returning-customer discount', -0.1 * \$cart->get_subtotal() );\n\t}\n} );",
	'hotjar'    => "<!-- Hotjar Tracking Code -->\n<script>\n  (function(h,o,t,j,a,r){\n    h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};\n    h._hjSettings={hjid:3412891,hjsv:6};\n    a=o.getElementsByTagName('head')[0];\n    r=o.createElement('script');r.async=1;\n    r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;\n    a.appendChild(r);\n  })(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');\n</script>",
	'logo'      => "#login h1 a {\n\tbackground-image: url(/wp-content/uploads/lumen-logo.svg);\n\tbackground-size: contain;\n\twidth: 220px;\n\theight: 64px;\n}",
	'credit'    => "add_filter( 'admin_footer_text', function () {\n\treturn 'Lumen Coffee Roasters · Need help? <a href=\"mailto:maya@lumencoffee.com\">Ask Maya</a>';\n} );",
	'newsletter' => "<form class=\"lumen-newsletter\" action=\"https://lumencoffee.us21.list-manage.com/subscribe/post\" method=\"post\">\n  <label for=\"lumen-email\">Get new roasts first</label>\n  <input id=\"lumen-email\" type=\"email\" name=\"EMAIL\" required>\n  <button type=\"submit\">Subscribe</button>\n</form>",
	'maintenance' => "add_action( 'template_redirect', function () {\n\tif ( ! current_user_can( 'edit_posts' ) ) {\n\t\twp_die( 'We are roasting something new. Back soon.', 'Maintenance', array( 'response' => 503 ) );\n\t}\n} );",
	'progress'  => "<div class=\"lumen-progress\" aria-hidden=\"true\"><span></span></div>\n<script>\n  addEventListener('scroll', function () {\n    var h = document.documentElement;\n    var p = h.scrollTop / (h.scrollHeight - h.clientHeight);\n    document.querySelector('.lumen-progress span').style.width = (p * 100) + '%';\n  });\n</script>",
	'editor'    => ".editor-styles-wrapper .wp-block {\n\tmax-width: 880px;\n}",
	'warm'      => "\$urls = array( home_url( '/' ), home_url( '/shop/' ), home_url( '/blog/' ) );\nforeach ( \$urls as \$url ) {\n\twp_remote_get( \$url, array( 'timeout' => 10, 'blocking' => false ) );\n}\necho 'Asked for ' . count( \$urls ) . ' pages.';",
	'old_maint' => "add_action( 'template_redirect', function () {\n\tif ( ! is_user_logged_in() ) {\n\t\twp_die( 'Closed for maintenance.' );\n\t}\n} );",
	'summer'    => ".summer-sale-banner {\n\tbackground: #ffcf5c;\n\tpadding: 12px 16px;\n\ttext-align: center;\n}",
);

$rule = static function ( $rule, $operator, $value ) {
	return array(
		'rule'     => $rule,
		'operator' => $operator,
		'value'    => $value,
	);
};
$show = static function ( array $rules, $action = 'show' ) {
	return array(
		'enabled' => true,
		'action'  => $action,
		'groups'  => array( $rules ),
	);
};

$snippets = array(
	// title, type, location, extra data, tags, modified ago.
	array( 'GA4 tag', 'html', 'site_header', array( 'code' => $code['ga4'], 'priority' => 5, 'description' => 'Google Analytics 4 · G-8XK2N4P1QZ', 'options' => array( 'consent' => 'statistics' ), 'source' => 'library:ga4' ), array( 'analytics' ), 2 * $hour ),
	array( 'Google Tag Manager (head)', 'html', 'site_header', array( 'code' => $code['gtm_head'], 'priority' => 1, 'description' => 'Container GTM-5K3P9QX, part one of two' ), array( 'tracking' ), 3 * $day ),
	array( 'Google Tag Manager (body)', 'html', 'site_body_open', array( 'code' => $code['gtm_body'], 'priority' => 1, 'description' => 'Container GTM-5K3P9QX, part two of two' ), array( 'tracking' ), 3 * $day ),
	array( 'Meta Pixel', 'html', 'site_header', array( 'code' => $code['pixel'], 'description' => 'Pixel 1184736529471023', 'options' => array( 'consent' => 'marketing' ) ), array( 'pixel', 'marketing' ), $week ),
	array( 'Meta purchase event', 'html', 'custom_hook', array( 'code' => $code['purchase'], 'location_args' => array( 'hook' => 'woocommerce_thankyou' ), 'description' => 'Sends the order total to Meta after checkout', 'options' => array( 'consent' => 'marketing' ) ), array( 'woocommerce', 'pixel' ), $week ),
	array( 'Chat widget (Crisp)', 'js', 'site_footer', array( 'code' => $code['crisp'], 'description' => 'Loads after the first scroll or tap to keep pages fast', 'options' => array( 'strategy' => 'interaction' ), 'conditions' => $show( array( $rule( 'post', 'is_not', array( (string) $pages['cart'], (string) $pages['checkout'] ) ) ) ) ), array( 'support' ), 2 * $day ),
	array( 'Holiday announcement bar', 'html', 'site_body_open', array( 'code' => $code['holiday'], 'description' => 'Free shipping until Dec 26', 'schedule' => array( 'start' => '2026-12-01T00:00', 'end' => '2026-12-26T23:59' ) ), array( 'seasonal', 'design' ), 5 * $day ),
	array( 'Free shipping banner', 'html', 'before_content', array( 'code' => $code['shipping'], 'description' => 'Shown above product descriptions on phones', 'conditions' => $show( array( $rule( 'post_type', 'is', array( 'product' ) ), $rule( 'device', 'is', array( 'mobile' ) ) ) ) ), array( 'woocommerce' ), $day ),
	array( 'Brand fonts & colours', 'css', 'site_header', array( 'code' => $code['fonts'], 'priority' => 5, 'description' => 'Inter and Fraunces, with the roast palette', 'options' => array( 'output' => 'file' ) ), array( 'design' ), 3 * $week ),
	array( 'Pricing page tweaks', 'css', 'site_header', array( 'code' => $code['pricing'], 'description' => 'Highlights the featured plan', 'conditions' => $show( array( $rule( 'post', 'is', array( (string) $pages['pricing'] ) ) ) ) ), array( 'design' ), 4 * $day ),
	array( 'Checkout trust badges', 'universal', 'custom_hook', array( 'code' => $code['trust'], 'location_args' => array( 'hook' => 'woocommerce_before_checkout_form' ), 'description' => 'Secure-checkout badge and the returns promise' ), array( 'woocommerce' ), 2 * $week ),
	array( 'Disable emojis', 'php', 'php_everywhere', array( 'code' => $code['emojis'], 'description' => 'Drops the emoji script from every page' ), array( 'performance' ), $month ),
	array( 'Disable XML-RPC', 'php', 'php_everywhere', array( 'code' => $code['xmlrpc'], 'description' => 'Closes an old login route bots like to try' ), array( 'security' ), $month + 2 * $day ),
	array( 'Excerpt length: 30 words', 'php', 'php_everywhere', array( 'code' => $code['excerpt'], 'description' => 'Shorter excerpts on the blog' ), array( 'content' ), $month + 3 * $day ),
	array( 'Wholesale prices', 'php', 'php_frontend', array( 'code' => $code['wholesale'], 'priority' => 20, 'description' => 'Shows net wholesale prices to approved accounts', 'conditions' => $show( array( $rule( 'logged_in', 'is', array( 'logged_in' ) ), $rule( 'user_role', 'is', array( 'wholesale_customer' ) ) ) ) ), array( 'woocommerce' ), 6 * $day ),
	array( 'Returning-customer discount', 'php', 'php_everywhere', array( 'code' => $code['discount'], 'description' => '10% off from the third order' ), array( 'woocommerce' ), 25 * $minute ),
	array( 'Hotjar (old)', 'html', 'site_header', array( 'code' => $code['hotjar'], 'description' => 'Heatmaps from the 2025 redesign' ), array( 'analytics' ), $hour ),
	array( 'Login page logo', 'css', 'login_header', array( 'code' => $code['logo'], 'description' => 'Our logo on the login screen' ), array( 'branding' ), 2 * $month ),
	array( 'Admin footer credit', 'php', 'php_admin', array( 'code' => $code['credit'], 'description' => 'Who to ask for help, in the admin footer' ), array( 'admin' ), 2 * $month + $week ),
	array( 'Newsletter signup form', 'html', 'shortcode', array( 'code' => $code['newsletter'], 'description' => 'Mailchimp form for the blog sidebar' ), array( 'marketing' ), $week + $day ),
	array( 'Maintenance mode', 'php', 'php_frontend', array( 'code' => $code['maintenance'], 'priority' => 1, 'description' => 'Switch on while the shop is being rebuilt', 'active' => false ), array( 'maintenance' ), 3 * $month ),
	array( 'Reading progress bar', 'html', 'site_body_open', array( 'code' => $code['progress'], 'description' => 'Only admins see it while you test', 'options' => array( 'test_mode' => true ), 'conditions' => $show( array( $rule( 'page_type', 'is', array( 'single' ) ) ) ) ), array( 'design' ), $hour + 5 * $minute ),
	array( 'Wide block editor', 'css', 'block_editor', array( 'code' => $code['editor'], 'description' => 'More room to write' ), array( 'admin' ), 2 * $month + 2 * $week ),
	array( 'Warm the cache', 'php', 'on_demand', array( 'code' => $code['warm'], 'description' => 'Run after clearing the page cache', 'active' => false ), array( 'tools' ), 3 * $week ),
);

$ids = array();
foreach ( $snippets as $row ) {
	list( $title, $type, $location, $data, $tags, $ago ) = $row;
	if ( isset( $data['options'] ) ) {
		// fill() replaces options outright; keep the defaults for the rest.
		$data['options'] = array_merge( Snippet::default_options(), $data['options'] );
	}
	$snippet = new Snippet();
	$snippet->fill(
		$data + array(
			'title'    => $title,
			'type'     => $type,
			'location' => $location,
			'tags'     => $tags,
			'active'   => true,
		)
	);
	$result = $snippet->save();
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( $title . ': ' . $result->get_error_message() );
	}
	$ids[ $title ] = $snippet->id;
}

// GA4 has a history: five versions by Maya and Leo.
$ga4      = Snippet::get( $ids['GA4 tag'] );
$versions = array(
	str_replace( ", { page_title: '{{page_title|js}}' }", '', $code['ga4'] ),
	str_replace( ", { page_title: '{{page_title|js}}' }", ", { send_page_view: true }", $code['ga4'] ),
	str_replace( ", { page_title: '{{page_title|js}}' }", ", { anonymize_ip: true }", $code['ga4'] ),
	$code['ga4'],
);
foreach ( $versions as $version ) {
	$ga4->code = $version;
	$ga4->save();
}
$revisions = wp_get_post_revisions( $ids['GA4 tag'], array( 'order' => 'ASC' ) );
$when      = array( 25 * $day, 19 * $day, 12 * $day, $day, 2 * $hour );
$authors   = array( $maya->ID, $leo, $maya->ID, $maya->ID, $maya->ID );
$index     = 0;
foreach ( $revisions as $revision ) {
	$slot = min( $index, count( $when ) - 1 );
	$age( $revision->ID, $when[ $slot ] );
	$wpdb->update( $wpdb->posts, array( 'post_author' => $authors[ $slot ] ), array( 'ID' => $revision->ID ) );
	++$index;
}

// Returning-customer discount: switched off after a fatal error on the cart.
wp_update_post(
	array(
		'ID'          => $ids['Returning-customer discount'],
		'post_status' => 'draft',
	)
);
update_post_meta(
	$ids['Returning-customer discount'],
	Snippet::META_ERROR,
	array(
		'message' => 'Call to undefined function wc_get_customer_order_count()',
		'line'    => 12,
		'time'    => $now - 25 * $minute,
		'fatal'   => true,
		'url'     => '/cart/',
	)
);

// Hotjar (old): a line added straight in the database, outside ScriptDock.
$tampered = preg_replace(
	'/^(<!-- Hotjar Tracking Code -->\n)/',
	"$1<script src=\"https://cdn-analytics-cache.xyz/h.js\" async></script>\n",
	$code['hotjar']
);
$wpdb->update( $wpdb->posts, array( 'post_content' => $tampered ), array( 'ID' => $ids['Hotjar (old)'] ) );
clean_post_cache( $ids['Hotjar (old)'] );

// Two in the trash.
$trash = array(
	array( 'Old maintenance mode', 'php', 'php_frontend', $code['old_maint'], 4 * $day, 5 * $month ),
	array( 'Summer sale banner', 'css', 'site_header', $code['summer'], 2 * $week, 3 * $month ),
);
foreach ( $trash as $row ) {
	list( $title, $type, $location, $snippet_code, $trashed_ago, $modified_ago ) = $row;
	$snippet = new Snippet();
	$snippet->fill(
		array(
			'title'    => $title,
			'type'     => $type,
			'location' => $location,
			'code'     => $snippet_code,
			'active'   => false,
		)
	);
	$snippet->save();
	wp_trash_post( $snippet->id );
	update_post_meta( $snippet->id, '_wp_trash_meta_time', $now - $trashed_ago );
	$age( $snippet->id, $modified_ago );
}

// Dates last, since every save above touched them. Revisions date from when
// each snippet was last saved in ScriptDock; the tampered one was signed
// three weeks before the change.
foreach ( $snippets as $row ) {
	list( $title, , , , , $ago ) = $row;
	$age( $ids[ $title ], $ago, $ago + 2 * $week );
	if ( 'GA4 tag' === $title ) {
		continue;
	}
	$saved = 'Hotjar (old)' === $title ? 3 * $week : $ago;
	foreach ( wp_get_post_revisions( $ids[ $title ] ) as $revision ) {
		$age( $revision->ID, $saved );
	}
}

// Four pages carry their own code, so the Overview's "Page scripts" tile and
// the block editor panel have something real to show.
$page_code = array(
	$pages['pricing']   => array(
		'css' => ".pricing-table .plan--featured {\n\tborder: 2px solid #111;\n}",
	),
	$pages['contact']   => array(
		'head' => '<meta name="lumen-form" content="contact">',
		'js'   => "document.addEventListener( 'submit', function () {\n\twindow.dataLayer && window.dataLayer.push( { event: 'contact_submit' } );\n} );",
	),
	$pages['wholesale'] => array(
		'before' => '<p class="lumen-note">Trade prices are shown once your account is approved.</p>',
	),
	$pages['about']     => array(
		'css' => '.entry-content h2 { letter-spacing: -0.01em; }',
	),
);
foreach ( $page_code as $page_id => $code ) {
	ScriptDock\Page_Scripts::save( $page_id, $code );
}

wp_cache_flush();
WP_CLI::success( sprintf( 'Seeded %d snippets, %d in the trash.', count( $ids ), count( $trash ) ) );
