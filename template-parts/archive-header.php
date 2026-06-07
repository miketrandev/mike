<?php
/**
 * Archive header — title/description at the top of index.php.
 * Covers home, archives, search, 404.
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mike_title = '';
$mike_desc  = '';

if ( is_search() ) {
	/* translators: %s: search query. */
	$mike_title = sprintf( esc_html__( 'Search results for: %s', 'mike' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
} elseif ( is_404() ) {
	$mike_title = esc_html__( 'Nothing found', 'mike' );
	$mike_desc  = esc_html__( 'That page could not be found. Try a search instead.', 'mike' );
} elseif ( is_home() ) {
	// Title only when a posts page is assigned.
	$mike_page = (int) get_option( 'page_for_posts' );
	if ( $mike_page ) {
		$mike_title = esc_html( get_the_title( $mike_page ) );
	}
} elseif ( is_archive() ) {
	// Category, tag, taxonomy, author, date, CPT.
	$mike_title = get_the_archive_title();
	$mike_desc  = get_the_archive_description();
} else {
	$mike_title = esc_html( get_the_title() );
}

// No title — skip the whole header.
if ( ! $mike_title ) {
	return;
}
?>

<header class="archive-header">

	<div class="archive-heading">
		<h1 class="archive-title"><?php echo wp_kses_post( $mike_title ); ?></h1>
		<?php
		// Show "Page N" on paged archives.
		$mike_paged = (int) get_query_var( 'paged' );
		if ( $mike_paged > 1 ) {
			/* translators: %d: page number. */
			echo '<span class="archive-page">' . sprintf( esc_html__( 'Page %d', 'mike' ), $mike_paged ) . '</span>';
		}
		?>
	</div>

	<?php if ( $mike_desc ) : ?>
		<div class="archive-description"><?php echo wp_kses_post( $mike_desc ); ?></div>
	<?php endif; ?>

	<?php
	// Search/404 run an empty loop — give visitors a way out.
	if ( is_search() || is_404() ) {
		get_search_form();
	}
	?>

</header><!-- .archive-header -->
