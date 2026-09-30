<?php
/**
 * Snippet library templates.
 *
 * Code lives in library/code/. Placeholders like %%measurement_id%% are
 * replaced with values that must match the field's pattern.
 *
 * @package ScriptDock
 */

defined( 'ABSPATH' ) || exit;

$scriptdock_numeric_id = array(
	'pattern' => '^[0-9]{4,20}$',
);

return array(

	/* ---------------------------------------------------------------- Tracking */

	'ga4'                    => array(
		'title'       => __( 'Google Analytics 4', 'scriptdock' ),
		'description' => __( 'Adds the Google tag (gtag.js) for a GA4 property to every page.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'priority'    => 5,
		'file'        => 'ga4.html',
		'tags'        => array( 'analytics' ),
		'fields'      => array(
			'measurement_id' => array(
				'label'       => __( 'Measurement ID', 'scriptdock' ),
				'placeholder' => 'G-XXXXXXXXXX',
				'pattern'     => '^G-[A-Z0-9]{4,20}$',
			),
		),
	),

	'gtm'                    => array(
		'title'       => __( 'Google Tag Manager', 'scriptdock' ),
		'description' => __( 'Creates two snippets: the container script in the head and the noscript fallback after <body>.', 'scriptdock' ),
		'category'    => 'tracking',
		'tags'        => array( 'analytics' ),
		'fields'      => array(
			'container_id' => array(
				'label'       => __( 'Container ID', 'scriptdock' ),
				'placeholder' => 'GTM-XXXXXXX',
				'pattern'     => '^GTM-[A-Z0-9]{4,12}$',
			),
		),
		'snippets'    => array(
			array(
				'title'    => __( 'Google Tag Manager (head)', 'scriptdock' ),
				'type'     => 'html',
				'location' => 'site_header',
				'priority' => 1,
				'file'     => 'gtm-head.html',
			),
			array(
				'title'    => __( 'Google Tag Manager (body)', 'scriptdock' ),
				'type'     => 'html',
				'location' => 'site_body_open',
				'priority' => 1,
				'file'     => 'gtm-body.html',
			),
		),
	),

	'meta-pixel'             => array(
		'title'       => __( 'Meta (Facebook) Pixel', 'scriptdock' ),
		'description' => __( 'Adds the Meta Pixel base code with a PageView event.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'file'        => 'meta-pixel.html',
		'tags'        => array( 'pixel', 'marketing' ),
		'options'     => array( 'consent' => 'marketing' ),
		'fields'      => array(
			'pixel_id' => array(
				'label'       => __( 'Pixel ID', 'scriptdock' ),
				'placeholder' => '123456789012345',
				'pattern'     => '^[0-9]{6,20}$',
			),
		),
	),

	'google-ads'             => array(
		'title'       => __( 'Google Ads tag', 'scriptdock' ),
		'description' => __( 'Adds the Google Ads global site tag for conversion tracking and remarketing.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'file'        => 'google-ads.html',
		'tags'        => array( 'marketing' ),
		'options'     => array( 'consent' => 'marketing' ),
		'fields'      => array(
			'conversion_id' => array(
				'label'       => __( 'Conversion ID', 'scriptdock' ),
				'placeholder' => 'AW-123456789',
				'pattern'     => '^AW-[0-9]{6,15}$',
			),
		),
	),

	'clarity'                => array(
		'title'       => __( 'Microsoft Clarity', 'scriptdock' ),
		'description' => __( 'Free heatmaps and session recordings from Microsoft.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'file'        => 'clarity.html',
		'tags'        => array( 'analytics' ),
		'options'     => array( 'consent' => 'statistics' ),
		'fields'      => array(
			'project_id' => array(
				'label'       => __( 'Project ID', 'scriptdock' ),
				'placeholder' => 'abcd1234ef',
				'pattern'     => '^[a-z0-9]{6,20}$',
			),
		),
	),

	'hotjar'                 => array(
		'title'       => __( 'Hotjar', 'scriptdock' ),
		'description' => __( 'Heatmaps, recordings and feedback widgets.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'file'        => 'hotjar.html',
		'tags'        => array( 'analytics' ),
		'options'     => array( 'consent' => 'statistics' ),
		'fields'      => array(
			'site_id' => array_merge(
				$scriptdock_numeric_id,
				array(
					'label'       => __( 'Site ID', 'scriptdock' ),
					'placeholder' => '1234567',
				)
			),
		),
	),

	'tiktok-pixel'           => array(
		'title'       => __( 'TikTok Pixel', 'scriptdock' ),
		'description' => __( 'Adds the TikTok Pixel base code with a page view event.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'file'        => 'tiktok-pixel.html',
		'tags'        => array( 'pixel', 'marketing' ),
		'options'     => array( 'consent' => 'marketing' ),
		'fields'      => array(
			'pixel_id' => array(
				'label'       => __( 'Pixel ID', 'scriptdock' ),
				'placeholder' => 'C1234567890ABCDEFGHI',
				'pattern'     => '^[A-Z0-9]{10,30}$',
			),
		),
	),

	'linkedin-insight'       => array(
		'title'       => __( 'LinkedIn Insight Tag', 'scriptdock' ),
		'description' => __( 'Conversion tracking and retargeting for LinkedIn ads.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_footer',
		'file'        => 'linkedin-insight.html',
		'tags'        => array( 'pixel', 'marketing' ),
		'options'     => array( 'consent' => 'marketing' ),
		'fields'      => array(
			'partner_id' => array_merge(
				$scriptdock_numeric_id,
				array(
					'label'       => __( 'Partner ID', 'scriptdock' ),
					'placeholder' => '1234567',
				)
			),
		),
	),

	'pinterest-tag'          => array(
		'title'       => __( 'Pinterest Tag', 'scriptdock' ),
		'description' => __( 'Adds the Pinterest base tag with a page visit event.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'file'        => 'pinterest-tag.html',
		'tags'        => array( 'pixel', 'marketing' ),
		'options'     => array( 'consent' => 'marketing' ),
		'fields'      => array(
			'tag_id' => array(
				'label'       => __( 'Tag ID', 'scriptdock' ),
				'placeholder' => '2612345678901',
				'pattern'     => '^[0-9]{6,20}$',
			),
		),
	),

	'microsoft-uet'          => array(
		'title'       => __( 'Microsoft Advertising UET tag', 'scriptdock' ),
		'description' => __( 'Universal Event Tracking for Microsoft (Bing) Ads.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'file'        => 'microsoft-uet.html',
		'tags'        => array( 'marketing' ),
		'options'     => array( 'consent' => 'marketing' ),
		'fields'      => array(
			'tag_id' => array_merge(
				$scriptdock_numeric_id,
				array(
					'label'       => __( 'UET tag ID', 'scriptdock' ),
					'placeholder' => '12345678',
				)
			),
		),
	),

	'plausible'              => array(
		'title'       => __( 'Plausible Analytics', 'scriptdock' ),
		'description' => __( 'Privacy-friendly, cookie-free analytics.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'file'        => 'plausible.html',
		'tags'        => array( 'analytics' ),
		'fields'      => array(
			'domain' => array(
				'label'       => __( 'Domain as set up in Plausible', 'scriptdock' ),
				'placeholder' => 'example.com',
				'pattern'     => '^[a-z0-9]([a-z0-9.-]{0,251}[a-z0-9])?$',
			),
		),
	),

	'fathom'                 => array(
		'title'       => __( 'Fathom Analytics', 'scriptdock' ),
		'description' => __( 'Privacy-first website analytics.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'file'        => 'fathom.html',
		'tags'        => array( 'analytics' ),
		'fields'      => array(
			'site_id' => array(
				'label'       => __( 'Site ID', 'scriptdock' ),
				'placeholder' => 'ABCDEFGH',
				'pattern'     => '^[A-Z0-9]{4,12}$',
			),
		),
	),

	'matomo'                 => array(
		'title'       => __( 'Matomo', 'scriptdock' ),
		'description' => __( 'Self-hosted or cloud Matomo tracking code.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'file'        => 'matomo.html',
		'tags'        => array( 'analytics' ),
		'options'     => array( 'consent' => 'statistics' ),
		'fields'      => array(
			'matomo_url' => array(
				'label'       => __( 'Matomo URL (with trailing slash)', 'scriptdock' ),
				'placeholder' => 'https://analytics.example.com/',
				'pattern'     => '^https://[A-Za-z0-9.-]+(:[0-9]{2,5})?(/[A-Za-z0-9._~-]+)*/$',
			),
			'site_id'    => array(
				'label'       => __( 'Site ID', 'scriptdock' ),
				'placeholder' => '1',
				'pattern'     => '^[0-9]{1,6}$',
			),
		),
	),

	'search-console'         => array(
		'title'       => __( 'Google Search Console verification', 'scriptdock' ),
		'description' => __( 'Adds the google-site-verification meta tag.', 'scriptdock' ),
		'category'    => 'tracking',
		'type'        => 'html',
		'location'    => 'site_header',
		'priority'    => 1,
		'file'        => 'search-console.html',
		'tags'        => array( 'seo' ),
		'fields'      => array(
			'token' => array(
				'label'       => __( 'Verification code (the content value)', 'scriptdock' ),
				'placeholder' => 'aBcD1234...',
				'pattern'     => '^[A-Za-z0-9_-]{20,100}$',
			),
		),
	),

	/* ------------------------------------------------------------- WooCommerce */

	'wc-ga4-purchase'        => array(
		'title'       => __( 'GA4 purchase event', 'scriptdock' ),
		'description' => __( 'Sends a GA4 purchase event with order value and items on the order-received page. Needs the Google Analytics 4 snippet.', 'scriptdock' ),
		'category'    => 'woocommerce',
		'requires'    => 'woocommerce',
		'type'        => 'html',
		'location'    => 'wc_thankyou',
		'file'        => 'wc-ga4-purchase.html',
		'tags'        => array( 'analytics', 'woocommerce' ),
	),

	'wc-meta-purchase'       => array(
		'title'       => __( 'Meta Pixel purchase event', 'scriptdock' ),
		'description' => __( 'Tracks purchases with value and currency. Needs the Meta Pixel snippet.', 'scriptdock' ),
		'category'    => 'woocommerce',
		'requires'    => 'woocommerce',
		'type'        => 'html',
		'location'    => 'wc_thankyou',
		'file'        => 'wc-meta-purchase.html',
		'tags'        => array( 'pixel', 'woocommerce' ),
		// Same consent category as the pixel, so it runs right after the pixel loads.
		'options'     => array( 'consent' => 'marketing' ),
	),

	'wc-add-to-cart-text'    => array(
		'title'       => __( 'Change “Add to cart” text', 'scriptdock' ),
		'description' => __( 'Changes the add-to-cart button text on product pages and archives.', 'scriptdock' ),
		'category'    => 'woocommerce',
		'requires'    => 'woocommerce',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'wc-add-to-cart-text.txt',
		'tags'        => array( 'woocommerce' ),
	),

	'wc-autocomplete-virtual' => array(
		'title'       => __( 'Auto-complete virtual orders', 'scriptdock' ),
		'description' => __( 'Marks paid orders that only contain virtual products as completed.', 'scriptdock' ),
		'category'    => 'woocommerce',
		'requires'    => 'woocommerce',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'wc-autocomplete-virtual.txt',
		'tags'        => array( 'woocommerce' ),
	),

	'wc-cart-fragments'      => array(
		'title'       => __( 'Disable cart fragments on non-shop pages', 'scriptdock' ),
		'description' => __( 'Stops the cart-fragments AJAX request on pages that do not need it. Keep it off if your header shows a live mini-cart.', 'scriptdock' ),
		'category'    => 'woocommerce',
		'requires'    => 'woocommerce',
		'type'        => 'php',
		'location'    => 'php_frontend',
		'file'        => 'wc-cart-fragments.txt',
		'tags'        => array( 'performance', 'woocommerce' ),
	),

	/* ------------------------------------------------------------- Performance */

	'disable-emojis'         => array(
		'title'       => __( 'Disable emoji scripts', 'scriptdock' ),
		'description' => __( 'Removes the emoji detection script and styles. Browsers show emoji natively.', 'scriptdock' ),
		'category'    => 'performance',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'disable-emojis.txt',
		'tags'        => array( 'performance' ),
	),

	'disable-embeds'         => array(
		'title'       => __( 'Disable oEmbed discovery', 'scriptdock' ),
		'description' => __( 'Stops other sites from embedding your posts and removes wp-embed.js.', 'scriptdock' ),
		'category'    => 'performance',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'disable-embeds.txt',
		'tags'        => array( 'performance' ),
	),

	'remove-jquery-migrate'  => array(
		'title'       => __( 'Remove jQuery Migrate', 'scriptdock' ),
		'description' => __( 'Drops jQuery Migrate on the front end. Test your site: old themes and plugins may need it.', 'scriptdock' ),
		'category'    => 'performance',
		'type'        => 'php',
		'location'    => 'php_frontend',
		'file'        => 'remove-jquery-migrate.txt',
		'tags'        => array( 'performance' ),
	),

	'clean-head'             => array(
		'title'       => __( 'Clean up <head>', 'scriptdock' ),
		'description' => __( 'Removes RSD, Windows Live Writer, shortlink and REST API discovery links from the page head.', 'scriptdock' ),
		'category'    => 'performance',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'clean-head.txt',
		'tags'        => array( 'performance' ),
	),

	'disable-self-pingbacks' => array(
		'title'       => __( 'Disable self-pingbacks', 'scriptdock' ),
		'description' => __( 'Stops WordPress from pinging your own posts when you link to them.', 'scriptdock' ),
		'category'    => 'performance',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'disable-self-pingbacks.txt',
		'tags'        => array( 'performance' ),
	),

	'heartbeat-control'      => array(
		'title'       => __( 'Slow down the Heartbeat API', 'scriptdock' ),
		'description' => __( 'Sends Heartbeat requests every 60 seconds and disables them on the front end.', 'scriptdock' ),
		'category'    => 'performance',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'heartbeat-control.txt',
		'tags'        => array( 'performance' ),
	),

	'dashicons-logged-out'   => array(
		'title'       => __( 'Remove Dashicons for visitors', 'scriptdock' ),
		'description' => __( 'Stops loading the Dashicons font for logged-out visitors.', 'scriptdock' ),
		'category'    => 'performance',
		'type'        => 'php',
		'location'    => 'php_frontend',
		'file'        => 'dashicons-logged-out.txt',
		'tags'        => array( 'performance' ),
	),

	/* ---------------------------------------------------------------- Security */

	'disable-xmlrpc'         => array(
		'title'       => __( 'Disable XML-RPC', 'scriptdock' ),
		'description' => __( 'Turns off XML-RPC, a common brute-force target. Jetpack and some mobile apps need it.', 'scriptdock' ),
		'category'    => 'security',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'disable-xmlrpc.txt',
		'tags'        => array( 'security' ),
	),

	'hide-wp-version'        => array(
		'title'       => __( 'Hide the WordPress version', 'scriptdock' ),
		'description' => __( 'Removes the generator meta tag and version from feeds.', 'scriptdock' ),
		'category'    => 'security',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'hide-wp-version.txt',
		'tags'        => array( 'security' ),
	),

	'block-user-enumeration' => array(
		'title'       => __( 'Block user enumeration', 'scriptdock' ),
		'description' => __( 'Hides user lists from the REST API and ?author=N scans for visitors who are not logged in.', 'scriptdock' ),
		'category'    => 'security',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'block-user-enumeration.txt',
		'tags'        => array( 'security' ),
	),

	'generic-login-errors'   => array(
		'title'       => __( 'Generic login error message', 'scriptdock' ),
		'description' => __( 'Stops the login form from revealing whether a username exists.', 'scriptdock' ),
		'category'    => 'security',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'generic-login-errors.txt',
		'tags'        => array( 'security' ),
	),

	'disable-app-passwords'  => array(
		'title'       => __( 'Disable application passwords', 'scriptdock' ),
		'description' => __( 'Turns off application passwords if you do not use them for API access.', 'scriptdock' ),
		'category'    => 'security',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'disable-app-passwords.txt',
		'tags'        => array( 'security' ),
	),

	/* ------------------------------------------------------------------- Admin */

	'hide-admin-bar'         => array(
		'title'       => __( 'Hide the admin bar for non-admins', 'scriptdock' ),
		'description' => __( 'Only users who can manage options see the toolbar on the front end.', 'scriptdock' ),
		'category'    => 'admin',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'hide-admin-bar.txt',
		'tags'        => array( 'admin' ),
	),

	'admin-footer-text'      => array(
		'title'       => __( 'Custom admin footer text', 'scriptdock' ),
		'description' => __( 'Replaces “Thank you for creating with WordPress” in the admin footer. Edit the text in the code.', 'scriptdock' ),
		'category'    => 'admin',
		'type'        => 'php',
		'location'    => 'php_admin',
		'file'        => 'admin-footer-text.txt',
		'tags'        => array( 'admin' ),
	),

	'clean-dashboard'        => array(
		'title'       => __( 'Clean up the dashboard', 'scriptdock' ),
		'description' => __( 'Removes the WordPress Events and News, Quick Draft and Welcome panels.', 'scriptdock' ),
		'category'    => 'admin',
		'type'        => 'php',
		'location'    => 'php_admin',
		'file'        => 'clean-dashboard.txt',
		'tags'        => array( 'admin' ),
	),

	'disable-update-emails'  => array(
		'title'       => __( 'Disable automatic update emails', 'scriptdock' ),
		'description' => __( 'Stops the emails WordPress sends after successful automatic updates. Failure emails still arrive.', 'scriptdock' ),
		'category'    => 'admin',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'disable-update-emails.txt',
		'tags'        => array( 'admin' ),
	),

	'duplicate-posts'        => array(
		'title'       => __( 'Duplicate posts and pages', 'scriptdock' ),
		'description' => __( 'Adds a “Duplicate” link to posts and pages that creates a draft copy with its terms and custom fields.', 'scriptdock' ),
		'category'    => 'admin',
		'type'        => 'php',
		'location'    => 'php_admin',
		'file'        => 'duplicate-posts.txt',
		'tags'        => array( 'admin' ),
	),

	/* ----------------------------------------------------------------- Content */

	'excerpt-length'         => array(
		'title'       => __( 'Change the excerpt length', 'scriptdock' ),
		'description' => __( 'Sets how many words automatic excerpts contain.', 'scriptdock' ),
		'category'    => 'content',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'excerpt-length.txt',
		'tags'        => array( 'content' ),
		'fields'      => array(
			'words' => array(
				'label'       => __( 'Number of words', 'scriptdock' ),
				'placeholder' => '30',
				'default'     => '30',
				'pattern'     => '^[1-9][0-9]{0,2}$',
			),
		),
	),

	'disable-comments'       => array(
		'title'       => __( 'Disable comments everywhere', 'scriptdock' ),
		'description' => __( 'Closes comments and pingbacks, hides existing comments and removes comment menus.', 'scriptdock' ),
		'category'    => 'content',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'disable-comments.txt',
		'tags'        => array( 'content' ),
	),

	'last-updated'           => array(
		'title'       => __( '“Last updated” date on posts', 'scriptdock' ),
		'description' => __( 'Shows when a post was last updated above its content, only if it changed after publishing.', 'scriptdock' ),
		'category'    => 'content',
		'type'        => 'universal',
		'location'    => 'before_content',
		'file'        => 'last-updated.txt',
		'tags'        => array( 'content' ),
		'conditions'  => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array(
				array(
					array(
						'rule'     => 'post_type',
						'operator' => 'is',
						'value'    => array( 'post' ),
					),
				),
			),
		),
	),

	'external-links-new-tab' => array(
		'title'       => __( 'Open external links in a new tab', 'scriptdock' ),
		'description' => __( 'Adds target="_blank" and rel="noopener" to links that point to other sites.', 'scriptdock' ),
		'category'    => 'content',
		'type'        => 'js',
		'location'    => 'site_footer',
		'file'        => 'external-links-new-tab.js',
		'tags'        => array( 'content' ),
		'options'     => array( 'strategy' => 'defer' ),
	),

	'featured-image-rss'     => array(
		'title'       => __( 'Featured images in RSS feeds', 'scriptdock' ),
		'description' => __( 'Adds each post’s featured image to the top of its feed content.', 'scriptdock' ),
		'category'    => 'content',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'featured-image-rss.txt',
		'tags'        => array( 'content' ),
	),

	'back-to-top'            => array(
		'title'       => __( 'Back-to-top button', 'scriptdock' ),
		'description' => __( 'A small accessible button that appears after scrolling down.', 'scriptdock' ),
		'category'    => 'content',
		'type'        => 'html',
		'location'    => 'site_footer',
		'file'        => 'back-to-top.html',
		'tags'        => array( 'design' ),
	),

	'reading-progress'       => array(
		'title'       => __( 'Reading progress bar', 'scriptdock' ),
		'description' => __( 'A thin bar at the top of single posts that fills as the reader scrolls.', 'scriptdock' ),
		'category'    => 'content',
		'type'        => 'html',
		'location'    => 'site_body_open',
		'file'        => 'reading-progress.html',
		'tags'        => array( 'design' ),
		'conditions'  => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array(
				array(
					array(
						'rule'     => 'page_type',
						'operator' => 'is',
						'value'    => array( 'single' ),
					),
				),
			),
		),
	),

	'announcement-bar'       => array(
		'title'       => __( 'Announcement bar', 'scriptdock' ),
		'description' => __( 'A dismissible bar at the top of every page. Edit the message and link in the code.', 'scriptdock' ),
		'category'    => 'content',
		'type'        => 'html',
		'location'    => 'site_body_open',
		'file'        => 'announcement-bar.html',
		'tags'        => array( 'design' ),
	),

	'maintenance-mode'       => array(
		'title'       => __( 'Maintenance mode', 'scriptdock' ),
		'description' => __( 'Shows a “back soon” page (HTTP 503) to visitors who are not logged in. Deactivate it to go live.', 'scriptdock' ),
		'category'    => 'content',
		'type'        => 'php',
		'location'    => 'php_frontend',
		'file'        => 'maintenance-mode.txt',
		'tags'        => array( 'maintenance' ),
	),

	/* ------------------------------------------------------------------- Login */

	'login-logo'             => array(
		'title'       => __( 'Custom login logo', 'scriptdock' ),
		'description' => __( 'Replaces the WordPress logo on the login page with your own image.', 'scriptdock' ),
		'category'    => 'login',
		'type'        => 'css',
		'location'    => 'login_header',
		'file'        => 'login-logo.css',
		'tags'        => array( 'branding' ),
		'fields'      => array(
			'logo_url' => array(
				'label'       => __( 'Logo image URL', 'scriptdock' ),
				'placeholder' => 'https://example.com/wp-content/uploads/logo.png',
				'pattern'     => '^https?://[A-Za-z0-9._~:/?#@!$&*+,;=%-]+$',
			),
		),
	),

	'login-logo-link'        => array(
		'title'       => __( 'Login logo links to your site', 'scriptdock' ),
		'description' => __( 'Points the login logo at your home page and uses your site title as its text.', 'scriptdock' ),
		'category'    => 'login',
		'type'        => 'php',
		'location'    => 'php_everywhere',
		'file'        => 'login-logo-link.txt',
		'tags'        => array( 'branding' ),
	),
);
