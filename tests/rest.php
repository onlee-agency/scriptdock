<?php
/**
 * REST API checks that run inside WordPress, through rest_do_request().
 * Run: wp eval-file /tests/rest.php --user=admin
 *
 * Creates its own snippets and tags (titled "REST test …") and removes them
 * at the end. Fatal errors during a trial run end the request, so those are
 * covered over HTTP by tests/check-rest.sh instead.
 *
 * @package ScriptDock
 */

use ScriptDock\Snippet;
use ScriptDock\Post_Type;

$failures = 0;
$check    = static function ( $label, $ok, $detail = '' ) use ( &$failures ) {
	WP_CLI::log( ( $ok ? '  PASS ' : '  FAIL ' ) . $label . ( $detail ? ' — ' . $detail : '' ) );
	if ( ! $ok ) {
		++$failures;
	}
};

$call = static function ( $method, $route, array $params = array() ) {
	$request = new WP_REST_Request( $method, '/scriptdock/v1' . $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$response = rest_do_request( $request );
	return array( $response->get_status(), $response->get_data() );
};

$make = static function ( array $data ) {
	$snippet = new Snippet();
	$snippet->fill( $data + array( 'type' => 'html', 'location' => 'site_footer', 'code' => '<!-- rest test -->' ) );
	$snippet->save();
	return $snippet->id;
};

$created = array();
$make_tracked = static function ( array $data ) use ( $make, &$created ) {
	$id        = $make( $data );
	$created[] = $id;
	return $id;
};

$sentence = static function ( $id ) use ( $call ) {
	list( , $data ) = $call( 'GET', '/snippets/' . $id );
	return $data['targeting'];
};

WP_CLI::log( 'Targeting sentences' );
$page  = (int) get_option( 'sd_smoke_post' );
$ids   = array();
$ids[] = $make_tracked(
	array(
		'title'      => 'REST test page and device',
		'location'   => 'site_header',
		'conditions' => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array(
				array(
					array( 'rule' => 'page_type', 'operator' => 'is', 'value' => array( 'front_page' ) ),
					array( 'rule' => 'device', 'operator' => 'is', 'value' => array( 'mobile' ) ),
					array( 'rule' => 'logged_in', 'operator' => 'is', 'value' => array( 'logged_out' ) ),
				),
			),
		),
	)
);
$t = $sentence( end( $ids ) );
$check( 'placement, page and visitors, who before device', 'Runs in the site header on Front page, for logged-out visitors, on mobile and tablet.' === $t['text'], $t['text'] );
$check( 'short summary', array( 'Front page', 'Logged out', 'Mobile' ) === $t['summary'], implode( ' · ', $t['summary'] ) );
$chips = wp_list_pluck( array_filter( $t['parts'], static function ( $part ) {
	return isset( $part['chip'] );
} ), 'step' );
$check( 'chips carry their step', array( 'placement', 'content', 'audience', 'audience' ) === array_values( $chips ), implode( ',', $chips ) );

$ids[] = $make_tracked(
	array(
		'title'      => 'REST test union',
		'conditions' => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array(
				array( array( 'rule' => 'post', 'operator' => 'is', 'value' => array( $page ) ) ),
				array( array( 'rule' => 'url', 'operator' => 'contains', 'value' => array( '/blog' ) ) ),
			),
		),
	)
);
$t = $sentence( end( $ids ) );
$check( 'groups of pages read as a list', 'Runs in the site footer on Smoke post and URLs containing /blog.' === $t['text'], $t['text'] );
$check( 'list summary counts the rest', array( 'Smoke post + 1 more' ) === $t['summary'], implode( ' · ', $t['summary'] ) );

$ids[] = $make_tracked(
	array(
		'title'      => 'REST test hide',
		'conditions' => array(
			'enabled' => true,
			'action'  => 'hide',
			'groups'  => array( array( array( 'rule' => 'page_type', 'operator' => 'is', 'value' => array( 'search', '404' ) ) ) ),
		),
	)
);
$t = $sentence( end( $ids ) );
$check( 'hiding reads as except', 'Runs in the site footer on every page except Search results and 404 page.' === $t['text'], $t['text'] );
$check( 'two page types are named in the list', array( 'Except Search results, 404 page' ) === $t['summary'], implode( ' · ', $t['summary'] ) );

$ids[] = $make_tracked(
	array(
		'title'      => 'REST test custom',
		'conditions' => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array(
				array(
					array( 'rule' => 'page_type', 'operator' => 'is', 'value' => array( 'page' ) ),
					array( 'rule' => 'url', 'operator' => 'contains', 'value' => array( '/shop' ) ),
				),
			),
		),
	)
);
$t = $sentence( end( $ids ) );
$check( 'two page rules that must both match are custom', $t['custom'] && array( 'Custom rules' ) === $t['summary'], $t['text'] );

$ids[] = $make_tracked(
	array(
		'title'      => 'REST test time',
		'schedule'   => array( 'start' => wp_date( 'Y' ) . '-10-01T09:00', 'end' => '' ),
		'conditions' => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array(
				array(
					array( 'rule' => 'day_of_week', 'operator' => 'is', 'value' => array( '1', '2', '3', '4', '5' ) ),
					array( 'rule' => 'time_of_day', 'operator' => 'between', 'value' => array( 'from' => '09:00', 'to' => '17:00' ) ),
				),
			),
		),
	)
);
$t     = $sentence( end( $ids ) );
$facts = wp_list_pluck( $t['facts'], 'value', 'key' );
$check( 'weekdays collapse to a range', false !== strpos( $t['text'], 'on Mon–Fri' ), $t['text'] );
$check( 'time of day reads as a range', false !== strpos( $t['text'], ', 9:00 am–5:00 pm,' ), $t['text'] );
$check( 'schedule joins the sentence', false !== strpos( $t['text'], 'from 1 Oct at 9:00 am.' ), $t['text'] );
$check( 'facts list the time', isset( $facts['time'] ) && false !== strpos( $facts['time'], 'Mon–Fri' ), isset( $facts['time'] ) ? $facts['time'] : '' );

$ids[] = $make_tracked(
	array(
		'title'         => 'REST test hook',
		'type'          => 'php',
		'location'      => 'custom_hook',
		'location_args' => array( 'hook' => 'rest_test_hook' ),
		'code'          => 'echo 1;',
	)
);
$t = $sentence( end( $ids ) );
$check( 'hook name is a code chip', 'Runs on the rest_test_hook action.' === $t['text'] && ! empty( $t['parts'][1]['code'] ), $t['text'] );

WP_CLI::log( 'List, filters and counts' );
list( $status, $all ) = $call( 'GET', '/snippets', array( 'per_page' => 100 ) );
$check( 'list answers 200', 200 === $status );
$counts = $all['counts'];
$check( 'all is active + inactive', $counts['all'] === $counts['active'] + $counts['inactive'], wp_json_encode( $counts ) );
list( , $php ) = $call( 'GET', '/snippets', array( 'type' => array( 'php' ), 'per_page' => 100 ) );
$check( 'type filter', $php['items'] && ! array_diff( array_unique( wp_list_pluck( $php['items'], 'type' ) ), array( 'php' ) ) );
list( , $found ) = $call( 'GET', '/snippets', array( 'search' => 'REST test union' ) );
$check( 'search by title', 1 === $found['total'], (string) $found['total'] );
list( , $found ) = $call( 'GET', '/snippets', array( 'search' => 'rest test -->' ) );
$check( 'search in code', $found['total'] >= 5, (string) $found['total'] );
list( , $found ) = $call( 'GET', '/snippets', array( 'targeting' => array( 'conditional' ), 'search' => 'REST test' ) );
$check( 'targeting filter', 5 === $found['total'], (string) $found['total'] );
list( , $found ) = $call( 'GET', '/snippets', array( 'orderby' => 'title', 'order' => 'asc', 'search' => 'REST test' ) );
$titles = wp_list_pluck( $found['items'], 'title' );
$sorted = $titles;
natcasesort( $sorted );
$check( 'sort by title', array_values( $sorted ) === $titles, implode( ', ', $titles ) );
list( , $paged ) = $call( 'GET', '/snippets', array( 'per_page' => 2, 'page' => 2 ) );
$check( 'pagination', 2 === count( $paged['items'] ) && 2 === $paged['page'] && $paged['total_pages'] >= 2 );
$check( 'months facet', ! empty( $all['months'] ) && preg_match( '/^\d{6}$/', $all['months'][0]['value'] ) );
list( , $month ) = $call( 'GET', '/snippets', array( 'month' => $all['months'][0]['value'], 'search' => 'REST test' ) );
$check( 'month filter', $month['total'] >= 6, (string) $month['total'] );
list( $status ) = $call( 'GET', '/snippets', array( 'view' => 'nonsense' ) );
$check( 'bad view is rejected', 400 === $status, (string) $status );

WP_CLI::log( 'Switching on and off' );
$html = $make_tracked( array( 'title' => 'REST test toggle' ) );
list( $status, $data ) = $call( 'POST', '/snippets/' . $html . '/activate' );
$check( 'activate answers with the row', 200 === $status && $data['item']['active'] && $data['item']['running'], (string) $status );
$check( 'activate answers with counts', isset( $data['counts']['active'] ) );
list( $status, $data ) = $call( 'POST', '/snippets/' . $html . '/deactivate' );
$check( 'deactivate', 200 === $status && ! $data['item']['active'] );

$throws = $make_tracked( array( 'title' => 'REST test throws', 'type' => 'php', 'location' => 'php_everywhere', 'code' => "throw new Exception( 'rest test boom' );" ) );
list( $status, $data ) = $call( 'POST', '/snippets/' . $throws . '/activate' );
$check( 'a snippet that throws is refused', 422 === $status && 'scriptdock_test_error' === $data['code'], $status . ' ' . ( isset( $data['code'] ) ? $data['code'] : '' ) );
$check( 'the error names the line', isset( $data['data']['error']['line'] ) && 1 === $data['data']['error']['line'] );
$check( 'it stays switched off', 'draft' === get_post_status( $throws ) );
list( , $row ) = $call( 'GET', '/snippets/' . $throws );
$check( 'the error is recorded', ! empty( $row['error']['message'] ) && in_array( 'error', wp_list_pluck( $row['badges'], 'key' ), true ) );

$syntax = $make_tracked( array( 'title' => 'REST test syntax', 'type' => 'php', 'location' => 'php_everywhere', 'code' => 'echo 1' ) );
list( $status, $data ) = $call( 'POST', '/snippets/' . $syntax . '/activate' );
$check( 'a syntax error is refused', 422 === $status && 'scriptdock_syntax' === $data['code'], (string) $status );

$tampered = $make_tracked( array( 'title' => 'REST test tampered', 'active' => true ) );
global $wpdb;
$wpdb->update( $wpdb->posts, array( 'post_content' => '<!-- changed outside -->' ), array( 'ID' => $tampered ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
clean_post_cache( $tampered );
list( , $row ) = $call( 'GET', '/snippets/' . $tampered );
$check( 'a changed snippet is paused', $row['paused'] && ! $row['running'] && 'changed' === $row['signature'] );
$check( 'and badged', in_array( 'review', wp_list_pluck( $row['badges'], 'key' ), true ) );
$call( 'POST', '/snippets/' . $tampered . '/deactivate' );
list( $status, $data ) = $call( 'POST', '/snippets/' . $tampered . '/activate' );
$check( 'switching a changed snippet on is refused', 409 === $status && 'scriptdock_untrusted' === $data['code'], (string) $status );
list( , $review ) = $call( 'GET', '/snippets', array( 'view' => 'review' ) );
$check( 'review view', in_array( $tampered, wp_list_pluck( $review['items'], 'id' ), true ) );
$wpdb->update( $wpdb->posts, array( 'post_content' => "<!-- rest test -->\n<script src=\"https://cdn-analytics-cache.xyz/a.js\"></script>" ), array( 'ID' => $tampered ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
clean_post_cache( $tampered );
list( $status, $data ) = $call( 'GET', '/snippets/' . $tampered . '/changes' );
$check( 'changes compare with the last signed version', 200 === $status && 1 === $data['added'] && 0 === $data['removed'] && $data['base']['trusted'], wp_json_encode( array( $data['added'], $data['removed'], $data['base'] ) ) );
$check( 'changes name the new site', array( 'cdn-analytics-cache.xyz' ) === $data['domains'] && false !== strpos( $data['text'], 'cdn-analytics-cache.xyz' ), $data['text'] );
$kinds = wp_list_pluck( $data['lines'], 'type' );
$check( 'changes carry the added line', in_array( 'add', $kinds, true ) && ! in_array( 'remove', $kinds, true ) );
list( , $row ) = $call( 'GET', '/snippets/' . $tampered );
$check( 'the row carries the change summary', isset( $row['review']['text'] ) && $row['review']['text'] === $data['text'] );
list( $status ) = $call( 'POST', '/snippets/' . $tampered . '/duplicate' );
$check( 'a changed snippet cannot be duplicated before review', 409 === $status, (string) $status );
list( $status, $data ) = $call( 'PUT', '/snippets/' . $tampered, array( 'title' => 'REST test changed, renamed' ) );
$check( 'renaming a changed snippet does not approve it', 200 === $status && ! $data['item']['trusted'], (string) $status );
list( $status, $data ) = $call( 'POST', '/snippets/' . $tampered . '/approve' );
$check( 'approve trusts the current code', 200 === $status && $data['item']['trusted'] && null === $data['item']['review'], (string) $status );
list( , $log ) = $call( 'GET', '/snippets/' . $tampered . '/revisions' );
$marks = wp_list_pluck( $log['items'], 'approved' );
$check( 'the approved version is marked in the history, and only that one', true === $marks[0] && 1 === count( array_filter( $marks ) ) && $log['items'][0]['same'], wp_json_encode( $marks ) );
$quiet = $make_tracked( array( 'title' => 'REST test changed in the database', 'active' => true ) );
$wpdb->update( $wpdb->posts, array( 'post_content' => '<!-- changed in the database -->' ), array( 'ID' => $quiet ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
clean_post_cache( $quiet );
$before = count( wp_get_post_revisions( $quiet ) );
$call( 'POST', '/snippets/' . $quiet . '/approve' );
list( , $log ) = $call( 'GET', '/snippets/' . $quiet . '/revisions' );
$check( 'code changed straight in the database joins the history when approved', count( $log['items'] ) === $before + 1 && $log['items'][0]['approved'] && $log['items'][0]['same'], wp_json_encode( array( $before, count( $log['items'] ) ) ) );

WP_CLI::log( 'Run on demand' );
$demand = $make_tracked( array( 'title' => 'REST test on demand', 'type' => 'php', 'location' => 'on_demand', 'code' => "echo 'ran ok';" ) );
list( $status, $data ) = $call( 'POST', '/snippets/' . $demand . '/run' );
$check( 'run returns output', 200 === $status && 'ran ok' === $data['output'], wp_json_encode( $data ) );

WP_CLI::log( 'Duplicate, trash and delete' );
list( $status, $data ) = $call( 'POST', '/snippets/' . $html . '/duplicate' );
$copy      = $data['item']['id'];
$created[] = $copy;
$check( 'duplicate makes a switched-off copy', 201 === $status && ! $data['item']['active'] && false !== strpos( $data['item']['title'], '(copy)' ) );
list( $status, $data ) = $call( 'DELETE', '/snippets/' . $copy, array( 'force' => true ) );
$check( 'deleting outside the trash is refused', 409 === $status );
list( $status, $data ) = $call( 'DELETE', '/snippets/' . $copy );
$check( 'trash', 200 === $status && 'trash' === $data['item']['status'] );
list( , $trash ) = $call( 'GET', '/snippets', array( 'view' => 'trash' ) );
$check( 'trash view', in_array( $copy, wp_list_pluck( $trash['items'], 'id' ), true ) && $trash['counts']['trash'] >= 1 );
list( $status, $data ) = $call( 'POST', '/snippets/' . $copy . '/untrash' );
$check( 'untrash comes back switched off', 200 === $status && 'draft' === $data['item']['status'] );
$call( 'DELETE', '/snippets/' . $copy );
list( $status, $data ) = $call( 'DELETE', '/snippets/' . $copy, array( 'force' => true ) );
$check( 'delete permanently from the trash', 200 === $status && $data['deleted'] && ! get_post( $copy ) );

WP_CLI::log( 'The editor saves' );
list( $status, $data ) = $call(
	'POST',
	'/snippets',
	array(
		'title'  => 'REST test created',
		'type'   => 'html',
		'code'   => '<b>hello</b>',
		'notes'  => 'A note',
		'tags'   => array( 'REST test editor tag' ),
		'active' => true,
	)
);
$new = isset( $data['item']['id'] ) ? (int) $data['item']['id'] : 0;
$created[] = $new;
$check( 'create answers 201 with the whole snippet', 201 === $status && $new && '<b>hello</b>' === $data['item']['code'] && 'A note' === $data['item']['notes'], (string) $status );
$check( 'it is switched on, signed and tagged', $new && $data['item']['active'] && 'valid' === $data['item']['signature'] && array( 'REST test editor tag' ) === wp_list_pluck( $data['item']['tags'], 'name' ) && null === $data['notice'] );
$check( 'the placement is the default for the type', $new && 'site_header' === $data['item']['placement']['key'], $new ? $data['item']['placement']['key'] : '' );

list( $status, $data ) = $call( 'PUT', '/snippets/' . $new, array( 'title' => 'REST test renamed in the editor' ) );
$check( 'update changes only what it was sent', 200 === $status && 'REST test renamed in the editor' === $data['item']['title'] && '<b>hello</b>' === $data['item']['code'] && $data['item']['active'] );

list( , $data ) = $call( 'PUT', '/snippets/' . $new, array( 'title' => "REST test <meta> tag  for 25%cafe\n& more", 'notes' => "Line one <b>kept</b>\r\nLine two %20" ) );
$check( 'titles and notes keep text that looks like HTML or an encoded character', 'REST test <meta> tag for 25%cafe & more' === $data['item']['title'] && "Line one <b>kept</b>\nLine two %20" === $data['item']['notes'], wp_json_encode( array( $data['item']['title'], $data['item']['notes'] ) ) );

list( , $data ) = $call( 'PUT', '/snippets/' . $new, array( 'options' => array( 'shortcodes' => true ) ) );
$check( 'options merge into the saved ones, the rest keep their values', true === $data['item']['options']['shortcodes'] && true === $data['item']['options']['smart_tags'] && 'inline' === $data['item']['options']['output'], wp_json_encode( $data['item']['options'] ) );

list( $status, $data ) = $call(
	'POST',
	'/snippets',
	array(
		'title'  => 'REST test broken on create',
		'type'   => 'php',
		'code'   => "add_filter( 'rest_test_broken', function () {\n\treturn 1\n} );",
		'active' => true,
	)
);
$created[] = isset( $data['item']['id'] ) ? (int) $data['item']['id'] : 0;
$check( 'code that does not parse is saved and left switched off', 201 === $status && ! $data['item']['active'] && 'syntax' === $data['notice']['code'] && ! empty( $data['notice']['error']['line'] ), isset( $data['notice']['code'] ) ? $data['notice']['code'] : 'no notice' );

list( , $data ) = $call( 'PUT', '/snippets/' . $new, array( 'location' => 'custom_hook', 'location_args' => array( 'hook' => '' ), 'active' => true ) );
$check( 'a custom hook with no hook name is saved and left switched off', ! $data['item']['active'] && 'hook_missing' === $data['notice']['code'], isset( $data['notice']['code'] ) ? $data['notice']['code'] : 'no notice' );
list( , $data ) = $call( 'PUT', '/snippets/' . $new, array( 'location_args' => array( 'hook' => 'rest_test_hook' ), 'active' => true ) );
$check( 'and it goes on once the hook is named', $data['item']['active'] && null === $data['notice'] );

$wpdb->update( $wpdb->posts, array( 'post_content' => '<b>changed by hand</b>' ), array( 'ID' => $new ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
clean_post_cache( $new );
list( , $data ) = $call( 'GET', '/snippets/' . $new );
$check( 'a snippet changed outside ScriptDock reads as changed', 'changed' === $data['signature'], $data['signature'] );
list( , $data ) = $call( 'PUT', '/snippets/' . $new, array( 'code' => '<b>changed in the editor</b>' ) );
$check( 'saving it signs the code again', 'valid' === $data['item']['signature'] );

WP_CLI::log( 'Syntax checks and unsaved targeting' );
list( $status, $data ) = $call( 'POST', '/snippets/lint', array( 'type' => 'php', 'code' => "if ( true ) {\n\techo 'hi';" ) );
$check( 'lint reports the line', 200 === $status && ! empty( $data['error']['line'] ) && ! empty( $data['error']['message'] ), wp_json_encode( $data ) );
list( , $data ) = $call( 'POST', '/snippets/lint', array( 'type' => 'php', 'code' => "echo 'hi';" ) );
$check( 'lint stays quiet on good code', null === $data['error'], wp_json_encode( $data ) );

list( $status, $data ) = $call(
	'POST',
	'/snippets/describe',
	array(
		'type'       => 'html',
		'location'   => 'site_header',
		'conditions' => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array( array( array( 'rule' => 'page_type', 'operator' => 'is', 'value' => array( 'front_page' ) ) ) ),
		),
	)
);
$check( 'describe answers for settings that are not saved yet', 200 === $status && 'Runs in the site header on Front page.' === $data['targeting']['text'], isset( $data['targeting']['text'] ) ? $data['targeting']['text'] : '' );

WP_CLI::log( 'The targeting wizard' );
list( $status, $options ) = $call( 'GET', '/targeting/options' );
$groups = wp_list_pluck( $options['catalogue'], 'key' );
$rules  = array();
foreach ( $options['catalogue'] as $group ) {
	foreach ( $group['rules'] as $entry ) {
		$rules[] = $entry['rule'];
	}
}
$check( 'the catalogue has every group', 200 === $status && array( 'page', 'request', 'user', 'device', 'datetime', 'site', 'woocommerce', 'advanced' ) === $groups, implode( ',', $groups ) );
$check( 'and every rule the engine evaluates', ! array_diff( array_keys( ScriptDock\Conditions::RULE_OPERATORS ), $rules ), implode( ',', array_diff( array_keys( ScriptDock\Conditions::RULE_OPERATORS ), $rules ) ) );
$woo = null;
foreach ( $options['catalogue'] as $group ) {
	if ( 'woocommerce' === $group['key'] ) {
		$woo = $group['rules'][0];
	}
}
$check( 'WooCommerce rules are listed but locked without the plugin', $woo && ( ScriptDock\Registry::woocommerce_active() ? '' === $woo['locked'] : '' !== $woo['locked'] ), $woo ? $woo['locked'] : 'missing' );
$check( 'the lists cover what the steps pick from', ! array_diff( array( 'post_types', 'page_templates', 'roles', 'languages', 'themes', 'plugins', 'taxonomies', 'page_type', 'day_of_week' ), array_keys( $options['lists'] ) ) );
$days = $options['lists']['day_of_week'];
$check( 'days carry a short name for the chips', 7 === count( $days ) && ! empty( $days[0]['short'] ), wp_json_encode( $days[0] ) );
$check( 'the site timezone comes with it', ! empty( $options['timezone'] ), isset( $options['timezone'] ) ? $options['timezone'] : '' );

list( $status, $content ) = $call( 'GET', '/targeting/content', array( 'type' => 'page', 'per_page' => 3 ) );
$check( 'content comes a page at a time', 200 === $status && count( $content['items'] ) <= 3 && $content['total'] >= 1 );
$first = $content['items'] ? $content['items'][0] : array();
$check( 'each item carries what a card draws', isset( $first['title'], $first['path'], $first['status'], $first['date_label'], $first['children'] ), wp_json_encode( array_keys( $first ) ) );
list( $status ) = $call( 'GET', '/targeting/content', array( 'type' => 'scriptdock_snippet' ) );
$check( 'a type that cannot be browsed is refused', 400 === $status, (string) $status );
list( , $one ) = $call( 'GET', '/targeting/content', array( 'include' => array( $page ) ) );
$check( 'known IDs can be looked up for the tray', 1 === count( $one['items'] ) && $page === $one['items'][0]['id'] );

list( $status, $terms ) = $call( 'GET', '/targeting/terms', array( 'taxonomy' => 'category' ) );
$check( 'terms come with their counts', 200 === $status && $terms['items'] && isset( $terms['items'][0]['count'], $terms['items'][0]['parent'] ) );
list( , $people ) = $call( 'GET', '/targeting/users', array( 'search' => 'admin' ) );
$check( 'users can be searched', ! empty( $people['items'] ) );

list( $status, $all ) = $call( 'POST', '/targeting/estimate', array( 'conditions' => array() ) );
$check( 'no rules means every page', 200 === $status && $all['exact'] && $all['count'] === $all['total'] );
list( , $pages_only ) = $call(
	'POST',
	'/targeting/estimate',
	array(
		'conditions' => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array( array( array( 'rule' => 'post_type', 'operator' => 'is', 'value' => array( 'page' ) ) ) ),
		),
	)
);
$check( 'a content rule counts what it matches', ! $pages_only['exact'] && $pages_only['count'] > 0 && $pages_only['count'] <= $pages_only['total'] );
list( , $visitor ) = $call(
	'POST',
	'/targeting/estimate',
	array(
		'conditions' => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array( array( array( 'rule' => 'device', 'operator' => 'is', 'value' => array( 'mobile' ) ) ) ),
		),
	)
);
$check( 'a visitor rule says it cannot be counted in advance', $visitor['visitor'] && $visitor['count'] === $visitor['total'] );

list( $status, $tested ) = $call(
	'POST',
	'/targeting/test-url',
	array(
		'url'   => 'https://example.com/shop/kettles/?utm_source=news',
		'rules' => array(
			array( 'operator' => 'contains', 'value' => array( '/shop' ) ),
			array( 'operator' => 'is', 'value' => array( '/about' ) ),
		),
	)
);
$check( 'the URL tester says which rule matched', 200 === $status && $tested['matches'] && array( 0 ) === $tested['matched'], wp_json_encode( $tested ) );
list( , $missed ) = $call( 'POST', '/targeting/test-url', array( 'url' => '/blog/', 'rules' => array( array( 'operator' => 'starts_with', 'value' => array( '/shop' ) ) ) ) );
$check( 'and when none does', ! $missed['matches'] );

WP_CLI::log( 'Conditions the wizard added' );
$wizard = $make_tracked(
	array(
		'title'      => 'REST test wizard rules',
		'conditions' => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array(
				array(
					array( 'rule' => 'post_type', 'operator' => 'is', 'value' => array( 'page' ) ),
					array( 'rule' => 'logged_in', 'operator' => 'is', 'value' => array( 'logged_out' ) ),
				),
				array(
					array( 'rule' => 'post', 'operator' => 'is', 'value' => array( $page ) ),
					array( 'rule' => 'logged_in', 'operator' => 'is', 'value' => array( 'logged_out' ) ),
				),
			),
		),
	)
);
$t = $sentence( $wizard );
$check( 'rules repeated in every group read as one clause', false !== strpos( $t['text'], 'for logged-out visitors' ) && false === strpos( $t['text'], 'custom rules' ), $t['text'] );

$themed = $make_tracked(
	array(
		'title'      => 'REST test theme rule',
		'conditions' => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array( array( array( 'rule' => 'active_theme', 'operator' => 'is', 'value' => array( get_stylesheet() ) ) ) ),
		),
	)
);
$saved_theme = ScriptDock\Snippet::get( $themed );
$check( 'the active theme rule survives saving', 'active_theme' === $saved_theme->conditions['groups'][0][0]['rule'] );
$check( 'and matches the theme in use', ScriptDock\Conditions::match( $saved_theme->conditions ) );
$check( 'a rule the engine does not know is dropped', array() === ScriptDock\Conditions::sanitize( array( 'enabled' => true, 'action' => 'show', 'groups' => array( array( array( 'rule' => 'not_a_rule', 'operator' => 'is', 'value' => array( 1 ) ) ) ) ) )['groups'] );

WP_CLI::log( 'History' );
$versioned = $make_tracked( array( 'title' => 'REST test history', 'code' => '<!-- one -->' ) );
foreach ( array( '<!-- two -->', '<!-- three -->' ) as $round ) {
	$call( 'PUT', '/snippets/' . $versioned, array( 'code' => $round ) );
}
list( $status, $log ) = $call( 'GET', '/snippets/' . $versioned . '/revisions' );
$check( 'the history lists every saved version', 200 === $status && count( $log['items'] ) >= 2, (string) count( $log['items'] ) );
$check( 'with who saved it and how big it was', ! empty( $log['items'][0]['author'] ) && isset( $log['items'][0]['lines'], $log['items'][0]['approved'], $log['items'][0]['same'] ) );
$check( 'the code as it stands leads the list', isset( $log['current'] ) && $log['current']['approved'] && $log['can_restore'] );
$oldest = end( $log['items'] )['id'];

list( $status, $one ) = $call( 'GET', '/snippets/' . $versioned . '/revisions/' . $oldest );
$check( 'one version carries its code and what changed since', 200 === $status && '<!-- one -->' === $one['code'] && '<!-- three -->' === $one['current'] && 1 === $one['diff']['added'] && 1 === $one['diff']['removed'], wp_json_encode( array( $one['code'], $one['diff']['added'], $one['diff']['removed'] ) ) );
$check( 'the newest version matches the code now', $log['items'][0]['same'] );
$check( 'versions saved in the editor are not marked as approved after review', ! in_array( true, wp_list_pluck( $log['items'], 'approved' ), true ) );
list( $status ) = $call( 'GET', '/snippets/' . $versioned . '/revisions/999999' );
$check( 'a version that is not this snippet is not found', 404 === $status, (string) $status );

list( $status, $back ) = $call( 'POST', '/snippets/' . $versioned . '/revisions/' . $oldest );
$check( 'restoring puts that code back', 200 === $status && '<!-- one -->' === $back['item']['code'] && 'restored' === $back['notice']['code'], (string) $status );
$check( 'and the code it replaced is still in the history', count( $back['item']['revisions']['latest'] ) >= 2 );

WP_CLI::log( 'Restoring code that cannot run' );
$brokenv = $make_tracked(
	array(
		'title'    => 'REST test restore broken',
		'type'     => 'php',
		'location' => 'php_frontend',
		'code'     => "add_filter( 'rest_test_broken_restore', function () {\n\treturn 1\n} );",
	)
);
$call( 'PUT', '/snippets/' . $brokenv, array( 'code' => "add_filter( 'rest_test_broken_restore', '__return_true' );", 'active' => true ) );
list( , $state ) = $call( 'GET', '/snippets/' . $brokenv );
$check( 'the good version is switched on', $state['active'] );
list( , $log ) = $call( 'GET', '/snippets/' . $brokenv . '/revisions' );
$first = end( $log['items'] )['id'];
list( $status, $restored ) = $call( 'POST', '/snippets/' . $brokenv . '/revisions/' . $first );
$check( 'restoring code that does not parse switches it off and says so', 200 === $status && ! $restored['item']['active'] && 'restored' !== $restored['notice']['code'], isset( $restored['notice']['code'] ) ? $restored['notice']['code'] : 'no notice' );
$check( 'the message explains what happened', false !== strpos( $restored['notice']['message'], 'Restored, but left switched off' ), $restored['notice']['message'] );

WP_CLI::log( 'Overview' );
list( $status, $over ) = $call( 'GET', '/overview' );
$check( 'the Overview answers in one request', 200 === $status && isset( $over['greeting'], $over['health'], $over['stats'], $over['recent'], $over['checklist'], $over['tip'], $over['library'] ) );
$check( 'the greeting names the person', false !== strpos( $over['greeting']['text'], wp_get_current_user()->display_name ) || false !== strpos( $over['greeting']['text'], wp_get_current_user()->first_name ), $over['greeting']['text'] );
$tiles = wp_list_pluck( $over['stats'], 'key' );
$check( 'four tiles, in the order the design sets', array( 'running', 'inactive', 'errors', 'pages' ) === $tiles, implode( ',', $tiles ) );
list( , $list ) = $call( 'GET', '/snippets' );
$running = 0;
foreach ( $over['stats'] as $tile ) {
	if ( 'running' === $tile['key'] ) {
		$running = $tile['count'];
	}
}
$check( 'the tiles agree with the list', $running === $list['counts']['running'], $running . ' vs ' . $list['counts']['running'] );
$check( 'recently edited is the newest first, at most five', count( $over['recent'] ) <= 5 && ( ! $over['recent'] || $over['recent'][0]['id'] === $list['items'][0]['id'] ) );
$check( 'the checklist counts what is done', $over['checklist']['total'] === count( $over['checklist']['items'] ) && $over['checklist']['done'] <= $over['checklist']['total'] );
$check( 'the library offers three to start from', 3 === count( $over['library']['cards'] ) && $over['library']['total'] > 3 );
$check( 'the safe mode link is a plain URL', false === strpos( $over['safeMode']['exit'], '&amp;' ), $over['safeMode']['exit'] );

$crashed = $make_tracked( array( 'title' => 'REST test overview error', 'type' => 'php', 'location' => 'php_frontend', 'code' => "echo 'hi';" ) );
update_post_meta(
	$crashed,
	ScriptDock\Snippet::META_ERROR,
	array(
		'message' => 'Call to undefined function rest_test_missing()',
		'line'    => 12,
		'fatal'   => true,
		'url'     => '/cart/',
		'time'    => time() - 300,
	)
);
ScriptDock\Notices::add_error( $crashed, 'REST test overview error', array( 'message' => 'Call to undefined function rest_test_missing()', 'line' => 12, 'time' => time() - 300 ) );
list( , $over ) = $call( 'GET', '/overview' );
$card = $over['health']['cards'] ? $over['health']['cards'][0] : array();
$check( 'a crash becomes a card with the line and the page', ! $over['health']['ok'] && isset( $card['body'] ) && false !== strpos( $card['body'], 'line 12' ) && false !== strpos( $card['body'], '/cart/' ), isset( $card['body'] ) ? $card['body'] : 'no card' );
$check( 'and the card can be dismissed the way the admin notice is', ! empty( $card['dismiss'] ) && false !== strpos( $card['dismiss'], 'scriptdock_dismiss_notice' ) );

WP_CLI::log( 'Header & Footer' );
$before_global = ScriptDock\Global_Scripts::get();
list( $status, $glob ) = $call( 'GET', '/global' );
$areas = wp_list_pluck( $glob['areas'], 'key' );
$check( 'the three areas come in order', 200 === $status && array( 'head', 'body', 'footer' ) === $areas, implode( ',', $areas ) );
$check( 'each area says where it prints', 'wp_head' === $glob['areas'][0]['hook'] && 'wp_footer' === $glob['areas'][2]['hook'] );
list( $status, $saved_global ) = $call( 'POST', '/global', array( 'head' => '<meta name="rest-test-global">', 'head_priority' => 3 ) );
$check( 'saving keeps what it was not sent', 200 === $status && '<meta name="rest-test-global">' === $saved_global['item']['areas'][0]['code'] && 3 === $saved_global['item']['areas'][0]['priority'] && $saved_global['item']['areas'][1]['code'] === $glob['areas'][1]['code'] );
$check( 'and signs the code', $saved_global['item']['trusted'] );
$raw         = get_option( 'scriptdock_global' );
$raw['head'] = '<meta name="rest-test-tampered">';
$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $raw ) ), array( 'option_name' => 'scriptdock_global' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_cache_flush();
list( , $after_edit ) = $call( 'GET', '/global' );
$check( 'code changed outside ScriptDock is not trusted', ! $after_edit['trusted'] );
list( $status ) = $call( 'POST', '/global', array( 'footer' => '<!-- rest test footer -->' ) );
$check( 'saving one area does not approve changes to the others', 409 === $status && ! ScriptDock\Global_Scripts::is_trusted(), (string) $status );
ScriptDock\Global_Scripts::save( $before_global );

WP_CLI::log( 'Site Files' );
$before_files = ScriptDock\Virtual_Files::get();
list( $status, $files ) = $call( 'GET', '/files' );
$names = wp_list_pluck( $files['files'], 'name' );
$check( 'every file is listed with robots on its own', 200 === $status && array( 'ads.txt', 'app-ads.txt', 'llms.txt', 'security.txt' ) === $names && 'robots.txt' === $files['robots']['name'], implode( ',', $names ) );
$first_file = $files['files'][0];
$check( 'a file with nothing in it reads as off', ( '' === trim( $first_file['content'] ) ) === ( 'off' === $first_file['state'] ), $first_file['state'] );
list( , $saved_files ) = $call( 'POST', '/files', array( 'ads_txt' => "google.com, pub-rest-test, DIRECT, f08c47fec0942fa0", 'robots_mode' => 'replace' ) );
$first = $saved_files['item']['files'][0];
$check( 'saving a file makes it live', 'live' === $first['state'] && false !== strpos( $first['content'], 'pub-rest-test' ), $first['state'] );
$check( 'and robots keeps the mode it was given', 'replace' === $saved_files['item']['robots']['mode'] );
ScriptDock\Virtual_Files::save( $before_files );

WP_CLI::log( 'Library' );
list( $status, $lib ) = $call( 'GET', '/library' );
$check( 'the library lists what ships with the plugin', 200 === $status && $lib['total'] > 40 && count( $lib['templates'] ) === $lib['total'] );
$cats = wp_list_pluck( $lib['categories'], 'key' );
$check( 'categories lead with All', 'all' === $cats[0] && count( $cats ) === count( ScriptDock\Library::categories() ) + 1 );
$ga4 = null;
foreach ( $lib['templates'] as $template ) {
	if ( 'ga4' === $template['id'] ) {
		$ga4 = $template;
	}
}
$check( 'a template carries its fields and parts', $ga4 && 1 === count( $ga4['fields'] ) && 'measurement_id' === $ga4['fields'][0]['key'] && 1 === count( $ga4['snippets'] ) );
list( $status, $bad ) = $call( 'POST', '/library/ga4', array( 'values' => array( 'measurement_id' => 'nope' ) ) );
$check( 'a value in the wrong format is refused', 400 === $status && 'measurement_id' === $bad['data']['field'], (string) $status );
list( $status, $made ) = $call( 'POST', '/library/ga4', array( 'values' => array( 'measurement_id' => 'G-RESTTEST01' ), 'activate' => false ) );
$check( 'adding a template creates its snippet', 201 === $status && 1 === count( $made['created'] ) && ! $made['created'][0]['active'], (string) $status );
$created_id = $made['created'][0]['id'];
$created[]  = $created_id;
$snippet    = ScriptDock\Snippet::get( $created_id );
$check( 'with the value filled in and the library as its source', false !== strpos( $snippet->code, 'G-RESTTEST01' ) && 'library:ga4' === $snippet->source );
list( , $lib_again ) = $call( 'GET', '/library' );
$ga4_again = null;
foreach ( $lib_again['templates'] as $template ) {
	if ( 'ga4' === $template['id'] ) {
		$ga4_again = $template;
	}
}
$check( 'and the card knows the site has it', $ga4_again['added'] && '' !== $ga4_again['edit_url'] );

WP_CLI::log( 'Import & Export' );
list( $status, $tools ) = $call( 'GET', '/tools' );
$check( 'the import screen answers', 200 === $status && isset( $tools['sources'], $tools['export']['cli'] ) );
$payload = ScriptDock\Import_Export::export( array( $created_id ) );
$had_imported = get_option( ScriptDock\Rest\Tools_Controller::IMPORTED_OPTION );
list( $status, $imported ) = $call( 'POST', '/tools/import', array( 'json' => ScriptDock\Import_Export::to_json( $payload ), 'activate' => false ) );
$check( 'an export file imports again', 200 === $status && 1 === $imported['imported'], (string) $status );
$check( 'importing ticks the checklist', (bool) get_option( ScriptDock\Rest\Tools_Controller::IMPORTED_OPTION ) );
if ( ! $had_imported ) {
	delete_option( ScriptDock\Rest\Tools_Controller::IMPORTED_OPTION );
}
foreach ( get_posts( array( 'post_type' => 'scriptdock_snippet', 'title' => $snippet->title, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $copy_id ) {
	$created[] = (int) $copy_id;
}
list( $status, $broken_json ) = $call( 'POST', '/tools/import', array( 'json' => '{not json' ) );
$check( 'a file that is not an export is refused', 400 === $status, (string) $status );

// Code Snippets' own export leaves out whatever is at its default, the
// "run everywhere" scope included.
$cs_items = ScriptDock\Import_Export::parse(
	wp_json_encode(
		array(
			'generator' => 'Code Snippets v3.10.2',
			'snippets'  => array(
				array(
					'id'       => 7,
					'name'     => 'REST test CS global',
					'code'     => "add_filter( 'rest_test_cs', '__return_true' );",
					'priority' => 5,
					'language' => 'php',
				),
				array(
					'name'  => 'REST test CS css',
					'code'  => '.x{}',
					'scope' => 'site-css',
				),
			),
		)
	)
);
$check(
	'a Code Snippets export without a scope is PHP that runs everywhere',
	is_array( $cs_items ) && 'REST test CS global' === $cs_items[0]['title'] && 'php' === $cs_items[0]['type'] && 'php_everywhere' === $cs_items[0]['location'] && 5 === $cs_items[0]['priority'] && 'css' === $cs_items[1]['type'],
	wp_json_encode( $cs_items )
);

WP_CLI::log( 'Settings' );
$before_settings = ScriptDock\Settings::all();
list( $status, $settings ) = $call( 'GET', '/settings' );
$check( 'settings come with what they mean here', 200 === $status && isset( $settings['values'], $settings['safe']['url'], $settings['tamper']['on'], $settings['php']['available'], $settings['about']['version'] ) );
$check( 'the safe mode link is a working URL', false !== strpos( $settings['safe']['url'], 'scriptdock_safe_mode' ) && false === strpos( $settings['safe']['url'], '&amp;' ) );
list( , $saved_settings ) = $call( 'POST', '/settings', array( 'values' => array( 'minify_css' => false, 'revisions' => 7 ) ) );
$check( 'saving changes only what it was sent', false === $saved_settings['item']['values']['minify_css'] && 7 === $saved_settings['item']['values']['revisions'] && $saved_settings['item']['values']['admin_bar'] === $before_settings['admin_bar'] );
list( , $capped ) = $call( 'POST', '/settings', array( 'values' => array( 'revisions' => 9999 ) ) );
$check( 'and keeps values in range', 200 === $capped['item']['values']['revisions'], (string) $capped['item']['values']['revisions'] );
$old_link = $settings['safe']['url'];
list( , $new_link ) = $call( 'POST', '/settings/safe-link' );
$check( 'a new safe mode link replaces the old one', $new_link['item']['safe']['url'] !== $old_link );
list( , $chosen ) = $call( 'POST', '/settings', array( 'values' => array( 'safe_mode_key' => 'aaaaaaaaaaaaaaaaaaaaaaaa' ) ) );
$check( 'nobody can choose the safe mode key', $chosen['item']['safe']['url'] === $new_link['item']['safe']['url'] );
$had_page_types = get_option( ScriptDock\Rest\Settings_Controller::PAGE_TYPES_OPTION );
$call( 'POST', '/settings', array( 'values' => array( 'page_post_types' => $before_settings['page_post_types'] ) ) );
$check( 'saving page-script types ticks the checklist', (bool) get_option( ScriptDock\Rest\Settings_Controller::PAGE_TYPES_OPTION ) );
if ( ! $had_page_types ) {
	delete_option( ScriptDock\Rest\Settings_Controller::PAGE_TYPES_OPTION );
}
// Written as it was, key included: saving through Settings::sanitize() would keep the new key.
update_option( ScriptDock\Settings::OPTION, $before_settings );

WP_CLI::log( 'Bulk' );
$b1 = $make_tracked( array( 'title' => 'REST test bulk one' ) );
$b2 = $make_tracked( array( 'title' => 'REST test bulk two' ) );
list( $status, $data ) = $call( 'POST', '/snippets/bulk', array( 'action' => 'activate', 'ids' => array( $b1, $b2, $syntax ) ) );
$check( 'bulk activate reports each snippet', 200 === $status && array( $b1, $b2 ) === $data['done'] && 1 === count( $data['failed'] ) && $syntax === $data['failed'][0]['id'] );
list( , $data ) = $call( 'POST', '/snippets/bulk', array( 'action' => 'add_tag', 'ids' => array( $b1, $b2 ), 'tag' => 'REST test tag' ) );
$tag = get_term_by( 'name', 'REST test tag', Post_Type::TAXONOMY );
$check( 'bulk add tag creates the tag', $tag && array( $b1, $b2 ) === $data['done'] );
list( , $tagged ) = $call( 'GET', '/snippets', array( 'tag' => array( $tag->term_id ) ) );
$check( 'tag filter', 2 === $tagged['total'] );
list( , $data ) = $call( 'POST', '/snippets/bulk', array( 'action' => 'trash', 'ids' => array( $b1, $b2 ) ) );
$check( 'bulk trash', 'trash' === get_post_status( $b1 ) && 'trash' === get_post_status( $b2 ) );
list( , $data ) = $call( 'POST', '/snippets/bulk', array( 'action' => 'untrash', 'ids' => array( $b1 ) ) );
$check( 'bulk untrash', 'draft' === get_post_status( $b1 ) );

WP_CLI::log( 'Export' );
list( $status, $data ) = $call( 'GET', '/snippets/export', array( 'ids' => array( $b1 ) ) );
$check( 'export one', 200 === $status && 1 === count( $data['data']['snippets'] ) && preg_match( '/^REST-test-bulk-one-\d{4}-\d{2}-\d{2}\.json$/', $data['filename'] ), isset( $data['filename'] ) ? $data['filename'] : '' );

WP_CLI::log( 'Tags' );
list( $status, $data ) = $call( 'POST', '/tags', array( 'name' => 'REST test other' ) );
$other = isset( $data['id'] ) ? $data['id'] : 0;
$check( 'create a tag', 201 === $status && 0 === $data['count'] );
list( $status ) = $call( 'POST', '/tags', array( 'name' => 'REST test other' ) );
$check( 'duplicate names are refused', 409 === $status );
list( , $tags ) = $call( 'GET', '/tags' );
$by = wp_list_pluck( $tags, 'count', 'name' );
$check( 'counts include switched-off snippets', isset( $by['REST test tag'] ) && 1 === $by['REST test tag'], wp_json_encode( $by ) );
list( $status, $data ) = $call( 'PUT', '/tags/' . $other, array( 'name' => 'REST test renamed' ) );
$check( 'rename', 200 === $status && 'REST test renamed' === $data['name'] );
list( $status ) = $call( 'PUT', '/tags/' . $other, array( 'name' => 'REST test tag' ) );
$check( 'renaming onto another tag is refused', 409 === $status );
list( $status, $data ) = $call( 'POST', '/tags/' . $tag->term_id . '/merge', array( 'into' => $other ) );
$check( 'merge moves snippets and removes the tag', 200 === $status && 1 === $data['into']['count'] && ! get_term( $tag->term_id, Post_Type::TAXONOMY ) );
list( $status ) = $call( 'DELETE', '/tags/' . $other );
$check( 'delete a tag', 200 === $status && ! get_term( $other, Post_Type::TAXONOMY ) );

WP_CLI::log( 'An admin who cannot edit PHP: may remove code, not add or run it' );
$admin = get_current_user_id();
$php   = $make_tracked( array( 'title' => 'REST test PHP rights', 'type' => 'php', 'location' => 'php_everywhere', 'code' => "add_filter( 'rest_test_filter', '__return_true' );" ) );
$again = new Snippet();
$again->fill( array( 'code' => "add_filter( 'rest_test_filter', '__return_false' );" ) + get_object_vars( Snippet::get( $php ) ) );
$again->id = $php;
$again->save(); // A second version, so there is a revision to restore.
$revision = current( wp_get_post_revisions( $php ) );
$plain    = $make_tracked( array( 'title' => 'REST test plain markup', 'code' => '<!-- plain -->' ) );
$live     = $make_tracked( array( 'title' => 'REST test PHP live', 'type' => 'php', 'location' => 'php_everywhere', 'code' => "add_filter( 'rest_test_live', '__return_true' );", 'active' => true ) );

add_role( 'rest_test_manager', 'REST test manager', array( 'read' => true, 'manage_options' => true, 'unfiltered_html' => true ) );
$manager = wp_insert_user( array( 'user_login' => 'rest_test_manager', 'user_pass' => wp_generate_password(), 'role' => 'rest_test_manager' ) );
wp_set_current_user( $manager );
$check( 'the test user can manage snippets but not PHP', ScriptDock\Capabilities::can_manage() && ! ScriptDock\Capabilities::can_manage_php() );
list( $status ) = $call( 'POST', '/snippets/' . $php . '/activate' );
$check( 'cannot switch a PHP snippet on', 403 === $status, (string) $status );
list( $status, $data ) = $call( 'POST', '/snippets/' . $live . '/deactivate' );
$check( 'can switch a live PHP snippet off', 200 === $status && ! $data['item']['active'], (string) $status );
$check( 'and the row says it cannot go back on', isset( $data['item'] ) && ! $data['item']['can_activate'] && $data['item']['can_deactivate'] && '' !== $data['item']['locked_reason'] );
list( $status ) = $call( 'POST', '/snippets/' . $live . '/activate' );
$check( 'cannot switch it back on', 403 === $status, (string) $status );
list( $status ) = $call( 'POST', '/snippets/' . $php . '/duplicate' );
$check( 'cannot duplicate a PHP snippet', 403 === $status, (string) $status );
list( $status ) = $call( 'POST', '/snippets', array( 'title' => 'REST test not allowed', 'type' => 'php', 'code' => "echo 'hi';" ) );
$check( 'cannot create a PHP snippet', 403 === $status, (string) $status );
list( $status ) = $call( 'PUT', '/snippets/' . $php, array( 'title' => 'REST test renamed PHP' ) );
$check( 'cannot edit a PHP snippet', 403 === $status, (string) $status );
list( $status ) = $call( 'PUT', '/snippets/' . $plain, array( 'type' => 'php', 'code' => "echo 'hi';" ) );
$check( 'cannot turn a markup snippet into PHP', 403 === $status, (string) $status );
list( $status ) = $call( 'POST', '/snippets/lint', array( 'type' => 'php', 'code' => "echo 'hi';" ) );
$check( 'cannot check PHP syntax', 403 === $status, (string) $status );
list( , $php_log ) = $call( 'GET', '/snippets/' . $php . '/revisions' );
$check( 'the history says restoring is not available', ! $php_log['can_restore'] && '' !== $php_log['reason'], $php_log['reason'] );
if ( $php_log['items'] ) {
	list( $status ) = $call( 'POST', '/snippets/' . $php . '/revisions/' . $php_log['items'][0]['id'] );
	$check( 'and refuses a restore', 403 === $status, (string) $status );
}
list( $status, $data ) = $call( 'PUT', '/snippets/' . $plain, array( 'title' => 'REST test plain renamed' ) );
$check( 'can still edit markup', 200 === $status && 'REST test plain renamed' === $data['item']['title'], (string) $status );
list( $status ) = $call(
	'PUT',
	'/snippets/' . $plain,
	array(
		'conditions' => array(
			'enabled' => true,
			'action'  => 'show',
			'groups'  => array( array( array( 'rule' => 'php_function', 'operator' => 'is', 'value' => array( 'rest_test_callback' ) ) ) ),
		),
	)
);
$check( 'cannot add a PHP function condition', 403 === $status, (string) $status );
list( $status ) = $call( 'POST', '/snippets/' . $demand . '/run' );
$check( 'cannot run a PHP snippet', 403 === $status, (string) $status );
$check( 'cannot restore a PHP revision', is_wp_error( ScriptDock\Snippets::revision_restore_error( Snippet::get( $php ) ) ) );
require_once ABSPATH . 'wp-admin/includes/revision.php';
// Admin hooks load in wp-admin only; attach this one the way it is there.
add_filter( 'wp_prepare_revision_for_js', array( ScriptDock\Admin\Admin::class, 'revision_for_js' ), 10, 3 );
$screen = wp_prepare_revisions_for_js( get_post( $php ), $revision ? $revision->ID : 0 );
$urls   = wp_list_pluck( $screen['revisionData'], 'restoreUrl' );
$check( 'the revisions screen offers no restore', $urls && ! array_filter( $urls ) );
$wpdb->update( $wpdb->posts, array( 'post_content' => "add_filter( 'rest_test_changed', '__return_true' );" ), array( 'ID' => $php ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
clean_post_cache( $php );
list( $status ) = $call( 'POST', '/snippets/' . $php . '/approve' );
$check( 'cannot approve changed PHP', 403 === $status, (string) $status );
list( $status ) = $call( 'DELETE', '/snippets/' . $php );
$check( 'can trash a PHP snippet', 200 === $status && 'trash' === get_post_status( $php ), (string) $status );
list( $status ) = $call( 'POST', '/snippets/' . $php . '/untrash' );
$check( 'can untrash it, switched off', 200 === $status && 'draft' === get_post_status( $php ), (string) $status );
$call( 'DELETE', '/snippets/' . $php );
list( $status ) = $call( 'DELETE', '/snippets/' . $php, array( 'force' => true ) );
$check( 'can delete it permanently', 200 === $status && ! get_post( $php ), (string) $status );
wp_set_current_user( $admin );
$check( 'an admin who can edit PHP may restore revisions', null === ScriptDock\Snippets::revision_restore_error( Snippet::get( $demand ) ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $manager );
remove_role( 'rest_test_manager' );

WP_CLI::log( 'First run' );
$was_onboarded = get_option( ScriptDock\Admin\Onboarding_Page::OPTION );
delete_option( ScriptDock\Admin\Onboarding_Page::OPTION );
$check( 'a fresh install has setup waiting', ! ScriptDock\Admin\Onboarding_Page::done() );

$mail = array();
$spy  = static function ( $null, $atts ) use ( &$mail ) {
	$mail = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $spy, 10, 2 );
$had_link = ScriptDock\Safe_Mode::link_saved();
list( $status, $data ) = $call( 'POST', '/onboarding/email-link' );
remove_filter( 'pre_wp_mail', $spy );
$me = wp_get_current_user();
$check( 'the safe mode link can be emailed', 200 === $status, (string) $status );
// The link is a password in all but name, so it only ever goes to the
// address on the account asking for it.
$check( 'it goes to the asker and nobody else', isset( $mail['to'] ) && $me->user_email === $mail['to'] && $me->user_email === $data['sent'] );
$check( 'the email carries the link', isset( $mail['message'] ) && false !== strpos( $mail['message'], ScriptDock\Safe_Mode::recovery_url() ) );
$check( 'emailing it ticks the checklist', ScriptDock\Safe_Mode::link_saved() );
if ( ! $had_link ) {
	delete_user_option( get_current_user_id(), ScriptDock\Safe_Mode::LINK_SAVED );
}

list( $status ) = $call( 'POST', '/onboarding/done' );
$check( 'setup can be marked done', 200 === $status && ScriptDock\Admin\Onboarding_Page::done(), (string) $status );

if ( $was_onboarded ) {
	update_option( ScriptDock\Admin\Onboarding_Page::OPTION, $was_onboarded, true );
}

WP_CLI::log( 'The email sent when a snippet is switched off' );
$crashed = $make( array( 'title' => 'REST test crashed', 'type' => 'php', 'code' => 'return 1;' ) );
$created[] = $crashed;
$mail = array();
$spy  = static function ( $null, $atts ) use ( &$mail ) {
	$mail = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $spy, 10, 2 );
$sent = ScriptDock\Crash_Email::send(
	get_post( $crashed ),
	array(
		'message' => 'Call to undefined function wc_get_customer_order_count()',
		'line'    => 12,
		'time'    => time(),
		'url'     => '/cart/',
	)
);
remove_filter( 'pre_wp_mail', $spy );
$body = isset( $mail['message'] ) ? $mail['message'] : '';
$check( 'it goes to the site admin', get_option( 'admin_email' ) === $sent, $sent );
$check( 'the subject names the snippet', isset( $mail['subject'] ) && false !== strpos( $mail['subject'], 'REST test crashed' ) );
$check( 'the body is HTML', 0 === strpos( $body, '<!DOCTYPE html>' ) );
$check( 'it shows the error, the line and the page', false !== strpos( $body, 'wc_get_customer_order_count' ) && false !== strpos( $body, 'Line</strong> 12' ) && false !== strpos( $body, '/cart/' ) );
$check( 'it links to the snippet and to Settings', false !== strpos( $body, esc_url( ScriptDock\Snippets::edit_url( $crashed ) ) ) && false !== strpos( $body, esc_url( ScriptDock\Settings::page_url() ) ) );
// Email clients strip <style>, so every rule has to be on the element.
$check( 'it carries no stylesheet', false === stripos( $body, '<style' ) && false === stripos( $body, '<link' ) );
$check( 'wp_mail is left as it was', ! has_filter( 'wp_mail_content_type' ) && ! has_action( 'phpmailer_init' ) );

WP_CLI::log( 'Page code, saved with the post by the block editor' );
$page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_title'   => 'REST test page',
		'post_status'  => 'publish',
		'post_content' => 'Page for the page code checks.',
	)
);
$page_meta = static function ( $value ) use ( $page ) {
	$request = new WP_REST_Request( 'POST', '/wp/v2/pages/' . $page );
	$request->set_body_params( array( 'meta' => array( ScriptDock\Page_Scripts::META => $value ) ) );
	$response = rest_do_request( $request );
	$data     = $response->get_data();
	return array( $response->get_status(), isset( $data['meta'][ ScriptDock\Page_Scripts::META ] ) ? $data['meta'][ ScriptDock\Page_Scripts::META ] : null );
};

list( $status, $saved ) = $page_meta(
	array(
		'css'         => ".rest-test { color: red; }\n.two {}",
		'head'        => '<meta name="rest-test" content="1">',
		'disable'     => array( 11, 11, 0, 12 ),
		'disable_all' => false,
	)
);
$check( 'the block editor can save page code', 200 === $status, (string) $status );
$check( 'the signature comes back with it', $saved && $saved['sig'], 'so the editor is not left dirty' );
$check( 'the switched-off list is tidied', $saved && array( 11, 12 ) === $saved['disable'], wp_json_encode( $saved ? $saved['disable'] : null ) );

$stored = ScriptDock\Page_Scripts::get( $page );
$check( 'saved page code is trusted', ScriptDock\Page_Scripts::is_trusted( $page, $stored ) );
$check( 'code put straight into the database is not', ! ScriptDock\Page_Scripts::is_trusted( $page, array_merge( $stored, array( 'css' => 'body{display:none}' ) ) ) );

list( , $saved ) = $page_meta( array( 'css' => '', 'head' => '', 'disable' => array(), 'disable_all' => false ) );
$check( 'clearing it removes the meta', ! metadata_exists( 'post', $page, ScriptDock\Page_Scripts::META ) );

$page_editor = wp_insert_user( array( 'user_login' => 'rest_test_page_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
wp_set_current_user( $page_editor );
$page_meta( array( 'css' => 'body { display: none; }' ) );
wp_set_current_user( $admin );
$check( 'an editor without ScriptDock rights cannot add page code', '' === trim( ScriptDock\Page_Scripts::get( $page )['css'] ) );

// Older versions stored "all" as a string; pages saved then must still work.
update_post_meta( $page, ScriptDock\Page_Scripts::META, array( 'css' => '.old {}', 'disable' => 'all' ) );
$legacy = ScriptDock\Page_Scripts::get( $page );
$check( 'page code in the older shape still reads', true === $legacy['disable_all'] && array() === $legacy['disable'] );

// The classic editor keeps its meta box, and the panel steps aside for it.
require_once ABSPATH . 'wp-admin/includes/post.php';
$page_post = get_post( $page );
$check( 'the panel handles a block editor page', ScriptDock\Admin\Block_Editor::applies( $page_post ) );
add_filter( 'use_block_editor_for_post', '__return_false' );
$check( 'the panel steps aside for the classic editor', ! ScriptDock\Admin\Block_Editor::applies( $page_post ) );
remove_filter( 'use_block_editor_for_post', '__return_false' );

$_POST['scriptdock_page_nonce'] = wp_create_nonce( ScriptDock\Admin\Page_Meta_Box::NONCE );
$_POST['post_ID']               = $page;
$_POST['scriptdock_page']       = array( 'css' => '.classic {}', 'disable_mode' => 'all' );
ScriptDock\Admin\Page_Meta_Box::save( $page, $page_post );
$classic = ScriptDock\Page_Scripts::get( $page );
$check( 'the classic box still saves, signed', '.classic {}' === $classic['css'] && $classic['disable_all'] && ScriptDock\Page_Scripts::is_trusted( $page, $classic ) );
$_POST['scriptdock_page'] = array( 'css' => '.classic {}', 'disable_mode' => 'some', 'disable' => array( '11', '12', 'x' ) );
ScriptDock\Admin\Page_Meta_Box::save( $page, $page_post );
$check( 'the classic box saves a chosen few', array( 11, 12 ) === ScriptDock\Page_Scripts::get( $page )['disable'] );

// Code changed outside ScriptDock is not approved by an ordinary update of the post.
$stored = get_post_meta( $page, ScriptDock\Page_Scripts::META, true );
$stored['css'] = '.injected {}';
$wpdb->update( $wpdb->postmeta, array( 'meta_value' => maybe_serialize( $stored ) ), array( 'post_id' => $page, 'meta_key' => ScriptDock\Page_Scripts::META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_cache_delete( $page, 'post_meta' );
// A fresh request would read it from the database; this one has it cached.
$page_cache = new ReflectionProperty( ScriptDock\Page_Scripts::class, 'cache' );
$page_cache->setAccessible( true );
$page_cache->setValue( null, array() );
$_POST['scriptdock_page'] = array( 'css' => '.injected {}', 'disable_mode' => 'all' );
ScriptDock\Admin\Page_Meta_Box::save( $page, $page_post );
$check( 'updating the post does not approve changed page code', ! ScriptDock\Page_Scripts::is_trusted( $page, ScriptDock\Page_Scripts::get( $page ) ) );
$_POST['scriptdock_page']['approve'] = '1';
ScriptDock\Admin\Page_Meta_Box::save( $page, $page_post );
$check( 'ticking the box approves it', ScriptDock\Page_Scripts::is_trusted( $page, ScriptDock\Page_Scripts::get( $page ) ) );

// Another post saved in the same request keeps its own page code.
$_POST['post_ID'] = $page + 1000000;
$_POST['scriptdock_page'] = array( 'css' => '.other {}', 'disable_mode' => 'all' );
ScriptDock\Admin\Page_Meta_Box::save( $page, $page_post );
$check( 'the box only saves the post in the form', '.injected {}' === ScriptDock\Page_Scripts::get( $page )['css'] );
unset( $_POST['scriptdock_page'], $_POST['scriptdock_page_nonce'], $_POST['post_ID'] );

require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $page_editor );
wp_delete_post( $page, true );

WP_CLI::log( 'Preferences' );
list( , $before ) = $call( 'GET', '/preferences' );
list( $status, $data ) = $call( 'POST', '/preferences', array( 'per_page' => 50, 'density' => 'compact' ) );
$check( 'preferences save', 200 === $status && 50 === $data['per_page'] && 'compact' === $data['density'] );
list( $status ) = $call( 'POST', '/preferences', array( 'per_page' => 500 ) );
$check( 'per page is capped', 400 === $status, (string) $status );
$call( 'POST', '/preferences', $before );
$had_link = ScriptDock\Safe_Mode::link_saved();
$call( 'POST', '/preferences', array( 'safe_link_saved' => true ) );
$check( 'copying the safe mode link ticks the checklist', ScriptDock\Safe_Mode::link_saved() );
if ( ! $had_link ) {
	delete_user_option( get_current_user_id(), ScriptDock\Safe_Mode::LINK_SAVED );
}

WP_CLI::log( 'WordPress before 6.6' );
$scripts = wp_scripts();
$core    = $scripts->query( 'react-jsx-runtime', 'registered' );
$check( 'the JSX runtime every screen needs is registered', (bool) $core );
$scripts->remove( 'react-jsx-runtime' );
ScriptDock\Plugin::jsx_runtime( $scripts );
$ours = $scripts->query( 'react-jsx-runtime', 'registered' );
$check( 'without WordPress 6.6’s own, ScriptDock stands in for it', $ours && false !== strpos( $ours->src, 'assets/js/react-jsx-runtime.js' ) && array( 'react' ) === $ours->deps, $ours ? $ours->src : 'not registered' );
if ( $core ) {
	$scripts->remove( 'react-jsx-runtime' );
	$scripts->registered['react-jsx-runtime'] = $core;
	ScriptDock\Plugin::jsx_runtime( $scripts );
	$check( 'and leaves WordPress’s own alone when it has one', $core === $scripts->query( 'react-jsx-runtime', 'registered' ) );
}

WP_CLI::log( 'The runtime cache' );
$autoload = static function () {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", ScriptDock\Compiler::OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};
$loads = array( 'yes', 'on', 'auto', 'auto-on' );
$tiny  = static function () {
	return 10;
};
add_filter( 'wp_max_autoloaded_option_size', $tiny );
delete_option( ScriptDock\Compiler::OPTION );
ScriptDock\Compiler::rebuild();
$check( 'a runtime too big to autoload loads on its own', ! in_array( $autoload(), $loads, true ), (string) $autoload() );
remove_filter( 'wp_max_autoloaded_option_size', $tiny );
delete_option( ScriptDock\Compiler::OPTION );
$size = strlen( wp_json_encode( ScriptDock\Compiler::rebuild() ) );
$check( 'one within the limit is autoloaded', ( $size <= 150000 ) === in_array( $autoload(), $loads, true ), $size . ' bytes, ' . $autoload() );

WP_CLI::log( 'Permissions (logged-out requests are checked over HTTP in check-rest.sh)' );
$editor = wp_insert_user( array( 'user_login' => 'rest_test_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
wp_set_current_user( $editor );
list( $status ) = $call( 'GET', '/tags' );
$check( 'an editor gets 403', 403 === $status, (string) $status );
wp_set_current_user( $admin );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $editor );

WP_CLI::log( 'Clean up' );
foreach ( array_unique( $created ) as $id ) {
	if ( get_post( $id ) ) {
		wp_delete_post( $id, true );
	}
}
foreach ( array( 'REST test tag', 'REST test editor tag', 'REST test other', 'REST test renamed' ) as $name ) {
	$term = get_term_by( 'name', $name, Post_Type::TAXONOMY );
	if ( $term ) {
		wp_delete_term( $term->term_id, Post_Type::TAXONOMY );
	}
}
ScriptDock\Compiler::rebuild();

WP_CLI::log( $failures ? "FAILED: $failures" : 'ALL PASSED' );
