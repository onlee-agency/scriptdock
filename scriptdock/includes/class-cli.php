<?php
/**
 * WP-CLI commands.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Manage ScriptDock snippets from the command line.
 */
final class CLI {

	/**
	 * Lists snippets.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by status.
	 * ---
	 * options:
	 *   - active
	 *   - inactive
	 * ---
	 *
	 * [--type=<type>]
	 * : Filter by type: php, html, css, js or universal.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp scriptdock list --status=active
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function list_( $args, $assoc_args ) {
		$query = array();
		if ( isset( $assoc_args['status'] ) ) {
			$query['post_status'] = 'active' === $assoc_args['status'] ? 'publish' : 'draft';
		}
		$rows = array();
		foreach ( Snippets::query( $query ) as $snippet ) {
			if ( isset( $assoc_args['type'] ) && $snippet->type !== $assoc_args['type'] ) {
				continue;
			}
			$rows[] = array(
				'id'       => $snippet->id,
				'title'    => $snippet->title,
				'type'     => $snippet->type,
				'location' => $snippet->location,
				'priority' => $snippet->priority,
				'status'   => $snippet->active ? 'active' : 'inactive',
				'trusted'  => $snippet->is_trusted() ? 'yes' : 'NO',
				'error'    => isset( $snippet->last_error['message'] ) ? $snippet->last_error['message'] : '',
			);
		}
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		if ( 'ids' === $format ) {
			\WP_CLI::log( implode( ' ', wp_list_pluck( $rows, 'id' ) ) );
			return;
		}
		\WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'title', 'type', 'location', 'priority', 'status', 'trusted', 'error' ) );
	}

	/**
	 * Activates snippets.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Snippet IDs.
	 *
	 * @param array $args Positional arguments.
	 */
	public function activate( $args ) {
		$this->set_status( $args, true );
	}

	/**
	 * Deactivates snippets.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Snippet IDs.
	 *
	 * @param array $args Positional arguments.
	 */
	public function deactivate( $args ) {
		$this->set_status( $args, false );
	}

	/**
	 * Approves snippets that were changed outside ScriptDock (re-signs them).
	 *
	 * Review the code first: approving means you trust it to run.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Snippet IDs.
	 *
	 * [--all]
	 * : Approve every snippet that failed verification.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function approve( $args, $assoc_args ) {
		$snippets = ! empty( $assoc_args['all'] ) ? Snippets::untrusted() : array_filter( array_map( array( Snippet::class, 'get' ), $args ) );
		if ( ! $snippets ) {
			\WP_CLI::success( 'Nothing to approve.' );
			return;
		}
		foreach ( $snippets as $snippet ) {
			$snippet->save();
			\WP_CLI::log( sprintf( 'Approved #%d %s', $snippet->id, $snippet->title ) );
		}
		Compiler::rebuild();
		\WP_CLI::success( sprintf( 'Approved %d snippet(s).', count( $snippets ) ) );
	}

	/**
	 * Exports snippets as JSON.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : Snippet IDs. Defaults to all snippets.
	 *
	 * [--file=<file>]
	 * : Write to a file instead of standard output.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function export( $args, $assoc_args ) {
		$json = Import_Export::to_json( Import_Export::export( array_map( 'absint', $args ) ) );
		if ( empty( $assoc_args['file'] ) ) {
			\WP_CLI::line( $json );
			return;
		}
		if ( false === file_put_contents( $assoc_args['file'], $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI output file chosen by the operator.
			\WP_CLI::error( 'Could not write the file.' );
		}
		\WP_CLI::success( 'Exported to ' . $assoc_args['file'] );
	}

	/**
	 * Imports snippets from a ScriptDock or Code Snippets JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : JSON file.
	 *
	 * [--activate]
	 * : Keep snippets active when the file marks them active.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function import( $args, $assoc_args ) {
		if ( ! is_readable( $args[0] ) ) {
			\WP_CLI::error( 'File not found.' );
		}
		$items = Import_Export::parse( (string) file_get_contents( $args[0] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file chosen by the operator.
		if ( is_wp_error( $items ) ) {
			\WP_CLI::error( $items->get_error_message() );
		}
		$results = Import_Export::import( $items, array( 'activate' => ! empty( $assoc_args['activate'] ) ) );
		foreach ( $results['skipped'] as $message ) {
			\WP_CLI::warning( $message );
		}
		Compiler::rebuild();
		\WP_CLI::success( sprintf( 'Imported %d snippet(s), %d active.', $results['imported'], $results['activated'] ) );
	}

	/**
	 * Runs a PHP snippet once and prints its output.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Snippet ID.
	 *
	 * @param array $args Positional arguments.
	 */
	public function run( $args ) {
		$snippet = Snippet::get( absint( $args[0] ) );
		if ( ! $snippet || ! $snippet->is_php() ) {
			\WP_CLI::error( 'PHP snippet not found.' );
		}
		if ( ! Capabilities::php_enabled() ) {
			\WP_CLI::error( 'PHP snippets are disabled on this site.' );
		}
		$lint = $snippet->lint();
		if ( $lint ) {
			\WP_CLI::error( sprintf( 'Syntax error: %s on line %d', $lint['message'], $lint['line'] ) );
		}
		$source = 'php' === $snippet->type ? '<?php ' . $snippet->code : $snippet->code;
		$output = Runtime::run_source( $snippet->id, $source, array(), true );
		if ( Runtime::$last_error ) {
			\WP_CLI::error( Runtime::$last_error['message'] );
		}
		\WP_CLI::log( $output );
		\WP_CLI::success( 'Snippet ran.' );
	}

	/**
	 * Rebuilds the runtime cache and asset files.
	 */
	public function rebuild() {
		$data = Compiler::rebuild();
		\WP_CLI::success( sprintf( 'Rebuilt: %d active snippet(s), %d waiting for review.', count( $data['snippets'] ), count( $data['untrusted'] ) ) );
	}

	/**
	 * Prints the secret safe mode URL.
	 *
	 * @subcommand safe-mode-url
	 */
	public function safe_mode_url() {
		Settings::ensure_defaults();
		\WP_CLI::log( Safe_Mode::recovery_url() );
	}

	/**
	 * Changes snippet status.
	 *
	 * @param array $ids    IDs.
	 * @param bool  $active New state.
	 */
	private function set_status( array $ids, $active ) {
		foreach ( $ids as $id ) {
			$result = Snippets::set_active( absint( $id ), $active );
			if ( is_wp_error( $result ) ) {
				\WP_CLI::warning( sprintf( '#%d: %s', $id, $result->get_error_message() ) );
				continue;
			}
			\WP_CLI::log( sprintf( '#%d %s', $id, $active ? 'activated' : 'deactivated' ) );
		}
		Compiler::rebuild();
		\WP_CLI::success( 'Done.' );
	}
}
