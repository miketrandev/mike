<?php get_header(); ?>

	<?php
	while ( have_posts() ) :
		the_post();
		?>
		
		<article id="post-<?php echo get_the_ID(); ?>" <?php post_class( 'mike-single' ); ?>>

			<?php
			// On a page, "Hide page title?" hides the whole single-header.
			$mike_hide_header = is_page() && '1' === get_post_meta( get_the_ID(), '_mike_hide_page_title', true );
			if ( ! $mike_hide_header ) :
				?>
				<header class="single-header">

					<h1 class="single-title"><?php the_title(); ?></h1>

					<?php mike_entry_meta(); ?>

				</header>
			<?php endif; ?>

			<div class="entry-content">
				<?php
				the_content();
				
				wp_link_pages( array(
					'before' => '<nav class="mike-pagelinks" aria-label="' . esc_attr__( 'Pages', 'mike' ) . '">',
					'after'  => '</nav>',
				) );
				?>
			</div><!-- .entry-content -->

			<?php
			// Single Post customizer toggles (default on).
			if ( get_theme_mod( 'show_tags', true ) ) {
				mike_single_tags();
			}
			?>

			<?php if (
				get_theme_mod( 'show_comments', true )
				&& ( comments_open() || get_comments_number() )
				&& ! post_password_required()
			) {
				comments_template();
			} ?>

		</article>

	<?php endwhile; ?>

<?php get_footer(); ?>
