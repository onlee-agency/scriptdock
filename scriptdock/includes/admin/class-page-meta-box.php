<?php
/**
 * Page scripts meta box in the post editor.
 *
 * @package ScriptDock
 */

namespace ScriptDock\Admin;

use ScriptDock\Capabilities;
use ScriptDock\Compiler;
use ScriptDock\Page_Scripts;
use ScriptDock\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * "ScriptDock" box on posts and pages: page-specific code and switches for
 * site-wide snippets. Works in the block editor and the classic editor.
 */
final class Page_Meta_Box {

	/**
	 * Nonce action.
	 */
	const NONCE = 'scriptdock_page_scripts';

	/**
	 * Hooks the box.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'scriptdock_admin_assets', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Whether the box applies to a post type for the current user.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private static function applies( $post_type ) {
		return in_array( $post_type, Page_Scripts::post_types(), true ) && Capabilities::can_manage();
	}

	/**
	 * Registers the box.
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_Post $post      Post.
	 */
	public static function register( $post_type, $post ) {
		if ( ! self::applies( $post_type ) || Block_Editor::applies( $post ) ) {
			return;
		}
		add_meta_box( 'scriptdock-page-scripts', __( 'ScriptDock: page scripts', 'scriptdock' ), array( __CLASS__, 'render' ), $post_type, 'normal', 'low' );
	}

	/**
	 * Enqueues box assets.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public static function assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! self::applies( $screen->post_type ) ) {
			return;
		}
		$editors = Admin::code_editor_settings( array( 'html', 'css', 'js' ) );
		wp_enqueue_style( 'scriptdock-admin' );
		wp_enqueue_script( 'scriptdock-admin' );
		wp_enqueue_script( 'scriptdock-page-scripts', SCRIPTDOCK_URL . 'assets/js/page-scripts.js', array( 'scriptdock-admin', 'wp-i18n' ), Admin::asset_version( 'js/page-scripts.js' ), true );
		wp_set_script_translations( 'scriptdock-page-scripts', 'scriptdock' );
		wp_localize_script( 'scriptdock-page-scripts', 'scriptdockPageScripts', array( 'codeEditors' => $editors ) );
	}

	/**
	 * Renders the box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function render( $post ) {
		$data    = Page_Scripts::get( $post->ID );
		$trusted = Page_Scripts::is_trusted( $post->ID, $data );
		$runtime = Compiler::get();
		$off     = $data['disable_all'] || $data['disable'];

		$tabs = array(
			'head'   => array( __( 'Header', 'scriptdock' ), 'html', __( 'Printed in the <head> of this page only.', 'scriptdock' ) ),
			'body'   => array( __( 'Body', 'scriptdock' ), 'html', __( 'Printed right after <body> on this page only.', 'scriptdock' ) ),
			'footer' => array( __( 'Footer', 'scriptdock' ), 'html', __( 'Printed before </body> on this page only.', 'scriptdock' ) ),
			'before' => array( __( 'Before content', 'scriptdock' ), 'html', __( 'HTML added before this post’s content.', 'scriptdock' ) ),
			'after'  => array( __( 'After content', 'scriptdock' ), 'html', __( 'HTML added after this post’s content.', 'scriptdock' ) ),
			'css'    => array( __( 'CSS', 'scriptdock' ), 'css', __( 'Styles for this page only, no <style> tag needed.', 'scriptdock' ) ),
			'js'     => array( __( 'JavaScript', 'scriptdock' ), 'js', __( 'Runs in the footer of this page, after other scripts. No <script> tag needed.', 'scriptdock' ) ),
		);

		wp_nonce_field( self::NONCE, 'scriptdock_page_nonce' );
		?>
		<div class="scriptdock-page-box">
			<p id="scriptdock-page-editor-help" class="screen-reader-text"><?php esc_html_e( 'In the code editors, the Tab key adds a tab. To leave an editor, press Escape and then Tab.', 'scriptdock' ); ?></p>
			<?php if ( ! $trusted ) : ?>
				<div class="notice notice-error inline">
					<p><?php esc_html_e( 'The code below was changed outside ScriptDock and is not being output. Review it, then tick the box and update this post to approve it.', 'scriptdock' ); ?></p>
					<p><label><input type="checkbox" name="scriptdock_page[approve]" value="1"> <?php esc_html_e( 'I have checked this code. Approve it and let it run.', 'scriptdock' ); ?></label></p>
				</div>
			<?php endif; ?>

			<div class="scriptdock-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Page code', 'scriptdock' ); ?>">
				<?php
				$first = true;
				foreach ( $tabs as $key => $tab ) :
					$has = '' !== trim( (string) $data[ $key ] );
					?>
					<button type="button" role="tab" id="scriptdock-tab-<?php echo esc_attr( $key ); ?>" class="scriptdock-tab<?php echo $first ? ' is-active' : ''; ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>" aria-controls="scriptdock-page-<?php echo esc_attr( $key ); ?>" tabindex="<?php echo $first ? '0' : '-1'; ?>" data-tab="<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $tab[0] ); ?>
						<?php if ( $has ) : ?>
							<span class="scriptdock-dot" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( '(has code)', 'scriptdock' ); ?></span>
						<?php endif; ?>
					</button>
					<?php
					$first = false;
				endforeach;
				?>
				<button type="button" role="tab" id="scriptdock-tab-snippets" class="scriptdock-tab" aria-selected="false" aria-controls="scriptdock-page-snippets" tabindex="-1" data-tab="snippets">
					<?php esc_html_e( 'Site-wide snippets', 'scriptdock' ); ?>
					<?php if ( $off ) : ?>
						<span class="scriptdock-dot scriptdock-dot--warning" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( '(some are switched off on this page)', 'scriptdock' ); ?></span>
					<?php endif; ?>
				</button>
			</div>

			<?php
			$first = true;
			foreach ( $tabs as $key => $tab ) :
				?>
				<div class="scriptdock-tab-panel<?php echo $first ? ' is-active' : ''; ?>" role="tabpanel" id="scriptdock-page-<?php echo esc_attr( $key ); ?>" aria-labelledby="scriptdock-tab-<?php echo esc_attr( $key ); ?>" data-panel="<?php echo esc_attr( $key ); ?>"<?php echo $first ? '' : ' hidden'; ?>>
					<p class="description"><?php echo esc_html( $tab[2] ); ?></p>
					<textarea name="scriptdock_page[<?php echo esc_attr( $key ); ?>]" class="scriptdock-page-code widefat code" rows="8" data-language="<?php echo esc_attr( $tab[1] ); ?>" spellcheck="false"><?php echo esc_textarea( (string) $data[ $key ] ); ?></textarea>
				</div>
				<?php
				$first = false;
			endforeach;
			?>

			<div class="scriptdock-tab-panel" role="tabpanel" id="scriptdock-page-snippets" aria-labelledby="scriptdock-tab-snippets" data-panel="snippets" hidden>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Site-wide snippets on this page', 'scriptdock' ); ?></legend>
					<p><label><input type="radio" name="scriptdock_page[disable_mode]" value="" <?php checked( ! $off ); ?>> <?php esc_html_e( 'Run all site-wide snippets that match this page', 'scriptdock' ); ?></label></p>
					<p><label><input type="radio" name="scriptdock_page[disable_mode]" value="all" <?php checked( $data['disable_all'] ); ?>> <?php esc_html_e( 'Turn off all site-wide snippets and the global header & footer code on this page', 'scriptdock' ); ?></label></p>
					<p><label><input type="radio" name="scriptdock_page[disable_mode]" value="some" <?php checked( ! $data['disable_all'] && $data['disable'] ); ?>> <?php esc_html_e( 'Turn off selected snippets:', 'scriptdock' ); ?></label></p>
					<div class="scriptdock-snippet-checklist">
						<?php
						$listed = 0;
						foreach ( $runtime['snippets'] as $id => $snippet ) {
							if ( 'frontend' !== Compiler::context_for( $snippet['location'] ) || 'shortcode' === $snippet['location'] ) {
								continue;
							}
							++$listed;
							printf(
								'<label><input type="checkbox" name="scriptdock_page[disable][]" value="%1$d" %2$s> %3$s <span class="description">(%4$s · %5$s)</span></label>',
								(int) $id,
								checked( in_array( (int) $id, $data['disable'], true ), true, false ),
								esc_html( $snippet['title'] ),
								esc_html( Registry::type_labels()[ $snippet['type'] ] ),
								esc_html( Registry::location_label( $snippet['location'] ) )
							);
						}
						if ( ! $listed ) {
							echo '<p class="description">' . esc_html__( 'There are no active site-wide snippets.', 'scriptdock' ) . '</p>';
						}
						?>
					</div>
					<p class="description"><?php esc_html_e( 'PHP snippets that run everywhere start before WordPress knows which page is loading, so they cannot be switched off per page. Use conditional logic for those.', 'scriptdock' ); ?></p>
				</fieldset>
			</div>

			<p class="scriptdock-page-box__note"><?php esc_html_e( 'Saved when you update the page.', 'scriptdock' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Saves the box.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['scriptdock_page_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['scriptdock_page_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! self::applies( $post->post_type ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['scriptdock_page'] ) || ! is_array( $_POST['scriptdock_page'] ) ) {
			return;
		}
		// The box belongs to the post in the form. Other posts saved during
		// the same request (translations kept in sync, say) keep their own code.
		if ( ! isset( $_POST['post_ID'] ) || (int) $_POST['post_ID'] !== (int) $post_id ) {
			return;
		}

		$input = wp_unslash( $_POST['scriptdock_page'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Unfiltered code for users with unfiltered_html; the disable list is sanitized in Page_Scripts::save().
		$mode  = isset( $input['disable_mode'] ) ? (string) $input['disable_mode'] : '';

		$input['disable_all'] = 'all' === $mode;
		if ( 'some' !== $mode || empty( $input['disable'] ) || ! is_array( $input['disable'] ) ) {
			$input['disable'] = array();
		}

		// The box posts its code back with every update. Code that changed
		// outside ScriptDock is only approved when the box says so, not by
		// someone fixing a typo in the post.
		$sign = Page_Scripts::is_trusted( $post_id, Page_Scripts::get( $post_id ) ) || ! empty( $input['approve'] );

		Page_Scripts::save( $post_id, $input, $sign );
	}
}
