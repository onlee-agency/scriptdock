<?php
/**
 * Feature checks that run inside WordPress.
 * Run: wp eval-file /tests/features.php --user=admin
 *
 * The snippets it makes stay behind for tests/check-frontend.sh, so each run
 * removes the ones the run before it made. The site keeps one set of them,
 * however often the tests run.
 *
 * @package ScriptDock
 */

use ScriptDock\Compiler;
use ScriptDock\Global_Scripts;
use ScriptDock\Import_Export;
use ScriptDock\Library;
use ScriptDock\Page_Scripts;
use ScriptDock\Snippet;
use ScriptDock\Snippets;
use ScriptDock\Virtual_Files;

$failures = 0;
$check    = static function ( $label, $ok, $detail = '' ) use ( &$failures ) {
	WP_CLI::log( ( $ok ? '  PASS ' : '  FAIL ' ) . $label . ( $detail ? ' — ' . $detail : '' ) );
	if ( ! $ok ) {
		++$failures;
	}
};

// Everything this run is about to create, by the title it is created with.
// The imported copy of the GA4 snippet keeps the same title as the original.
$fixture_titles = array(
	'Google Analytics 4',
	'Google Tag Manager (head)',
	'Google Tag Manager (body)',
	'Change the excerpt length',
	'Scheduled future',
	'Scheduled expired',
	'Scheduled now',
	'Test mode only',
	'Delayed JS',
	'Idle JS',
);
$removed        = 0;
foreach ( $fixture_titles as $fixture_title ) {
	$old = get_posts(
		array(
			'post_type'   => 'scriptdock_snippet',
			'title'       => $fixture_title,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $old as $old_id ) {
		wp_delete_post( $old_id, true );
		++$removed;
	}
}
WP_CLI::log( sprintf( 'Fixtures from earlier runs removed: %d', $removed ) );

WP_CLI::log( 'Library' );
$bad = Library::create( 'ga4', array( 'measurement_id' => "G-ABC'); alert(1);//" ), true );
$check( 'rejects a malformed GA4 ID', is_wp_error( $bad ) );
$ids = Library::create( 'ga4', array( 'measurement_id' => 'G-TEST12345' ), true );
$check( 'creates GA4 snippet', is_array( $ids ) && 1 === count( $ids ) );
$ga4 = Snippet::get( $ids[0] );
$check( 'GA4 code contains the ID and no placeholders', false !== strpos( $ga4->code, "gtag('config', 'G-TEST12345')" ) && false === strpos( $ga4->code, '%%' ) );
$check( 'GA4 snippet is active and trusted', $ga4->active && $ga4->is_trusted() );
$gtm = Library::create( 'gtm', array( 'container_id' => 'GTM-ABC123' ), false );
$check( 'GTM creates two snippets', is_array( $gtm ) && 2 === count( $gtm ) );
$words = Library::create( 'excerpt-length', array( 'words' => '42' ), false );
$check( 'excerpt template inserts a number', is_array( $words ) && false !== strpos( Snippet::get( $words[0] )->code, 'return 42;' ) );
$templates = Library::templates();
$missing   = array();
foreach ( $templates as $id => $template ) {
	foreach ( Library::parts( $template ) as $part ) {
		if ( '' === trim( Library::code( $part ) ) ) {
			$missing[] = $id;
		}
		$probe       = new Snippet();
		$probe->type = $part['type'];
		$probe->code = Library::code( $part );
		foreach ( isset( $template['fields'] ) ? $template['fields'] : array() as $key => $field ) {
			$probe->code = str_replace( '%%' . $key . '%%', isset( $field['default'] ) ? $field['default'] : $field['placeholder'], $probe->code );
		}
		if ( $probe->lint() ) {
			$missing[] = $id . ' (syntax: ' . $probe->lint()['message'] . ')';
		}
	}
}
$check( 'all ' . count( $templates ) . ' templates have code with valid PHP syntax', ! $missing, implode( ', ', $missing ) );

WP_CLI::log( 'Import / export' );
$payload = Import_Export::export( array( $ga4->id ) );
$check( 'export has one snippet', 1 === count( $payload['snippets'] ) );
$items = Import_Export::parse( Import_Export::to_json( $payload ) );
$check( 'export parses back', is_array( $items ) && 1 === count( $items ) );
$result = Import_Export::import( $items, array( 'activate' => false ) );
$check( 'import creates an inactive copy', 1 === $result['imported'] && 0 === $result['activated'] );
$cs_json = wp_json_encode(
	array(
		'generator' => 'Code Snippets v3.9',
		'snippets'  => array(
			array(
				'name'   => 'From CS export',
				'code'   => "add_filter( 'cs_export', '__return_true' );",
				'scope'  => 'front-end',
				'active' => true,
			),
		),
	)
);
$items = Import_Export::parse( $cs_json );
$check( 'Code Snippets JSON is recognised', is_array( $items ) && 'php_frontend' === $items[0]['location'] );
$check( 'invalid JSON is rejected', is_wp_error( Import_Export::parse( '{not json' ) ) );

WP_CLI::log( 'Virtual files' );
Virtual_Files::save(
	array(
		'ads_txt'      => "google.com, pub-123, DIRECT, f08c47fec0942fa0\r\n",
		'llms_txt'     => "# Site\n> Hello",
		'security_txt' => 'Contact: mailto:security@example.com',
		'robots_txt'   => "User-agent: GPTBot\nDisallow: /",
		'robots_mode'  => 'append',
	)
);
$data = Virtual_Files::get();
$check( 'ads.txt saved with normalised line endings', "google.com, pub-123, DIRECT, f08c47fec0942fa0" === $data['ads_txt'] );

WP_CLI::log( 'Global header & footer' );
Global_Scripts::save(
	array(
		'head'            => '<meta name="sd-global-head" content="{{site_name}}">',
		'footer'          => '<!-- sd-global-footer -->',
		'head_priority'   => 1,
		'footer_priority' => 10,
	)
);
$check( 'global code is signed', Global_Scripts::is_trusted() );

WP_CLI::log( 'Schedule and test mode' );
$future = new Snippet();
$future->fill(
	array(
		'title'    => 'Scheduled future',
		'type'     => 'html',
		'location' => 'site_footer',
		'code'     => '<!-- sd-scheduled-future -->',
		'schedule' => array( 'start' => wp_date( 'Y-m-d\TH:i', time() + DAY_IN_SECONDS ) ),
		'active'   => true,
	)
);
$future->save();
$expired = new Snippet();
$expired->fill(
	array(
		'title'    => 'Scheduled expired',
		'type'     => 'html',
		'location' => 'site_footer',
		'code'     => '<!-- sd-scheduled-expired -->',
		'schedule' => array( 'end' => wp_date( 'Y-m-d\TH:i', time() - HOUR_IN_SECONDS ) ),
		'active'   => true,
	)
);
$expired->save();
$current = new Snippet();
$current->fill(
	array(
		'title'    => 'Scheduled now',
		'type'     => 'html',
		'location' => 'site_footer',
		'code'     => '<!-- sd-scheduled-now -->',
		'schedule' => array(
			// A wide window, so the front-end checks still pass when they run a
			// while after the fixtures are made.
			'start' => wp_date( 'Y-m-d\TH:i', time() - DAY_IN_SECONDS ),
				'end'   => wp_date( 'Y-m-d\TH:i', time() + DAY_IN_SECONDS ),
		),
		'active'   => true,
	)
);
$current->save();
$test = new Snippet();
$test->fill(
	array(
		'title'    => 'Test mode only',
		'type'     => 'html',
		'location' => 'site_footer',
		'code'     => '<!-- sd-test-mode -->',
		'options'  => array( 'test_mode' => true, 'smart_tags' => true ),
		'active'   => true,
	)
);
$test->save();
$delay = new Snippet();
$delay->fill(
	array(
		'title'    => 'Delayed JS',
		'type'     => 'js',
		'location' => 'site_footer',
		'code'     => 'window.sdDelayed = ( window.sdDelayed || 0 ) + 1;',
		'options'  => array( 'strategy' => 'interaction', 'smart_tags' => true ),
		'active'   => true,
	)
);
$delay->save();
$idle = new Snippet();
$idle->fill(
	array(
		'title'    => 'Idle JS',
		'type'     => 'js',
		'location' => 'site_footer',
		'code'     => 'window.sdIdle = true;',
		'options'  => array( 'strategy' => 'idle', 'smart_tags' => true ),
		'active'   => true,
	)
);
$idle->save();

WP_CLI::log( 'Page scripts' );
$page_id = (int) get_option( 'sd_smoke_post' );
Page_Scripts::save(
	$page_id,
	array(
		'head'    => '<meta name="sd-page-head" content="{{post_id}}">',
		'css'     => '.sd-page-css{color:green}',
		'js'      => 'window.sdPageJs = "{{post_title}}";',
		'before'  => '<p class="sd-page-before">Page before</p>',
		'disable' => array( 16 ),
	)
);
$check( 'page scripts are signed', Page_Scripts::is_trusted( $page_id, Page_Scripts::get( $page_id ) ) );
WP_CLI::log( "  Smoke post is $page_id: run tests/check-frontend.sh with POST_ID=$page_id" );

Compiler::rebuild();
WP_CLI::log( $failures ? "FAILED: $failures" : 'ALL PASSED' );
