<?php
/**
 * What changed in a snippet since ScriptDock last trusted it.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * Compares a snippet that changed outside ScriptDock with the last version
 * ScriptDock saved and signed, so an admin can see what to approve.
 *
 * The last trusted version is the newest revision whose code still matches
 * the stored signature. That holds even when the change itself created a
 * revision (for example through another plugin's save), which "the newest
 * revision" alone would get wrong.
 */
final class Changes {

	/**
	 * Most revisions searched for the trusted version.
	 */
	const MAX_REVISIONS = 50;

	/**
	 * Lines of unchanged code kept around each change.
	 */
	const CONTEXT = 3;

	/**
	 * The last trusted version of a snippet's code.
	 *
	 * @param Snippet $snippet Snippet.
	 * @return array {
	 *     @type \WP_Post|null $revision The revision, or null when there are none.
	 *     @type bool          $trusted  Whether it matches the stored signature.
	 * }
	 */
	public static function trusted_base( Snippet $snippet ) {
		$revisions = wp_get_post_revisions(
			$snippet->id,
			array(
				'posts_per_page' => self::MAX_REVISIONS,
				'check_enabled'  => false,
			)
		);
		foreach ( $revisions as $revision ) {
			$probe       = clone $snippet;
			$probe->code = $revision->post_content;
			if ( Signer::snippet_is_valid( $probe ) ) {
				return array(
					'revision' => $revision,
					'trusted'  => true,
				);
			}
		}
		$newest = reset( $revisions );
		return array(
			'revision' => $newest ? $newest : null,
			'trusted'  => false,
		);
	}

	/**
	 * A line diff with a few lines of context around each change.
	 *
	 * @param string $old Old code.
	 * @param string $new New code.
	 * @return array {
	 *     @type array $lines   Lines: type (same, add, remove, skip), text, old
	 *                          and new line numbers; skip lines carry a count.
	 *     @type int   $added   Lines added.
	 *     @type int   $removed Lines removed.
	 * }
	 */
	public static function diff( $old, $new ) {
		require_once ABSPATH . WPINC . '/wp-diff.php';

		$old_lines = explode( "\n", str_replace( "\r\n", "\n", (string) $old ) );
		$new_lines = explode( "\n", str_replace( "\r\n", "\n", (string) $new ) );
		$diff      = new \Text_Diff( 'auto', array( $old_lines, $new_lines ) );

		$all     = array();
		$old_no  = 1;
		$new_no  = 1;
		$added   = 0;
		$removed = 0;
		foreach ( $diff->getDiff() as $edit ) {
			$orig  = is_array( $edit->orig ) ? $edit->orig : array();
			$final = is_array( $edit->final ) ? $edit->final : array();
			if ( $edit instanceof \Text_Diff_Op_copy ) {
				foreach ( $orig as $text ) {
					$all[] = array(
						'type' => 'same',
						'text' => $text,
						'old'  => $old_no++,
						'new'  => $new_no++,
					);
				}
				continue;
			}
			foreach ( $orig as $text ) {
				$all[] = array(
					'type' => 'remove',
					'text' => $text,
					'old'  => $old_no++,
					'new'  => null,
				);
				++$removed;
			}
			foreach ( $final as $text ) {
				$all[] = array(
					'type' => 'add',
					'text' => $text,
					'old'  => null,
					'new'  => $new_no++,
				);
				++$added;
			}
		}

		return array(
			'lines'   => self::with_context( $all ),
			'added'   => $added,
			'removed' => $removed,
		);
	}

	/**
	 * Keeps changed lines and their context; folds the rest into skip lines.
	 *
	 * @param array $lines All lines.
	 * @return array
	 */
	private static function with_context( array $lines ) {
		$keep  = array();
		$count = count( $lines );
		foreach ( $lines as $index => $line ) {
			if ( 'same' === $line['type'] ) {
				continue;
			}
			$from = max( 0, $index - self::CONTEXT );
			$to   = min( $count - 1, $index + self::CONTEXT );
			for ( $i = $from; $i <= $to; $i++ ) {
				$keep[ $i ] = true;
			}
		}

		$out     = array();
		$skipped = 0;
		foreach ( $lines as $index => $line ) {
			if ( isset( $keep[ $index ] ) ) {
				if ( $skipped ) {
					$out[]   = array(
						'type'  => 'skip',
						'count' => $skipped,
					);
					$skipped = 0;
				}
				$out[] = $line;
			} else {
				++$skipped;
			}
		}
		if ( $skipped ) {
			$out[] = array(
				'type'  => 'skip',
				'count' => $skipped,
			);
		}
		return $out;
	}

	/**
	 * What changed, in words and numbers, for the list and the compare view.
	 *
	 * @param Snippet $snippet Snippet.
	 * @param bool    $lines   Whether to include the diff lines.
	 * @return array text, added, removed, domains, base, and lines when asked.
	 */
	public static function describe( Snippet $snippet, $lines = false ) {
		$base = self::trusted_base( $snippet );
		if ( ! $base['revision'] ) {
			return array(
				'text'    => __( 'Changed outside ScriptDock. No earlier version to compare with.', 'scriptdock' ),
				'added'   => 0,
				'removed' => 0,
				'domains' => array(),
				'base'    => null,
				'lines'   => array(),
			);
		}

		$old  = $base['revision']->post_content;
		$diff = self::diff( $old, $snippet->code );

		$added_text = array();
		foreach ( $diff['lines'] as $line ) {
			if ( 'add' === $line['type'] ) {
				$added_text[] = $line['text'];
			}
		}
		$domains = array_values( array_diff( self::domains( implode( "\n", $added_text ) ), self::domains( $old ) ) );

		if ( $diff['added'] && $diff['removed'] ) {
			/* translators: 1: number of lines added, 2: number of lines removed. */
			$text = sprintf( __( '%1$d added, %2$d removed', 'scriptdock' ), $diff['added'], $diff['removed'] );
		} elseif ( $diff['added'] ) {
			/* translators: %d: number of lines added. */
			$text = sprintf( _n( '%d line added', '%d lines added', $diff['added'], 'scriptdock' ), $diff['added'] );
		} elseif ( $diff['removed'] ) {
			/* translators: %d: number of lines removed. */
			$text = sprintf( _n( '%d line removed', '%d lines removed', $diff['removed'], 'scriptdock' ), $diff['removed'] );
		} else {
			$text = __( 'The code is the same; where it runs changed', 'scriptdock' );
		}
		if ( 1 === count( $domains ) ) {
			/* translators: 1: what changed, for example "1 line added", 2: a web address the new code loads from. */
			$text = sprintf( __( '%1$s, loading from %2$s', 'scriptdock' ), $text, $domains[0] );
		} elseif ( count( $domains ) > 1 ) {
			/* translators: 1: what changed, for example "1 line added", 2: number of new web addresses. */
			$text = sprintf( _n( '%1$s, loading from %2$d new site', '%1$s, loading from %2$d new sites', count( $domains ), 'scriptdock' ), $text, count( $domains ) );
		}

		$user = get_userdata( (int) $base['revision']->post_author );
		$time = (int) get_post_timestamp( $base['revision'], 'modified' );
		$out  = array(
			'text'    => $text,
			'added'   => $diff['added'],
			'removed' => $diff['removed'],
			'domains' => $domains,
			'base'    => array(
				'revision' => (int) $base['revision']->ID,
				'trusted'  => $base['trusted'],
				'time'     => $time ? gmdate( 'c', $time ) : '',
				'human'    => $time ? sprintf( /* translators: %s: time difference, for example "5 mins". */ __( '%s ago', 'scriptdock' ), human_time_diff( $time ) ) : '',
				'author'   => $user ? $user->display_name : '',
			),
		);
		if ( $lines ) {
			$out['lines'] = $diff['lines'];
		}
		return $out;
	}

	/**
	 * Host names of the web addresses in some code.
	 *
	 * @param string $code Code.
	 * @return string[]
	 */
	private static function domains( $code ) {
		preg_match_all( '#(?:https?:)?//([a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+)#i', (string) $code, $matches );
		return array_values( array_unique( array_map( 'strtolower', $matches[1] ) ) );
	}
}
