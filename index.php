<?php
/**
 * The main template — the catch-all that drives every listing context:
 * blog home, posts page, CPT archives, date archives, category, tag, author,
 * search, and 404. Same shape everywhere: a reading-width container holding an
 * archive header, the post flow, and pagination.
 *
 * @package Mike
 */

get_header();
?>

	<div class="archive-wrap">

		<?php get_template_part( 'template-parts/archive-header' ); ?>

		<?php if ( have_posts() ) : ?>

			<div class="entry-list">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content' );
				endwhile;
				?>
			</div><!-- .entry-list -->

			<?php
			the_posts_pagination( array(
				'mid_size'           => 1,
				'prev_text'          => esc_html__( 'Previous', 'mike' ),
				'next_text'          => esc_html__( 'Next', 'mike' ),
				'screen_reader_text' => esc_html__( 'Posts navigation', 'mike' ),
			) );
			?>

		<?php elseif ( ! is_404() ) : ?>

			<p class="no-results"><?php esc_html_e( 'Nothing found.', 'mike' ); ?></p>

		<?php endif; ?>

	</div><!-- .archive-wrap -->

<?php get_footer(); ?>
