<?php
/**
 * Snippet collection helpers.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Operations on several snippets and small shared helpers.
 */
final class Snippets {

	/**
	 * True while ScriptDock itself saves a snippet, after its own checks.
	 *
	 * @var bool
	 */
	private static $writing = false;

	/**
	 * Hooks cache invalidation.
	 */
	public static function init() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard_status' ), 99, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status_change' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'on_post_change' ), 10, 2 );
		add_action( 'wp_restore_post_revision', array( __CLASS__, 'on_revision_restored' ), 10, 2 );
		// Schedules are stored as timestamps; recompute them when the site timezone changes.
		add_action( 'update_option_timezone_string', array( Compiler::class, 'mark_dirty' ) );
		add_action( 'update_option_gmt_offset', array( Compiler::class, 'mark_dirty' ) );
		add_action( 'update_option_' . Settings::OPTION, array( Compiler::class, 'mark_dirty' ) );
	}

	/**
	 * Runs a save that has passed ScriptDock's own checks.
	 *
	 * @param callable $write Does the saving.
	 * @return mixed Whatever $write returns.
	 */
	public static function writing( callable $write ) {
		self::$writing = true;
		try {
			return $write();
		} finally {
			self::$writing = false;
		}
	}

	/**
	 * Keeps WordPress's own routes from switching snippets on.
	 *
	 * Quick Edit, XML-RPC and any other plugin that saves the post directly
	 * would skip what switching on checks here: the rights for PHP, the review
	 * of code changed outside ScriptDock, the syntax check and the trial run.
	 * They may still switch a snippet off or move it to the trash, which only
	 * stops code.
	 *
	 * @param array $data    Post data about to be saved.
	 * @param array $postarr Post data as passed in.
	 * @return array
	 */
	public static function guard_status( $data, $postarr ) {
		if ( self::$writing || ! isset( $data['post_type'], $data['post_status'] ) || Post_Type::NAME !== $data['post_type'] ) {
			return $data;
		}
		$old = empty( $postarr['ID'] ) ? '' : get_post_status( (int) $postarr['ID'] );
		if ( 'publish' === $data['post_status'] && 'publish' === $old ) {
			return $data;
		}
		if ( ! in_array( $data['post_status'], array( 'draft', 'trash', 'auto-draft' ), true ) ) {
			$data['post_status'] = 'draft';
		}
		return $data;
	}

	/**
	 * Marks the runtime cache dirty when a snippet changes status.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public static function on_status_change( $new_status, $old_status, $post ) {
		if ( $post instanceof \WP_Post && Post_Type::NAME === $post->post_type && $new_status !== $old_status ) {
			Compiler::mark_dirty();
		}
	}

	/**
	 * Marks the runtime cache dirty when a snippet is deleted.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post.
	 */
	public static function on_post_change( $post_id, $post = null ) {
		if ( $post instanceof \WP_Post && Post_Type::NAME === $post->post_type ) {
			// A snippet that no longer exists cannot be fixed, so its error
			// notice goes with it.
			Notices::remove( (int) $post_id );
			Compiler::mark_dirty();
		}
	}

	/**
	 * Re-signs a snippet after an admin restores a revision.
	 *
	 * @param int $post_id     Post ID.
	 * @param int $revision_id Revision ID.
	 */
	public static function on_revision_restored( $post_id, $revision_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$snippet = Snippet::get( $post_id );
		if ( ! $snippet || ! Capabilities::can_edit_type( $snippet->type ) ) {
			return;
		}
		$snippet->signature = Signer::sign_snippet( $snippet );
		update_post_meta( $snippet->id, Snippet::META_SIGNATURE, $snippet->signature );
		Compiler::mark_dirty();
	}

	/**
	 * Editor URL for a snippet.
	 *
	 * @param int $id Snippet ID, 0 for a new snippet.
	 * @return string
	 */
	public static function edit_url( $id = 0 ) {
		$args = array( 'page' => 'scriptdock-edit' );
		if ( $id ) {
			$args['snippet'] = (int) $id;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Snippets screen URL.
	 *
	 * @param array $args Query args the screen understands: view, s, type,
	 *                    location, tag, targeting, month, orderby, order,
	 *                    paged, tags.
	 * @return string
	 */
	public static function list_url( array $args = array() ) {
		// Older links used the core list's name for the view.
		if ( isset( $args['scriptdock_view'] ) ) {
			$args['view'] = $args['scriptdock_view'];
			unset( $args['scriptdock_view'] );
		}
		return add_query_arg( array_merge( array( 'page' => 'scriptdock-snippets' ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Activates or deactivates a snippet.
	 *
	 * Switching on needs the rights to edit the snippet's type, because it
	 * starts code running. Switching off does not: it only stops code, the way
	 * WordPress lets an admin deactivate a plugin they cannot edit.
	 *
	 * @param int  $id     Snippet ID.
	 * @param bool $active New state.
	 * @return true|\WP_Error
	 */
	public static function set_active( $id, $active ) {
		$snippet = Snippet::get( $id );
		if ( ! $snippet ) {
			return new \WP_Error( 'scriptdock_not_found', __( 'Snippet not found.', 'scriptdock' ) );
		}
		$error = $active ? self::activation_error( $snippet ) : null;
		if ( $error ) {
			return $error;
		}

		$restore = Snippet::suspend_content_filters();
		$result  = self::writing(
			static function () use ( $snippet, $active ) {
				return wp_update_post(
					array(
						'ID'          => $snippet->id,
						'post_status' => $active ? 'publish' : 'draft',
					),
					true
				);
			}
		);
		$restore();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $active ) {
			$snippet->clear_error();
		}
		Snippet::touch_editor( $snippet->id );

		Compiler::mark_dirty();
		return true;
	}

	/**
	 * Why the current user may not change a snippet, if they may not.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return \WP_Error|null
	 */
	public static function edit_error( Snippet $snippet ) {
		if ( ! Capabilities::can_edit_type( $snippet->type ) ) {
			return new \WP_Error( 'scriptdock_forbidden', Capabilities::php_unavailable_reason(), array( 'status' => 403 ) );
		}
		return null;
	}

	/**
	 * Why the current user may not restore one of a snippet's revisions, if
	 * they may not. Restoring reads like an undo but replaces the code, so it
	 * needs the same rights as editing it.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return \WP_Error|null
	 */
	public static function revision_restore_error( Snippet $snippet ) {
		if ( Capabilities::can_edit_type( $snippet->type ) ) {
			return null;
		}
		return new \WP_Error(
			'scriptdock_forbidden',
			/* translators: %s: why this user cannot edit PHP, for example "Your account is not allowed to edit PHP code on this site." */
			sprintf( __( 'Restoring a revision replaces the snippet’s code, so it needs permission to edit PHP. %s', 'scriptdock' ), Capabilities::php_unavailable_reason() ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Why a snippet cannot be switched on, if it cannot: the user may not
	 * edit its type, it changed outside ScriptDock, or its PHP does not parse.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return \WP_Error|null
	 */
	public static function activation_error( Snippet $snippet ) {
		$error = self::edit_error( $snippet );
		if ( $error ) {
			return $error;
		}
		if ( ! $snippet->is_trusted() ) {
			return new \WP_Error( 'scriptdock_untrusted', __( 'This snippet was changed outside ScriptDock. Open it, review the code and save it to approve it.', 'scriptdock' ), array( 'status' => 409 ) );
		}
		if ( $snippet->is_php() ) {
			$lint = $snippet->lint();
			if ( $lint ) {
				/* translators: 1: error message, 2: line number */
				return new \WP_Error( 'scriptdock_syntax', sprintf( __( 'This snippet has a syntax error and cannot be activated: %1$s on line %2$d.', 'scriptdock' ), $lint['message'], $lint['line'] ), array( 'status' => 422 ) );
			}
		}
		return null;
	}

	/**
	 * Why a changed snippet cannot be approved, if it cannot. Approving lets
	 * its current code run, so it needs the rights for its type, and PHP
	 * that would start running again must parse.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return \WP_Error|null
	 */
	public static function approval_error( Snippet $snippet ) {
		$error = self::edit_error( $snippet );
		if ( $error ) {
			return $error;
		}
		if ( $snippet->active && $snippet->is_php() ) {
			$lint = $snippet->lint();
			if ( $lint ) {
				/* translators: 1: error message, 2: line number */
				return new \WP_Error( 'scriptdock_syntax', sprintf( __( 'This snippet has a syntax error, so approving it would break your site: %1$s on line %2$d. Fix it in the editor.', 'scriptdock' ), $lint['message'], $lint['line'] ), array( 'status' => 422 ) );
			}
		}
		return null;
	}

	/**
	 * Approves a snippet that changed outside ScriptDock: its current code is
	 * signed as trusted, and a paused snippet starts running again.
	 *
	 * @param Snippet $snippet Snippet.
	 */
	public static function approve( Snippet $snippet ) {
		$snippet->signature = Signer::sign_snippet( $snippet );
		update_post_meta( $snippet->id, Snippet::META_SIGNATURE, $snippet->signature );
		Snippet::touch_editor( $snippet->id );
		Compiler::mark_dirty();
		self::keep_approved_version( $snippet );
	}

	/**
	 * Puts the approved code in the history, marked as approved. Code changed
	 * straight in the database never went through a save, so it has no saved
	 * version until now. When it did (another plugin saving the post), or when
	 * only the placement changed, the newest version already holds it.
	 *
	 * @param Snippet $snippet Snippet.
	 */
	private static function keep_approved_version( Snippet $snippet ) {
		$revision = wp_save_post_revision( $snippet->id );
		if ( ! $revision || is_wp_error( $revision ) ) {
			$newest   = wp_get_post_revisions( $snippet->id, array( 'posts_per_page' => 1 ) );
			$newest   = reset( $newest );
			$revision = $newest && $newest->post_content === $snippet->code ? $newest->ID : 0;
		}
		if ( $revision ) {
			update_metadata( 'post', (int) $revision, Snippet::META_APPROVED, time() );
		}
	}

	/**
	 * Whether a snippet is tried once before it is switched on.
	 *
	 * Only PHP that runs on every request (everywhere, or everywhere in the
	 * admin) is tried: a fatal error there would take the admin down, and it
	 * does not depend on which page is being viewed.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return bool
	 */
	public static function needs_test_run( Snippet $snippet ) {
		return 'php' === $snippet->type && in_array( $snippet->location, array( 'php_everywhere', 'php_admin' ), true );
	}

	/**
	 * Switches a snippet off after it caused an error.
	 *
	 * Writes the status directly instead of calling wp_update_post(): this can
	 * run while PHP is shutting down after a fatal error, and firing every
	 * save_post callback on the site at that point is asking for trouble.
	 *
	 * @param int $id Snippet ID.
	 */
	public static function deactivate_after_error( $id ) {
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		clean_post_cache( (int) $id );
		Compiler::rebuild();
	}

	/**
	 * Duplicates a snippet as an inactive copy.
	 *
	 * @param int $id Snippet ID.
	 * @return int|\WP_Error New ID.
	 */
	public static function duplicate( $id ) {
		$snippet = Snippet::get( $id );
		if ( ! $snippet ) {
			return new \WP_Error( 'scriptdock_not_found', __( 'Snippet not found.', 'scriptdock' ) );
		}
		if ( ! Capabilities::can_edit_type( $snippet->type ) ) {
			return new \WP_Error( 'scriptdock_forbidden', Capabilities::php_unavailable_reason() );
		}
		// The copy is signed when it is saved, which would approve code
		// nobody has looked at.
		if ( ! $snippet->is_trusted() ) {
			return new \WP_Error( 'scriptdock_untrusted', __( 'This snippet was changed outside ScriptDock. Review and approve the change before you duplicate it.', 'scriptdock' ), array( 'status' => 409 ) );
		}
		$copy             = clone $snippet;
		$copy->id         = 0;
		$copy->active     = false;
		$copy->signature  = '';
		$copy->last_error = array();
		/* translators: %s: snippet title */
		$copy->title = sprintf( __( '%s (copy)', 'scriptdock' ), $snippet->title );
		return $copy->save();
	}

	/**
	 * Loads snippets.
	 *
	 * @param array $args get_posts() arguments.
	 * @return Snippet[]
	 */
	public static function query( array $args = array() ) {
		$posts = get_posts(
			array_merge(
				array(
					'post_type'   => Post_Type::NAME,
					'post_status' => array( 'publish', 'draft' ),
					'numberposts' => -1,
					'orderby'     => 'ID',
					'order'       => 'ASC',
				),
				$args
			)
		);
		return array_map( array( Snippet::class, 'from_post' ), $posts );
	}

	/**
	 * Snippets whose code no longer matches their signature.
	 *
	 * @return Snippet[]
	 */
	public static function untrusted() {
		if ( ! Signer::enabled() ) {
			return array();
		}
		return array_values(
			array_filter(
				self::query(),
				static function ( Snippet $snippet ) {
					return ! $snippet->is_trusted();
				}
			)
		);
	}
}
