			</div><!-- .container -->

		</main><!-- .site-main -->

		<footer class="site-footer" role="contentinfo">

			<div class="container">

				<div class="reading">

					<?php
					// Build the copyright line in 3 parts, then print once.
					$copyright_text = '';

					// 1. Prefix: © year + site name.
					if ( get_theme_mod( 'mike_show_copyright_prefix', true ) ) {
						$copyright_text .= '&copy; ' . esc_html( wp_date( 'Y' ) ) . ' ' . esc_html( get_bloginfo( 'name' ) ) . ' &mdash; ';
					}

					// 2. Content: editable line, default "All rights reserved.".
					$copyright_text .= wp_kses_post( get_theme_mod( 'mike_copyright', esc_html__( 'All rights reserved.', 'mike' ) ) );

					// 3. Credit: made with love by Mike.
					if ( get_theme_mod( 'mike_keep_credit', true ) ) {
						$copyright_text .= ' <span class="mike-credit">' . esc_html__( 'Made with', 'mike' )
							. ' <span class="heart">&hearts;</span> ' . esc_html__( 'by', 'mike' )
							. ' <a href="https://miketran.net" target="_blank" title="' . esc_attr__( 'Build a site like this', 'mike' ) . '">Mike</a></span>';
					}
					?>
					<div class="copyright">
						<p><?php echo $copyright_text; // phpcs:ignore WordPress.Security.EscapeOutput -- parts escaped above. ?></p>
					</div>

					<?php
					if ( has_nav_menu( 'footer-menu' ) ) {
						wp_nav_menu( array(
							'theme_location' => 'footer-menu',
							'menu_class'     => 'menu footer-menu',
							'container'      => 'nav',
							'container_class' => 'footer-nav',
							'container_aria_label' => esc_attr__( 'Footer menu', 'mike' ),
							'depth'          => 1,
							'fallback_cb'    => false,
						) );
					}
					?>

				</div><!-- .reading -->

			</div><!-- .container -->

		</footer><!-- .site-footer -->

		<?php wp_footer(); ?>

	</body>
</html>
