<?php
/**
 * DEV ONLY - never deploy. Renders WordPress right-to-left without a
 * translation, to test RTL layouts: ?dev-rtl=1 turns it on for this browser,
 * ?dev-rtl=0 turns it off. Only active when WP_ENVIRONMENT_TYPE is "local".
 */

add_action(
	'after_setup_theme',
	static function () {
		if ( 'local' !== wp_get_environment_type() ) {
			return;
		}
		if ( isset( $_GET['dev-rtl'] ) ) {
			$on = '1' === $_GET['dev-rtl'];
			setcookie( 'dev_rtl', $on ? '1' : '', $on ? time() + DAY_IN_SECONDS : time() - HOUR_IN_SECONDS, '/' );
			$_COOKIE['dev_rtl'] = $on ? '1' : '';
		}
		if ( ! empty( $_COOKIE['dev_rtl'] ) && isset( $GLOBALS['wp_locale'] ) ) {
			$GLOBALS['wp_locale']->text_direction = 'rtl';
		}
	},
	0
);
