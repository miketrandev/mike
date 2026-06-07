<?php
/**
 * Admin — metaboxes and other edit-screen controls.
 *
 * Plain metaboxes, no block-editor JS. Loaded from functions.php.
 *
 * TABLE OF CONTENTS
 * ------------------------------------------------
 * 1. mike_page_options_box    - add the "Page options" metabox on the page editor
 * 2. mike_page_options_render - render the "Hide page title?" checkbox
 * 3. mike_page_options_save   - save the checkbox value
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Meta key: when '1', hide the page's single-header on the front end.
define( 'MIKE_HIDE_PAGE_TITLE_META', '_mike_hide_page_title' );

/* 1. mike_page_options_box - add the "Page options" metabox on the page editor
------------------------------------------------ */

if ( ! function_exists( 'mike_page_options_box' ) ) :
	function mike_page_options_box() {
		add_meta_box(
			'mike_page_options',
			esc_html__( 'Page options', 'mike' ),
			'mike_page_options_render',
			'page',
			'side'
		);
	}
	add_action( 'add_meta_boxes', 'mike_page_options_box' );
endif;

/* 2. mike_page_options_render - render the "Hide page title?" checkbox
------------------------------------------------ */

if ( ! function_exists( 'mike_page_options_render' ) ) :
	function mike_page_options_render( $post ) {
		$hide = get_post_meta( $post->ID, MIKE_HIDE_PAGE_TITLE_META, true );
		wp_nonce_field( 'mike_page_options', 'mike_page_options_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="mike_hide_page_title" value="1" <?php checked( $hide, '1' ); ?> />
				<?php esc_html_e( 'Hide page title?', 'mike' ); ?>
			</label>
		</p>
		<?php
	}
endif;

/* 3. mike_page_options_save - save the checkbox value
------------------------------------------------ */

if ( ! function_exists( 'mike_page_options_save' ) ) :
	function mike_page_options_save( $post_id ) {
		// Bail on the usual non-save passes.
		if ( ! isset( $_POST['mike_page_options_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mike_page_options_nonce'] ) ), 'mike_page_options' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Checked → store '1'; unchecked → drop the meta (default = show).
		if ( isset( $_POST['mike_hide_page_title'] ) ) {
			update_post_meta( $post_id, MIKE_HIDE_PAGE_TITLE_META, '1' );
		} else {
			delete_post_meta( $post_id, MIKE_HIDE_PAGE_TITLE_META );
		}
	}
	add_action( 'save_post_page', 'mike_page_options_save' );
endif;
