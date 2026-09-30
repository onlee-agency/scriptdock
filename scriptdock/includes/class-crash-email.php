<?php
/**
 * S19: the email sent when a snippet is switched off.
 *
 * @package ScriptDock
 */

namespace ScriptDock;

defined( 'ABSPATH' ) || exit;

/**
 * When a snippet brings the site down, ScriptDock switches it off and tells
 * whoever looks after the site. The message has to work when someone reads it
 * on a phone, in a hurry, possibly locked out of WordPress — so it says what
 * happened, what it did about it, where the problem is, and how to get back
 * in if the admin will not load.
 *
 * Email clients ignore stylesheets, custom properties and most modern CSS, so
 * the layout is tables with inline styles and the brand colours are written
 * out. Every message also carries a plain text version.
 */
final class Crash_Email {

	/**
	 * Body width, the width email clients handle reliably.
	 */
	const WIDTH = 600;

	/**
	 * Brand colours, as flat values. Email cannot read the design tokens.
	 */
	const INK     = '#111111';
	const BODY    = '#4f504d';
	const MUTED   = '#6b6c69';
	const LINE    = '#e7e5e1';
	const TINT    = '#f9f8f6';
	const SHELL   = '#f3f2ef';
	const DANGER  = '#d63638';
	const DANGER_TEXT = '#b32d2e';
	const LINK    = '#c2410c';
	const EMBER   = '#ff6400';

	/**
	 * Tells the site admin that a snippet was switched off.
	 *
	 * @param \WP_Post $post  Snippet post.
	 * @param array    $error Error data: message, line, time, url.
	 * @return string The address it went to, or '' when nothing was sent.
	 */
	public static function send( $post, array $error ) {
		$to = get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return '';
		}

		$site    = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$subject = sprintf(
			/* translators: 1: site name, 2: snippet title. */
			__( '[%1$s] ScriptDock switched off “%2$s”', 'scriptdock' ),
			$site,
			$post->post_title
		);

		$html = self::html( $post, $error, $site );
		$text = self::text( $post, $error, $site );

		// Both filters are put back straight after the send, so nothing else
		// on the site starts sending HTML by accident.
		$type = static function () {
			return 'text/html';
		};
		$alt  = static function ( $phpmailer ) use ( $text ) {
			$phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar -- PHPMailer's property.
		};

		add_filter( 'wp_mail_content_type', $type );
		add_action( 'phpmailer_init', $alt );
		$sent = wp_mail( $to, $subject, $html );
		remove_action( 'phpmailer_init', $alt );
		remove_filter( 'wp_mail_content_type', $type );

		return $sent ? $to : '';
	}

	/**
	 * The message as HTML.
	 *
	 * @param \WP_Post $post  Snippet post.
	 * @param array    $error Error data.
	 * @param string   $site  Site name.
	 * @return string
	 */
	private static function html( $post, array $error, $site ) {
		$facts = array();
		if ( ! empty( $error['line'] ) ) {
			$facts[] = array( __( 'Line', 'scriptdock' ), (string) (int) $error['line'] );
		}
		if ( ! empty( $error['url'] ) ) {
			$facts[] = array( __( 'Page', 'scriptdock' ), $error['url'] );
		}
		$facts[] = array( __( 'When', 'scriptdock' ), self::when( $error ) );

		$cells = '';
		foreach ( $facts as $fact ) {
			$cells .= sprintf(
				'<td style="padding:0 20px 0 0;font:400 12px/20px Arial,sans-serif;color:%1$s;" nowrap><strong style="font-weight:600;">%2$s</strong> %3$s</td>',
				esc_attr( self::BODY ),
				esc_html( $fact[0] ),
				esc_html( $fact[1] )
			);
		}

		$intro = sprintf(
			/* translators: 1: the kind of snippet, for example "PHP", 2: site name. */
			__( 'A %1$s snippet on %2$s caused a fatal error. ScriptDock switched it off straight away, so your site stayed up.', 'scriptdock' ),
			'<strong style="font-weight:600;">' . esc_html( self::type_label( $post ) ) . '</strong>',
			'<strong style="font-weight:600;">' . esc_html( $site ) . '</strong>'
		);

		$safe = __( 'Open your safe mode link. It pauses every snippet in your browser so you can reach the admin. You saved it during setup — it is also in ScriptDock → Settings.', 'scriptdock' );

		$footer = sprintf(
			/* translators: %s: a link reading "Turn these off". */
			__( 'Sent by ScriptDock because error alerts are on. %s', 'scriptdock' ),
			sprintf(
				'<a href="%1$s" style="color:%2$s;">%3$s</a>',
				esc_url( Settings::page_url() ),
				esc_attr( self::LINK ),
				esc_html__( 'Turn these off', 'scriptdock' )
			)
		);

		ob_start();
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?php echo esc_html( $post->post_title ); ?></title>
</head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( self::SHELL ); ?>;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">
	<?php echo esc_html( self::preview( $post, $error ) ); ?>
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:<?php echo esc_attr( self::SHELL ); ?>;">
<tr><td align="center" style="padding:28px 12px;">

<table role="presentation" width="<?php echo (int) self::WIDTH - 80; ?>" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:<?php echo (int) self::WIDTH - 80; ?>px;background:#ffffff;border:1px solid <?php echo esc_attr( self::LINE ); ?>;border-radius:14px;">

	<tr><td style="padding:26px 30px 0;">
		<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
			<td width="18" style="width:18px;"><div style="width:18px;height:18px;border-radius:9px;background:<?php echo esc_attr( self::EMBER ); ?>;"></div></td>
			<td style="padding-left:9px;font:800 17px/18px Arial,sans-serif;letter-spacing:-0.4px;color:<?php echo esc_attr( self::INK ); ?>;">scriptdock</td>
		</tr></table>
	</td></tr>

	<tr><td style="padding:20px 30px 0;">
		<h1 style="margin:0;font:800 22px/30px Arial,sans-serif;letter-spacing:-0.4px;color:<?php echo esc_attr( self::INK ); ?>;">
			<?php esc_html_e( 'We switched off a snippet to keep your site running', 'scriptdock' ); ?>
		</h1>
	</td></tr>

	<tr><td style="padding:16px 30px 0;font:400 14px/22px Arial,sans-serif;color:<?php echo esc_attr( self::BODY ); ?>;">
		<?php echo wp_kses( $intro, array( 'strong' => array( 'style' => array() ) ) ); ?>
	</td></tr>

	<tr><td style="padding:16px 30px 0;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:<?php echo esc_attr( self::TINT ); ?>;border:1px solid <?php echo esc_attr( self::LINE ); ?>;border-left:3px solid <?php echo esc_attr( self::DANGER ); ?>;border-radius:10px;">
			<tr><td style="padding:16px 18px;">
				<div style="font:700 15px/22px Arial,sans-serif;color:<?php echo esc_attr( self::INK ); ?>;"><?php echo esc_html( $post->post_title ); ?></div>
				<div style="padding-top:10px;font:400 12px/19px Consolas,Menlo,monospace;color:<?php echo esc_attr( self::DANGER_TEXT ); ?>;word-break:break-word;"><?php echo esc_html( $error['message'] ); ?></div>
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="padding-top:10px;"><tr><?php echo $cells; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built and escaped above. ?></tr></table>
			</td></tr>
		</table>
	</td></tr>

	<tr><td style="padding:16px 30px 0;">
		<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
			<td style="border-radius:23px;background:<?php echo esc_attr( self::INK ); ?>;">
				<a href="<?php echo esc_url( Snippets::edit_url( $post->ID ) ); ?>" style="display:inline-block;padding:13px 24px;font:600 15px/20px Arial,sans-serif;color:#ffffff;text-decoration:none;">
					<?php esc_html_e( 'Fix the snippet', 'scriptdock' ); ?>
				</a>
			</td>
		</tr></table>
	</td></tr>

	<tr><td style="padding:22px 30px 26px;">
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="padding-top:16px;border-top:1px solid <?php echo esc_attr( self::LINE ); ?>;">
			<div style="font:700 13px/20px Arial,sans-serif;color:<?php echo esc_attr( self::INK ); ?>;"><?php esc_html_e( 'If you cannot get into WordPress', 'scriptdock' ); ?></div>
			<div style="padding-top:6px;font:400 13px/20px Arial,sans-serif;color:<?php echo esc_attr( self::BODY ); ?>;"><?php echo esc_html( $safe ); ?></div>
		</td></tr></table>
	</td></tr>

	<tr><td style="padding:16px 30px;background:<?php echo esc_attr( self::TINT ); ?>;border-top:1px solid <?php echo esc_attr( self::LINE ); ?>;border-radius:0 0 13px 13px;">
		<div style="font:400 12px/19px Arial,sans-serif;color:<?php echo esc_attr( self::BODY ); ?>;"><?php echo wp_kses( $footer, array( 'a' => array( 'href' => array(), 'style' => array() ) ) ); ?></div>
		<div style="padding-top:4px;font:400 12px/19px Arial,sans-serif;color:<?php echo esc_attr( self::MUTED ); ?>;"><?php echo esc_html( self::host() ); ?></div>
	</td></tr>

</table>

</td></tr>
</table>
</body>
</html>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * The message as plain text, for clients that show no HTML.
	 *
	 * @param \WP_Post $post  Snippet post.
	 * @param array    $error Error data.
	 * @param string   $site  Site name.
	 * @return string
	 */
	private static function text( $post, array $error, $site ) {
		$lines = array(
			__( 'We switched off a snippet to keep your site running', 'scriptdock' ),
			'',
			sprintf(
				/* translators: 1: the kind of snippet, for example "PHP", 2: site name. */
				__( 'A %1$s snippet on %2$s caused a fatal error. ScriptDock switched it off straight away, so your site stayed up.', 'scriptdock' ),
				self::type_label( $post ),
				$site
			),
			'',
			$post->post_title,
			$error['message'],
		);

		if ( ! empty( $error['line'] ) ) {
			/* translators: %d: line number. */
			$lines[] = sprintf( __( 'Line: %d', 'scriptdock' ), (int) $error['line'] );
		}
		if ( ! empty( $error['url'] ) ) {
			/* translators: %s: the page the error happened on. */
			$lines[] = sprintf( __( 'Page: %s', 'scriptdock' ), $error['url'] );
		}
		/* translators: %s: date and time. */
		$lines[] = sprintf( __( 'When: %s', 'scriptdock' ), self::when( $error ) );

		$lines[] = '';
		$lines[] = __( 'Fix the snippet:', 'scriptdock' );
		$lines[] = Snippets::edit_url( $post->ID );
		$lines[] = '';
		$lines[] = __( 'If you cannot get into WordPress', 'scriptdock' );
		$lines[] = __( 'Open your safe mode link. It pauses every snippet in your browser so you can reach the admin. You saved it during setup — it is also in ScriptDock → Settings.', 'scriptdock' );
		$lines[] = '';
		$lines[] = __( 'Sent by ScriptDock because error alerts are on. Turn them off in ScriptDock → Settings.', 'scriptdock' );
		$lines[] = self::host();

		return implode( "\n", $lines );
	}

	/**
	 * The line email clients show beside the subject.
	 *
	 * @param \WP_Post $post  Snippet post.
	 * @param array    $error Error data.
	 * @return string
	 */
	private static function preview( $post, array $error ) {
		return sprintf(
			/* translators: 1: snippet title, 2: the error message. */
			__( '%1$s stopped the site, so it is switched off: %2$s', 'scriptdock' ),
			$post->post_title,
			$error['message']
		);
	}

	/**
	 * When the error happened, in the site's own time and format.
	 *
	 * @param array $error Error data.
	 * @return string
	 */
	private static function when( array $error ) {
		$time = empty( $error['time'] ) ? time() : (int) $error['time'];
		return wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $time );
	}

	/**
	 * What kind of snippet it was, for the opening line.
	 *
	 * @param \WP_Post $post Snippet post.
	 * @return string
	 */
	private static function type_label( $post ) {
		$snippet = Snippet::get( $post->ID );
		$labels  = Registry::type_labels();
		return $snippet && isset( $labels[ $snippet->type ] ) ? $labels[ $snippet->type ] : __( 'code', 'scriptdock' );
	}

	/**
	 * The site's host name, for the footer.
	 *
	 * @return string
	 */
	private static function host() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host ? $host : home_url();
	}
}
