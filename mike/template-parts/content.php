<?php
/**
 * Single post in the archive flow — one entry in index.php's loop.
 *
 * Layout: meta → title → excerpt on the left, a square thumbnail on the right.
 * The thumbnail comes from mike_get_thumbnail() (featured image, else the first
 * wide-enough image in the content); when there is none, the figure is omitted.
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Customizer toggles (Blog / Archive section) — all default on.
$mike_thumb = get_theme_mod( 'show_thumbnail', true ) ? mike_get_thumbnail( get_the_ID(), 'large' ) : '';
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'mike-entry' ); ?>>

	<div class="entry-body">

		<?php if ( $mike_thumb ) : ?>
			<a class="entry-thumbnail" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
				<?php echo wp_kses_post( $mike_thumb ); ?>
			</a>
		<?php endif; ?>

		<div class="entry-text">

			<h2 class="entry-title">
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
			</h2>

			<?php if ( get_theme_mod( 'show_excerpt', true ) ) : ?>
				<div class="entry-summary"><?php the_excerpt(); ?></div>
			<?php endif; ?>

			<?php mike_entry_meta(); ?>

		</div><!-- .entry-text -->

	</div><!-- .entry-body -->

</article><!-- .mike-entry -->
