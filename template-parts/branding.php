<?php
/**
 * Branding — logo + site title + tagline in the header.
 * Logo is independent; title and tagline each have their own customizer toggle.
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mike_show_title   = get_theme_mod( 'mike_show_title', true );
$mike_show_tagline = get_theme_mod( 'mike_show_tagline', true );
$mike_has_logo     = function_exists( 'has_custom_logo' ) && has_custom_logo();
$mike_tagline      = get_bloginfo( 'description', 'display' );
?>
<div class="mike-branding">

	<?php if ( $mike_has_logo ) { the_custom_logo(); } ?>

	<?php if ( $mike_show_title || ( $mike_show_tagline && $mike_tagline ) ) : ?>
		<div class="site-identity">

			<?php if ( $mike_show_title ) : ?>
				<p class="site-title">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
				</p>
			<?php endif; ?>

			<?php if ( $mike_show_tagline && $mike_tagline ) : ?>
				<p class="site-tagline"><?php echo esc_html( $mike_tagline ); ?></p>
			<?php endif; ?>

		</div><!-- .site-identity -->
	<?php endif; ?>

</div><!-- .mike-branding -->
