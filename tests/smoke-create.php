<?php
/**
 * Smoke test fixtures. Run: wp eval-file /tests/smoke-create.php
 *
 * @package ScriptDock
 */

use ScriptDock\Snippet;
use ScriptDock\Compiler;

// Clean slate: the snippets, and the post the last run left behind.
foreach ( get_posts( array( 'post_type' => 'scriptdock_snippet', 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
	wp_delete_post( $p->ID, true );
}
$last_post = (int) get_option( 'sd_smoke_post' );
if ( $last_post && get_post( $last_post ) ) {
	wp_delete_post( $last_post, true );
}

$post_id = wp_insert_post(
	array(
		'post_title'   => 'Smoke post',
		'post_content' => "<p>First paragraph.</p>\n<p>Second paragraph.</p>\n<p>Third paragraph.</p>",
		'post_status'  => 'publish',
	)
);
update_option( 'sd_smoke_post', $post_id );

$fixtures = array(
	array( 'title' => 'HTML head', 'type' => 'html', 'location' => 'site_header', 'code' => '<meta name="sd-test" content="{{post_id}}|{{post_title}}">' ),
	array( 'title' => 'CSS inline', 'type' => 'css', 'location' => 'site_header', 'code' => "/* comment */\nbody  {\n  --sd-inline : 1 ;\n  content: \"a  b\";\n}\n" ),
	array( 'title' => 'JS inline', 'type' => 'js', 'location' => 'site_footer', 'code' => 'window.sdInline = "{{post_title}}";' ),
	array( 'title' => 'PHP everywhere', 'type' => 'php', 'location' => 'php_everywhere', 'code' => "<?php\nadd_action( 'wp_footer', function () { echo '<!-- sd-php-everywhere -->'; } );" ),
	array( 'title' => 'Before content', 'type' => 'html', 'location' => 'before_content', 'code' => '<div class="sd-before">Before</div>' ),
	array( 'title' => 'After paragraph 2', 'type' => 'html', 'location' => 'after_paragraph', 'location_args' => array( 'paragraph' => 2 ), 'code' => '<div class="sd-after-p2">AfterP2</div>' ),
	array( 'title' => 'Shortcode HTML', 'type' => 'html', 'location' => 'shortcode', 'code' => '<span class="sd-sc">{{attr:color}}</span>' ),
	array( 'title' => 'JS file defer', 'type' => 'js', 'location' => 'site_footer', 'code' => 'window.sdFile = 1;', 'options' => array( 'output' => 'file', 'strategy' => 'defer', 'smart_tags' => true ) ),
	array( 'title' => 'CSS file', 'type' => 'css', 'location' => 'site_header', 'code' => '.sd-file { color: red; }', 'options' => array( 'output' => 'file' ) ),
	array( 'title' => 'Consent HTML', 'type' => 'html', 'location' => 'site_footer', 'code' => '<script>window.sdConsent = 1;</script><script type="application/ld+json">{"a":1}</script>', 'options' => array( 'consent' => 'marketing', 'smart_tags' => true ) ),
	array( 'title' => 'Universal footer', 'type' => 'universal', 'location' => 'site_footer', 'code' => '<p class="sd-universal"><?php echo esc_html( strtoupper( "universal" ) ); ?></p>' ),
	array(
		'title'      => 'Front page only',
		'type'       => 'html',
		'location'   => 'site_footer',
		'code'       => '<!-- sd-front-only -->',
		'conditions' => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array( array( array( 'rule' => 'page_type', 'operator' => 'is', 'value' => array( 'front_page' ) ) ) ),
		),
	),
	array( 'title' => 'Fatal frontend', 'type' => 'php', 'location' => 'php_frontend', 'code' => "add_action( 'wp_footer', function () { sd_this_function_does_not_exist(); } );" ),
);

foreach ( $fixtures as $fixture ) {
	$snippet = new Snippet();
	$snippet->fill( array_merge( array( 'active' => true ), $fixture ) );
	$id = $snippet->save();
	WP_CLI::log( sprintf( '#%d %s', $id, $fixture['title'] ) );
}

$content = get_post( $post_id );
wp_update_post(
	array(
		'ID'           => $post_id,
		'post_content' => $content->post_content . "\n" . '[scriptdock id="' . ( $id - 6 ) . '" color="blue<b>"]',
	)
);

Compiler::rebuild();
WP_CLI::log( 'post: ' . get_permalink( $post_id ) );
