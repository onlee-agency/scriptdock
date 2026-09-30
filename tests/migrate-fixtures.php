<?php
/**
 * Creates sample data in competitor plugins (they must be active).
 * Run: wp eval-file /tests/migrate-fixtures.php
 *
 * @package ScriptDock
 */

global $wpdb;

// Start clean so the script can be re-run.
foreach ( get_posts( array( 'post_type' => array( 'wpcode', 'custom-css-js' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $old ) {
	wp_delete_post( $old->ID, true );
}
$wpdb->query( "DELETE FROM {$wpdb->prefix}hfcm_scripts" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}snippets WHERE name LIKE 'CS %'" );

// WPCode: a PHP snippet everywhere, an HTML header snippet with rules, a paragraph snippet.
$wpcode = array(
	array(
		'title'       => 'WPC PHP everywhere',
		'code'        => "add_filter( 'wpc_migrated', '__return_true' );",
		'code_type'   => 'php',
		'location'    => 'everywhere',
		'auto_insert' => 1,
		'active'      => true,
		'priority'    => 7,
		'tags'        => array( 'wpc-tag' ),
	),
	array(
		'title'       => 'WPC HTML header rules',
		'code'        => '<meta name="wpc-migrated" content="1">',
		'code_type'   => 'html',
		'location'    => 'site_wide_header',
		'auto_insert' => 1,
		'active'      => true,
		'use_rules'   => true,
		'rules'       => array(
			'show'   => 'show',
			'groups' => array(
				array(
					array(
						'type'     => 'page',
						'option'   => 'type_of_page',
						'relation' => '=',
						'value'    => 'is_front_page',
					),
					array(
						'type'     => 'user',
						'option'   => 'logged_in',
						'relation' => '=',
						'value'    => '0',
					),
				),
			),
		),
	),
	array(
		'title'         => 'WPC after paragraph 2',
		'code'          => '<div class="wpc-p2">ad</div>',
		'code_type'     => 'html',
		'location'      => 'after_paragraph',
		'auto_insert'   => 1,
		'insert_number' => 2,
		'active'        => false,
	),
	array(
		'title'       => 'WPC shortcode only',
		'code'        => '<b>wpc shortcode</b>',
		'code_type'   => 'html',
		'auto_insert' => 0,
		'active'      => true,
	),
	// Awkward content: nothing here may come out changed.
	array(
		'title'       => 'WPC tricky PHP — naïve café 🚀 עברית',
		'code'        => <<<'PHP'
<?php
$greeting = "Say \"hi\" & <b>bye</b> – naïve café 🚀";
add_filter( 'wpc_tricky', static function () use ( $greeting ) { return $greeting . '\\'; } );
PHP,
		'code_type'   => 'php',
		'location'    => 'frontend_only',
		'auto_insert' => 1,
		'active'      => false,
		'priority'    => 20,
		'tags'        => array( 'wpc-tag', 'second-tag' ),
	),
	array(
		'title'       => 'WPC JS with markup in strings',
		'code'        => <<<'JS'
if ( 1 < 2 && 3 > 2 ) { document.title += ' &amp; "quoted" <\/script>'; }
JS,
		'code_type'   => 'js',
		'location'    => 'site_wide_footer',
		'auto_insert' => 1,
		'active'      => true,
		'device_type' => 'mobile',
		'schedule'    => array(
			'start' => '2026-01-01 09:30:00',
			'end'   => '2030-12-31 18:00:00',
		),
	),
	array(
		'title'        => 'WPC CSS child selectors',
		'code'         => '.wpc > .child::before { content: "\201C"; } /* ünïcödé */',
		'code_type'    => 'css',
		'location'     => 'site_wide_header',
		'auto_insert'  => 1,
		'active'       => true,
		'load_as_file' => true,
	),
	array(
		'title'         => 'WPC before paragraph 3',
		'code'          => '<aside class="wpc-p3">&nbsp;note&nbsp;</aside>',
		'code_type'     => 'html',
		'location'      => 'before_paragraph',
		'auto_insert'   => 1,
		'insert_number' => 3,
		'active'        => true,
	),
);
foreach ( $wpcode as $data ) {
	$snippet = new WPCode_Snippet( $data );
	WP_CLI::log( 'WPCode #' . $snippet->save() );
}
update_option( 'ihaf_insert_header', '<meta name="wpc-global-header" content="1">' );

// Code Snippets (its table and example snippets are created on activation).
$table = $wpdb->prefix . 'snippets';
$wpdb->insert(
	$table,
	array(
		'name'        => 'CS global',
		'description' => '<p>Code Snippets description</p>',
		'code'        => "add_filter( 'cs_migrated', '__return_true' );",
		'tags'        => 'one, two',
		'scope'       => 'global',
		'priority'    => 5,
		'active'      => 1,
	)
);
$wpdb->insert(
	$table,
	array(
		'name'        => 'CS site css',
		'description' => '',
		'code'        => '.cs-migrated{color:red}',
		'tags'        => '',
		'scope'       => 'site-css',
		'priority'    => 10,
		'active'      => 0,
	)
);
$wpdb->insert(
	$table,
	array(
		'name'        => 'CS tricky — ünïcödé 🚀',
		'description' => 'Plain & simple <em>note</em>',
		'code'        => <<<'PHP'
<?php
// Opening tag typed by hand.
add_filter( 'cs_tricky', static function () { return "a\\b 'q' \"dq\" <tag>"; } );
PHP,
		'tags'        => 'alpha, beta gamma',
		'scope'       => 'front-end',
		'priority'    => 3,
		'active'      => 1,
	)
);
$wpdb->insert(
	$table,
	array(
		'name'        => 'CS content shortcode',
		'description' => '',
		'code'        => '<p class="cs-content">A &amp; B &lt;tag&gt; ✓</p>',
		'tags'        => '',
		'scope'       => 'content',
		'priority'    => 10,
		'active'      => 1,
	)
);
$wpdb->insert(
	$table,
	array(
		'name'        => 'CS footer js',
		'description' => '',
		'code'        => 'window.cs = { a: 1 < 2, b: "</div>" };',
		'tags'        => '',
		'scope'       => 'site-footer-js',
		'priority'    => 10,
		'active'      => 1,
	)
);
WP_CLI::log( 'Code Snippets rows: ' . $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );

// Header Footer Code Manager.
$wpdb->insert(
	$wpdb->prefix . 'hfcm_scripts',
	array(
		'name'           => 'HFCM specific pages',
		'snippet'        => htmlentities( '<script>window.hfcm = "1";</script>' ),
		'snippet_type'   => 'js',
		'device_type'    => 'mobile',
		'location'       => 'footer',
		'display_on'     => 's_posts',
		's_posts'        => wp_json_encode( array( '4' ) ),
		'ex_pages'       => wp_json_encode( array() ),
		'ex_posts'       => wp_json_encode( array() ),
		's_pages'        => wp_json_encode( array() ),
		'lp_count'       => 5,
		'status'         => 'active',
		'created_by'     => 'admin',
		'created'        => current_time( 'mysql' ),
		'spt_display_on' => 'both',
	)
);
$wpdb->insert(
	$wpdb->prefix . 'hfcm_scripts',
	array(
		'name'           => 'HFCM tricky entities',
		// Stored the way HFCM stores it: htmlentities() of what was typed.
		'snippet'        => htmlentities( '<a href="/?a=1&amp;b=2" title="x &lt; y – café 🚀">link</a>' ),
		'snippet_type'   => 'html',
		'device_type'    => 'both',
		'location'       => 'header',
		'display_on'     => 'All',
		's_posts'        => wp_json_encode( array() ),
		'ex_pages'       => wp_json_encode( array() ),
		'ex_posts'       => wp_json_encode( array() ),
		's_pages'        => wp_json_encode( array() ),
		'lp_count'       => 5,
		'status'         => 'active',
		'created_by'     => 'admin',
		'created'        => current_time( 'mysql' ),
		'spt_display_on' => 'both',
	)
);
WP_CLI::log( 'HFCM rows: ' . $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hfcm_scripts" ) );

// Simple Custom CSS and JS.
$css_id = wp_insert_post(
	array(
		'post_type'    => 'custom-css-js',
		'post_title'   => 'SCCJ CSS',
		'post_content' => '.sccj{color:blue}',
		'post_status'  => 'publish',
	)
);
update_post_meta(
	$css_id,
	'options',
	array(
		'type'     => 'header',
		'linking'  => 'external',
		'side'     => 'frontend,admin',
		'priority' => 3,
		'language' => 'css',
	)
);
$js_id = wp_insert_post(
	array(
		'post_type'    => 'custom-css-js',
		'post_title'   => 'SCCJ JS',
		'post_content' => 'console.log("sccj");',
		'post_status'  => 'publish',
	)
);
update_post_meta(
	$js_id,
	'options',
	array(
		'type'     => 'footer',
		'linking'  => 'internal',
		'side'     => 'frontend',
		'priority' => 5,
		'language' => 'js',
	)
);
update_post_meta( $js_id, '_active', 'no' );
$tricky_id = wp_insert_post(
	wp_slash(
		array(
			'post_type'    => 'custom-css-js',
			'post_title'   => 'SCCJ tricky CSS',
			'post_content' => ".sccj > p::after { content: '→ ✓'; } /* \"quoted\" \\ */",
			'post_status'  => 'publish',
		)
	)
);
update_post_meta(
	$tricky_id,
	'options',
	array(
		'type'     => 'header',
		'linking'  => 'internal',
		'side'     => 'frontend',
		'priority' => 5,
		'language' => 'css',
	)
);
WP_CLI::log( 'SCCJ posts: ' . $css_id . ', ' . $js_id . ', ' . $tricky_id );
