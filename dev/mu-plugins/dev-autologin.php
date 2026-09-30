<?php
/**
 * DEV ONLY - never deploy. Logs in when a request to the local Docker site
 * carries ?dev-login, so browser tests do not need a password.
 *
 *   ?dev-login=1              the first administrator
 *   ?dev-login=<user_login>   that user, for checking what people without
 *                             ScriptDock rights see
 *
 * Only active when WP_ENVIRONMENT_TYPE is "local".
 */

add_action(
	'init',
	static function () {
		if ( 'local' !== wp_get_environment_type() || empty( $_GET['dev-login'] ) ) {
			return;
		}
		$want = sanitize_user( wp_unslash( $_GET['dev-login'] ) );

		if ( '1' === $want ) {
			if ( is_user_logged_in() ) {
				return;
			}
			$found = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
			$id    = $found ? (int) $found[0] : 0;
		} else {
			$user = get_user_by( 'login', $want );
			$id   = $user ? (int) $user->ID : 0;
			if ( $id && get_current_user_id() === $id ) {
				return;
			}
		}

		if ( ! $id ) {
			return;
		}
		wp_set_current_user( $id );
		wp_set_auth_cookie( $id, true );
		wp_safe_redirect( remove_query_arg( 'dev-login' ) );
		exit;
	}
);
