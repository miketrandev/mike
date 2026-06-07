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

			</div>

		</header><!-- .site-header -->

		<main class="site-main" id="site-content" role="main">

			<div class="container">