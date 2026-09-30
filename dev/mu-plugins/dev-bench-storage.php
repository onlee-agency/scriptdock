<?php
/**
 * Dev only (never ships): measures what it costs to load PHP snippets on a
 * page request, four ways.
 *
 *   ?sd-bench=stream   ScriptDock today: include through the scriptdock:// stream
 *                      wrapper. PHP compiles the code on every request.
 *   ?sd-bench=file     FluentSnippets' way: include real .php files, so OPcache
 *                      keeps the compiled code between requests.
 *   ?sd-bench=verified Real files plus a SHA-256 check of each file before
 *                      including it (what tamper protection would cost).
 *   ?sd-bench=eval     Code Snippets' way: eval() the code.
 *   ?sd-bench=hybrid   The planned file cache: real files, each checked with one
 *                      stat against the size and time recorded when it was
 *                      written (a changed file would fall back to the loader).
 *   ?sd-bench=runtime  ScriptDock's per-request cache read: verify the signed
 *                      runtime option and decode it.
 *   ?sd-bench=list-json / list-file  The same read for a runtime list holding
 *                      the n test snippets: today's signed JSON option, or the
 *                      list cached as a PHP file (OPcache).
 *
 * &n= number of snippets (default 20), &lines= lines per snippet (default 40).
 * Answers JSON with the time in microseconds. Local environment only.
 */

add_action(
	'plugins_loaded',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( 'local' !== wp_get_environment_type() || empty( $_GET['sd-bench'] ) ) {
			return;
		}
		$mode  = sanitize_key( wp_unslash( $_GET['sd-bench'] ) );
		$n     = isset( $_GET['n'] ) ? max( 1, min( 500, (int) $_GET['n'] ) ) : 20;
		$lines = isset( $_GET['lines'] ) ? max( 12, min( 1000, (int) $_GET['lines'] ) ) : 40;
		// phpcs:enable

		if ( 'runtime' === $mode ) {
			$stored = get_option( ScriptDock\Compiler::OPTION );
			$start  = hrtime( true );
			$valid  = ScriptDock\Signer::verify( $stored['data'], $stored['sig'] );
			$data   = json_decode( $stored['data'], true );
			$time   = ( hrtime( true ) - $start ) / 1000;
			wp_send_json(
				array(
					'mode'     => $mode,
					'us'       => round( $time, 1 ),
					'valid'    => $valid,
					'snippets' => count( $data['snippets'] ),
					'bytes'    => strlen( $stored['data'] ),
				)
			);
		}

		// The same realistic snippets for every mode: a guarded function with
		// string handling, a hook and a small config array.
		$sources = array();
		for ( $i = 1; $i <= $n; $i++ ) {
			$code  = "<?php\n// Bench snippet {$i}.\n";
			$code .= "if ( ! function_exists( 'sd_bench_{$i}' ) ) {\n\tfunction sd_bench_{$i}( \$value ) {\n";
			for ( $l = 0; $l < $lines - 10; $l++ ) {
				$code .= "\t\t\$value = is_string( \$value ) ? str_replace( 'a{$l}', 'b{$l}', \$value ) : \$value;\n";
			}
			$code         .= "\t\treturn \$value;\n\t}\n}\n";
			$code         .= "add_filter( 'sd_bench_filter_{$i}', 'sd_bench_{$i}' );\n";
			$code         .= "\$sd_bench_config = array( 'enabled' => true, 'label' => 'Snippet {$i}', 'priority' => {$i} );\n";
			$sources[ $i ] = $code;
		}

		$dir = WP_CONTENT_DIR . '/uploads/sd-bench/' . $n . '-' . $lines;
		$key = "sd_bench_{$n}_{$lines}";
		if ( in_array( $mode, array( 'file', 'verified', 'hybrid' ), true ) ) {
			wp_mkdir_p( $dir );
			$manifest = array();
			foreach ( $sources as $i => $code ) {
				$path = "{$dir}/snippet-{$i}.php";
				if ( ! file_exists( $path ) || file_get_contents( $path ) !== $code ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
					file_put_contents( $path, $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
				clearstatcache( true, $path );
				$manifest[ $i ] = array( filesize( $path ), filemtime( $path ) );
			}
			if ( get_option( "{$key}_manifest" ) !== $manifest ) {
				update_option( "{$key}_manifest", $manifest, true );
			}
		}

		// A runtime list holding these snippets, stored the way the compiler
		// stores it today and as a PHP file.
		if ( in_array( $mode, array( 'list-json', 'list-file' ), true ) ) {
			$data = array( 'snippets' => array() );
			foreach ( $sources as $i => $code ) {
				$data['snippets'][ $i ] = array(
					'id'       => $i,
					'type'     => 'php',
					'location' => 'php_everywhere',
					'priority' => 10,
					'cond'     => array(),
					'options'  => ScriptDock\Snippet::default_options(),
					'code'     => substr( $code, 6 ),
				);
			}
			$json = wp_json_encode( $data );
			if ( 'list-json' === $mode ) {
				$stored = get_option( "{$key}_list" );
				if ( ! is_array( $stored ) || $stored['data'] !== $json ) {
					update_option( "{$key}_list", array( 'data' => $json, 'sig' => ScriptDock\Signer::sign( $json ) ), true );
					$stored = get_option( "{$key}_list" );
				}
				$start = hrtime( true );
				$valid = ScriptDock\Signer::verify( $stored['data'], $stored['sig'] );
				$list  = json_decode( $stored['data'], true );
				$time  = ( hrtime( true ) - $start ) / 1000;
			} else {
				wp_mkdir_p( $dir );
				$path = "{$dir}/list.php";
				$php  = '<?php return ' . var_export( $data, true ) . ';'; // phpcs:ignore WordPress.PHP.DevelopmentFunctions
				if ( ! file_exists( $path ) || file_get_contents( $path ) !== $php ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
					file_put_contents( $path, $php ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
				$start = hrtime( true );
				$list  = include $path;
				$time  = ( hrtime( true ) - $start ) / 1000;
			}
			wp_send_json(
				array(
					'mode'  => $mode,
					'n'     => $n,
					'lines' => $lines,
					'us'    => round( $time, 1 ),
					'bytes' => strlen( $json ),
					'count' => count( $list['snippets'] ),
				)
			);
		}
		$manifest = 'hybrid' === $mode ? get_option( "{$key}_manifest" ) : array();
		$hashes = array();
		foreach ( $sources as $i => $code ) {
			$hashes[ $i ] = hash( 'sha256', $code );
		}

		$start = hrtime( true );
		foreach ( $sources as $i => $code ) {
			switch ( $mode ) {
				case 'stream':
					include ScriptDock\Stream::path_for( 900000 + $i, $code );
					break;
				case 'file':
					include "{$dir}/snippet-{$i}.php";
					break;
				case 'verified':
					if ( hash_file( 'sha256', "{$dir}/snippet-{$i}.php" ) === $hashes[ $i ] ) {
						include "{$dir}/snippet-{$i}.php";
					}
					break;
				case 'eval':
					eval( substr( $code, 5 ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
					break;
				case 'hybrid':
					$path = "{$dir}/snippet-{$i}.php";
					$stat = stat( $path );
					if ( $stat && $stat['size'] === $manifest[ $i ][0] && $stat['mtime'] === $manifest[ $i ][1] ) {
						include $path;
					} else {
						include ScriptDock\Stream::path_for( 900000 + $i, $code );
					}
					break;
			}
		}
		$time = ( hrtime( true ) - $start ) / 1000;

		wp_send_json(
			array(
				'mode'    => $mode,
				'n'       => $n,
				'lines'   => $lines,
				'us'      => round( $time, 1 ),
				'opcache' => function_exists( 'opcache_get_status' ) && ! empty( opcache_get_status( false )['opcache_enabled'] ),
			)
		);
	},
	0
);
