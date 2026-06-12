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
				// Mobile-only hamburger — opens the off-canvas menu. The whole
				// open/close is plain inline onclick + CSS (.is-open drives the
				// slide-in); the theme ships no toggle script.
				?>
				<div class="header-icons">
					<button type="button" class="header-icon header-hamburger" aria-expanded="false" aria-controls="mike-offcanvas" onclick="document.getElementById('mike-offcanvas').classList.add('is-open');this.setAttribute('aria-expanded','true');document.body.classList.add('has-offcanvas-open');document.querySelector('.header-offcanvas__close').focus();">
						<span class="screen-reader-text"><?php esc_html_e( 'Open menu', 'mike' ); ?></span>
						<?php mike_icon( 'menu', array( 'size' => 26 ) ); ?>
					</button>
				</div>

			</div>

		</header><!-- .site-header -->

		<?php
		// Off-canvas menu — opened by the hamburger. Closed by default (CSS).
		// Close (backdrop + button) reverts the class, aria, and scroll-lock,
		// then returns focus to the hamburger — all inline, no JS file.
		//
		// aria-modal is only honest when the focus-trap is active (a11y.js); when
		// the Misc toggle is off, we don't claim modal behavior.
		$mike_modal = get_theme_mod( 'menu_a11y_js', true ) ? ' aria-modal="true"' : '';
		$mike_close = "document.getElementById('mike-offcanvas').classList.remove('is-open');document.body.classList.remove('has-offcanvas-open');var b=document.querySelector('.header-hamburger');if(b){b.setAttribute('aria-expanded','false');b.focus();}";
		?>
		<div class="header-offcanvas" id="mike-offcanvas">
			<div class="header-offcanvas__backdrop" onclick="<?php echo esc_attr( $mike_close ); ?>"></div>
			<div class="header-offcanvas__panel" role="dialog"<?php echo $mike_modal; ?> aria-label="<?php esc_attr_e( 'Menu', 'mike' ); ?>">
				<button type="button" class="header-icon header-offcanvas__close" onclick="<?php echo esc_attr( $mike_close ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Close menu', 'mike' ); ?></span>
					<?php mike_icon( 'close', array( 'size' => 24 ) ); ?>
				</button>
				<?php echo mike_offcanvas_menu(); ?>
			</div>
		</div>

		<main class="site-main" id="site-content" role="main">

			<div class="container">