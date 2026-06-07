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

$mike_thumb = mike_get_thumbnail( get_the_ID(), 'medium' );
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'mike-entry' ); ?>>

	<div class="entry-body">

		<?php mike_entry_meta(); ?>

		<h2 class="entry-title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h2>

		<div class="entry-summary"><?php the_excerpt(); ?></div>

	</div><!-- .entry-body -->

	<?php if ( $mike_thumb ) : ?>
		<a class="entry-thumbnail" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php echo wp_kses_post( $mike_thumb ); ?>
		</a>
	<?php endif; ?>

</article><!-- .mike-entry -->
