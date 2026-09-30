<?php
/**
 * DEV ONLY - never deploy. The ScriptDock component gallery: every shared
 * component in every state, inside the real app shell, for checking against
 * design_handoff_scriptdock/ScriptDock Components.dc.html.
 *
 * Adds "Components (dev)" under the ScriptDock menu on the local Docker site
 * only. Build it with `npm run build` from the repository root.
 */

add_action(
	'admin_menu',
	static function () {
		if ( 'local' !== wp_get_environment_type() || ! class_exists( 'ScriptDock\\Plugin' ) ) {
			return;
		}
		add_submenu_page(
			'scriptdock',
			'Components',
			'Components (dev)',
			'manage_options',
			'scriptdock-gallery',
			static function () {
				echo '<div class="wrap sd-wrap"><hr class="wp-header-end"><div id="sd-gallery" class="sd-app"></div></div>';
			},
			99
		);
	},
	20
);

add_filter(
	'scriptdock_is_admin_screen',
	static function ( $is ) {
		return $is || ( isset( $_GET['page'] ) && 'scriptdock-gallery' === $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
);

add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( ! isset( $_GET['page'] ) || 'scriptdock-gallery' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$asset_file = WPMU_PLUGIN_DIR . '/sd-gallery/gallery.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset    = include $asset_file;
		$settings = \ScriptDock\Admin\Admin::code_editor_settings( array( 'html', 'php', 'css', 'js' ) );

		wp_enqueue_style( 'scriptdock-ui' );
		if ( file_exists( WPMU_PLUGIN_DIR . '/sd-gallery/gallery.css' ) ) {
			wp_enqueue_style( 'sd-gallery', WPMU_PLUGIN_URL . '/sd-gallery/gallery.css', array( 'scriptdock-ui' ), $asset['version'] );
		}
		wp_enqueue_script( 'sd-gallery', WPMU_PLUGIN_URL . '/sd-gallery/gallery.js', array_merge( $asset['dependencies'], array( 'code-editor' ) ), $asset['version'], true );
		wp_add_inline_script( 'sd-gallery', 'window.sdGallery = ' . wp_json_encode( array( 'editorSettings' => $settings ) ) . ';', 'before' );
	}
);
