<?php
/**
 * Site Health integration.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a status test and a debug information section to Tools > Site Health.
 */
final class Site_Health {

	/**
	 * Hooks Site Health.
	 */
	public static function init() {
		add_filter( 'debug_information', array( __CLASS__, 'debug' ) );
		add_filter( 'site_status_tests', array( __CLASS__, 'tests' ) );
	}

	/**
	 * Debug information section.
	 *
	 * @param array $info Sections.
	 * @return array
	 */
	public static function debug( $info ) {
		$data = Compiler::get();
		$yes  = __( 'Yes', 'scriptdock' );
		$no   = __( 'No', 'scriptdock' );

		$info['scriptdock'] = array(
			'label'  => 'ScriptDock',
			'fields' => array(
				'version'   => array(
					'label' => __( 'Version', 'scriptdock' ),
					'value' => SCRIPTDOCK_VERSION,
				),
				'active'    => array(
					'label' => __( 'Snippets running', 'scriptdock' ),
					'value' => count( $data['snippets'] ),
				),
				'untrusted' => array(
					'label' => __( 'Snippets waiting for review', 'scriptdock' ),
					'value' => count( $data['untrusted'] ),
				),
				'php'       => array(
					'label' => __( 'PHP snippets enabled', 'scriptdock' ),
					'value' => Capabilities::php_enabled() ? $yes : $no,
				),
				'tamper'    => array(
					'label' => __( 'Tamper protection', 'scriptdock' ),
					'value' => Signer::enabled() ? $yes : $no,
				),
				'safe_mode' => array(
					'label' => __( 'Safe mode forced in wp-config.php', 'scriptdock' ),
					'value' => Safe_Mode::is_forced() ? $yes : $no,
				),
				'updates'   => array(
					'label' => __( 'Update source', 'scriptdock' ),
					'value' => Updater::repo() ? 'github.com/' . Updater::repo() : __( 'Not configured', 'scriptdock' ),
				),
			),
		);
		return $info;
	}

	/**
	 * Registers the status test.
	 *
	 * @param array $tests Tests.
	 * @return array
	 */
	public static function tests( $tests ) {
		$tests['direct']['scriptdock_snippets'] = array(
			'label' => __( 'ScriptDock snippets', 'scriptdock' ),
			'test'  => array( __CLASS__, 'test' ),
		);
		return $tests;
	}

	/**
	 * Checks for tampered or crashed snippets.
	 *
	 * @return array
	 */
	public static function test() {
		$data    = Compiler::get();
		$notices = Notices::all();
		$result  = array(
			'label'       => __( 'ScriptDock snippets are healthy', 'scriptdock' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'scriptdock' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'All active snippets match their signatures and none were switched off after an error.', 'scriptdock' ) . '</p>',
			'actions'     => '',
			'test'        => 'scriptdock_snippets',
		);

		if ( $data['untrusted'] ) {
			$result['label']       = __( 'Some snippets were changed outside ScriptDock', 'scriptdock' );
			$result['status']      = 'critical';
			$result['badge']['color'] = 'red';
			$result['description'] = '<p>' . esc_html__( 'Their code no longer matches the signature ScriptDock stored, so they are paused. This can mean someone changed your database directly. Review them before approving.', 'scriptdock' ) . '</p>';
			$result['actions']     = '<a href="' . esc_url( Snippets::list_url( array( 'view' => 'review' ) ) ) . '">' . esc_html__( 'Review snippets', 'scriptdock' ) . '</a>';
		} elseif ( $notices ) {
			$result['label']       = __( 'A snippet was switched off after an error', 'scriptdock' );
			$result['status']      = 'recommended';
			$result['description'] = '<p>' . esc_html__( 'ScriptDock deactivated a snippet that caused a fatal error. Fix it or delete it.', 'scriptdock' ) . '</p>';
			$result['actions']     = '<a href="' . esc_url( Snippets::list_url() ) . '">' . esc_html__( 'View snippets', 'scriptdock' ) . '</a>';
		}
		return $result;
	}
}
