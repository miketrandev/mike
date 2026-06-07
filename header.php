<!DOCTYPE html>

<html class="no-js" <?php language_attributes(); ?>>

	<head>

		<meta http-equiv="content-type" content="<?php bloginfo( 'html_type' ); ?>" charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" >

		<link rel="profile" href="http://gmpg.org/xfn/11">

		<?php wp_head(); ?>

	</head>

	<body <?php body_class(); ?>>

		<?php
		if ( function_exists( 'wp_body_open' ) ) {
			wp_body_open();
		}
		?>

		<a class="skip-link screen-reader-text" href="#site-content"><?php esc_html_e( 'Skip to the content', 'mike' ); ?></a>
		<a class="skip-link screen-reader-text" href="#menu-menu"><?php esc_html_e( 'Skip to the main menu', 'mike' ); ?></a>

		<header class="site-header" role="banner">

			<div class="container">

				<?php get_template_part( 'template-parts/branding' ); ?>

				<?php echo mike_header_primary_menu(); ?>

				<?php
				// Mobile-only hamburger — opens the off-canvas menu via the inline
				// toggle below. (The 'search' icon lives in inc/icons.php but is not
				// used yet.)
				?>
				<div class="header-icons">
					<button type="button" class="header-icon header-hamburger" aria-expanded="false" aria-controls="mike-offcanvas" onclick="mikeToggleOffcanvas(true)">
						<span class="screen-reader-text"><?php esc_html_e( 'Open menu', 'mike' ); ?></span>
						<?php mike_icon( 'menu', array( 'size' => 26 ) ); ?>
					</button>
				</div>

			</div>

		</header><!-- .site-header -->

		<?php
		// Off-canvas menu — opened by the hamburger. Hidden until toggled.
		// A tiny inline toggle keeps this self-contained (no extra JS file).
		?>
		<div class="header-offcanvas" id="mike-offcanvas" hidden>
			<div class="header-offcanvas__backdrop" onclick="mikeToggleOffcanvas(false)"></div>
			<div class="header-offcanvas__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Menu', 'mike' ); ?>">
				<button type="button" class="header-icon header-offcanvas__close" onclick="mikeToggleOffcanvas(false)">
					<span class="screen-reader-text"><?php esc_html_e( 'Close menu', 'mike' ); ?></span>
					<?php mike_icon( 'close', array( 'size' => 24 ) ); ?>
				</button>
				<?php echo mike_offcanvas_menu(); ?>
			</div>
		</div>

		<script>
		// Open/close the off-canvas menu. Inline so the theme ships no JS file.
		function mikeToggleOffcanvas( open ) {
			var panel = document.getElementById( 'mike-offcanvas' );
			var burger = document.querySelector( '.header-hamburger' );
			if ( ! panel ) { return; }
			if ( open ) {
				panel.hidden = false;
				// Next frame so the slide-in transition runs.
				requestAnimationFrame( function () { panel.classList.add( 'is-open' ); } );
			} else {
				panel.classList.remove( 'is-open' );
				setTimeout( function () { panel.hidden = true; }, 250 );
			}
			document.body.classList.toggle( 'has-offcanvas-open', open );
			if ( burger ) { burger.setAttribute( 'aria-expanded', open ? 'true' : 'false' ); }
		}
		// Close on Escape.
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) { mikeToggleOffcanvas( false ); }
		} );
		</script>

		<main class="site-main" id="site-content" role="main">

			<div class="container">