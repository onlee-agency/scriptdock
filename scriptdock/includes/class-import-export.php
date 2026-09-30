<?php
/**
 * JSON import and export.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Exports snippets to JSON and imports ScriptDock or Code Snippets JSON files.
 */
final class Import_Export {

	/**
	 * Export format identifier.
	 */
	const FORMAT = 'scriptdock-export';

	/**
	 * Builds an export payload.
	 *
	 * @param int[] $ids Snippet IDs; empty for all.
	 * @return array
	 */
	public static function export( array $ids = array() ) {
		if ( $ids ) {
			$snippets = array_filter( array_map( array( Snippet::class, 'get' ), array_map( 'absint', $ids ) ) );
		} else {
			$snippets = Snippets::query();
		}
		return array(
			'format'    => self::FORMAT,
			'version'   => 1,
			'generator' => 'ScriptDock ' . SCRIPTDOCK_VERSION,
			'site'      => home_url( '/' ),
			'exported'  => gmdate( 'c' ),
			'snippets'  => array_values(
				array_map(
					static function ( Snippet $snippet ) {
						return $snippet->to_export();
					},
					$snippets
				)
			),
		);
	}

	/**
	 * JSON for a payload.
	 *
	 * @param array $payload Export payload.
	 * @return string
	 */
	public static function to_json( array $payload ) {
		return (string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * File name for an export: the snippet's title for one snippet, else
	 * scriptdock-snippets, plus the date.
	 *
	 * @param array $payload Export payload.
	 * @return string
	 */
	public static function filename( array $payload ) {
		$count = count( $payload['snippets'] );
		$name  = 1 === $count ? sanitize_file_name( $payload['snippets'][0]['title'] ) : 'scriptdock-snippets';
		return ( '' === $name ? 'scriptdock-snippet' : $name ) . '-' . gmdate( 'Y-m-d' ) . '.json';
	}

	/**
	 * Sends an export as a file download and exits.
	 *
	 * @param array $payload Export payload.
	 */
	public static function download( array $payload ) {
		$name = self::filename( $payload );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		echo self::to_json( $payload ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file download.
		exit;
	}

	/**
	 * Parses an import file into snippet data arrays.
	 *
	 * @param string $json File contents.
	 * @return array|\WP_Error List of snippet data arrays.
	 */
	public static function parse( $json ) {
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'scriptdock_invalid_json', __( 'The file is not valid JSON.', 'scriptdock' ) );
		}

		// A single exported snippet object.
		if ( isset( $data['code'] ) && ( isset( $data['title'] ) || isset( $data['name'] ) ) ) {
			$data = array( 'snippets' => array( $data ) );
		}

		if ( empty( $data['snippets'] ) || ! is_array( $data['snippets'] ) ) {
			return new \WP_Error( 'scriptdock_no_snippets', __( 'No snippets were found in the file.', 'scriptdock' ) );
		}

		// Code Snippets leaves out every field at its default, the default
		// scope ("run everywhere") included, so its items are recognised by
		// the file, or by a name where ScriptDock would have a title.
		$code_snippets_file = isset( $data['generator'] ) && 0 === stripos( (string) $data['generator'], 'Code Snippets' );

		$items = array();
		foreach ( $data['snippets'] as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['code'] ) ) {
				continue;
			}
			$from_code_snippets = ! isset( $item['type'] ) && ( $code_snippets_file || isset( $item['scope'] ) || ( isset( $item['name'] ) && ! isset( $item['title'] ) ) );
			$items[]            = $from_code_snippets ? self::from_code_snippets( $item ) : $item;
		}

		if ( ! $items ) {
			return new \WP_Error( 'scriptdock_no_snippets', __( 'No snippets were found in the file.', 'scriptdock' ) );
		}
		return $items;
	}

	/**
	 * Maps a Code Snippets export item to ScriptDock fields.
	 *
	 * @param array $item Code Snippets item.
	 * @return array
	 */
	public static function from_code_snippets( array $item ) {
		$scopes = array(
			'global'         => array( 'php', 'php_everywhere' ),
			'admin'          => array( 'php', 'php_admin' ),
			'front-end'      => array( 'php', 'php_frontend' ),
			'single-use'     => array( 'php', 'on_demand' ),
			'content'        => array( 'html', 'shortcode' ),
			'head-content'   => array( 'html', 'site_header' ),
			'body-content'   => array( 'html', 'site_body_open' ),
			'footer-content' => array( 'html', 'site_footer' ),
			'admin-css'      => array( 'css', 'admin_header' ),
			'site-css'       => array( 'css', 'site_header' ),
			'site-head-js'   => array( 'js', 'site_header' ),
			'site-footer-js' => array( 'js', 'site_footer' ),
		);
		$scope  = isset( $item['scope'], $scopes[ $item['scope'] ] ) ? $scopes[ $item['scope'] ] : array( 'php', 'php_everywhere' );

		return array(
			'title'       => isset( $item['name'] ) ? $item['name'] : '',
			'description' => isset( $item['desc'] ) ? wp_strip_all_tags( (string) $item['desc'] ) : '',
			'code'        => (string) $item['code'],
			'type'        => $scope[0],
			'location'    => $scope[1],
			'priority'    => isset( $item['priority'] ) ? (int) $item['priority'] : 10,
			'tags'        => isset( $item['tags'] ) ? (array) $item['tags'] : array(),
			'active'      => ! empty( $item['active'] ),
		);
	}

	/**
	 * Imports snippet data arrays.
	 *
	 * Snippets are imported inactive unless $args['activate'] is true and the
	 * file marks them active. PHP code is syntax-checked before activation.
	 *
	 * @param array $items Snippet data arrays.
	 * @param array $args  Options: activate (bool), source (string).
	 * @return array Results: imported, activated, skipped (list of messages).
	 */
	public static function import( array $items, array $args = array() ) {
		$args    = wp_parse_args(
			$args,
			array(
				'activate' => false,
				'source'   => 'import',
			)
		);
		$results = array(
			'imported'  => 0,
			'activated' => 0,
			'skipped'   => array(),
		);

		foreach ( $items as $item ) {
			$snippet = new Snippet();
			$snippet->fill( array_merge( $item, array( 'active' => false ) ) );
			$snippet->source = sanitize_text_field( (string) $args['source'] );
			$label           = '' !== $snippet->title ? $snippet->title : __( 'Untitled snippet', 'scriptdock' );

			if ( ( $snippet->is_php() || Conditions::uses_php( $snippet->conditions ) ) && ! Capabilities::can_manage_php() ) {
				/* translators: %s: snippet title */
				$results['skipped'][] = sprintf( __( '“%s” skipped: you are not allowed to add PHP code.', 'scriptdock' ), $label );
				continue;
			}

			$saved = $snippet->save();
			if ( is_wp_error( $saved ) ) {
				$results['skipped'][] = $label . ': ' . $saved->get_error_message();
				continue;
			}
			++$results['imported'];

			if ( $args['activate'] && ! empty( $item['active'] ) ) {
				if ( $snippet->is_php() && $snippet->lint() ) {
					/* translators: %s: snippet title */
					$results['skipped'][] = sprintf( __( '“%s” was imported but left inactive because it has a syntax error.', 'scriptdock' ), $label );
					continue;
				}
				$snippet->active = true;
				$snippet->save();
				++$results['activated'];
			}
		}

		Compiler::mark_dirty();
		return $results;
	}
}
