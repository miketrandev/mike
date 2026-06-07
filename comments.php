<?php
/**
 * Comments template — native WordPress comments, theme-styled.
 * -----------------------------------------------------------------------------
 * Displayed below the article (single.php calls comments_template()), in the same
 * reading-width column as the body. Plain native comments — no off-canvas drawer,
 * no JS beyond core's comment-reply (enqueued in functions.php). The threaded list
 * uses a small render callback (mike_comment) for clean, typographic markup.
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Never show comments on a password-protected post until the password is entered.
if ( post_password_required() ) {
	return;
}
?>

<section id="comments" class="mike-comments">

	<?php if ( have_comments() ) : ?>

		<h2 class="comments-title">
			<?php
			$mike_comment_count = (int) get_comments_number();
			printf(
				/* translators: %s: comment count. */
				esc_html( _n( '%s Comment', '%s Comments', $mike_comment_count, 'mike' ) ),
				esc_html( number_format_i18n( $mike_comment_count ) )
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 44,
				'callback'    => function_exists( 'mike_comment' ) ? 'mike_comment' : null,
			) );
			?>
		</ol>

		<?php
		// Pagination (only when comments are split across pages).
		$mike_comment_nav = get_the_comments_navigation( array(
			'prev_text' => '&larr; ' . esc_html__( 'Older comments', 'mike' ),
			'next_text' => esc_html__( 'Newer comments', 'mike' ) . ' &rarr;',
		) );
		if ( $mike_comment_nav ) {
			echo '<nav class="comment-navigation" aria-label="' . esc_attr__( 'Comments navigation', 'mike' ) . '">' . $mike_comment_nav . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput -- core nav markup.
		}
		?>

	<?php endif; ?>

	<?php
	// Comments are closed, but the post HAS comments → say so (don't go silent).
	if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) :
		?>
		<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'mike' ); ?></p>
		<?php
	endif;

	// The reply form. Native comment_form() — placeholders keep it clean; the
	// title/labels inherit the theme's type. Honors comments_open() itself.
	comment_form( array(
		'class_container'    => 'mike-comment-form',
		'title_reply'        => esc_html__( 'Leave a comment', 'mike' ),
		'title_reply_to'     => esc_html__( 'Reply to %s', 'mike' ),
		'comment_notes_before' => '',
		'comment_field'      => sprintf(
			'<p class="comment-form-comment"><label for="comment" class="screen-reader-text">%1$s</label><textarea id="comment" name="comment" rows="6" placeholder="%2$s" required></textarea></p>',
			esc_html__( 'Comment', 'mike' ),
			esc_attr__( 'Join the discussion…', 'mike' )
		),
	) );
	?>

</section><!-- #comments -->
