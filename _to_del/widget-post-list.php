<?php
/**
 * Classic "Mike — Post List" widget (sidebar/block-areas).
 * -----------------------------------------------------------------------------
 * A compact recent/category post list for the section sidebars: small thumbnail
 * + title + date. WordPress core has no built-in posts-with-thumbnail widget, so
 * this fills the common editorial need ("Latest in Travel" rail) using the same
 * compact look as the builder's cards.
 *
 * A WP_Widget subclass is the ONE place a class is unavoidable (the widget API
 * is class-based) — see CLAUDE.md's class rule. Output markup matches the
 * builder's .mike-card--compact so a single set of styles covers both.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Mike_Post_List_Widget' ) ) :
	class Mike_Post_List_Widget extends WP_Widget {

		public function __construct() {
			parent::__construct(
				'mike_post_list',
				esc_html__( 'Mike — Post List', 'mike' ),
				array(
					'description' => esc_html__( 'A compact list of posts (thumbnail + title + date). Good for a “Latest” or “Popular” rail in a homepage sidebar.', 'mike' ),
					'classname'   => 'mike-widget-post-list',
				)
			);
		}

		/** Defaults — one place; both form() and widget() read these. */
		protected function defaults() {
			return array(
				'title'      => '',
				'source'     => 'latest', // latest | popular | category
				'category'   => 0,
				'count'      => 5,
				'show_thumb' => 1,
				'show_date'  => 1,
			);
		}

		/** Front-end output. */
		public function widget( $args, $instance ) {
			$instance = wp_parse_args( (array) $instance, $this->defaults() );

			$query = $this->build_query( $instance );
			if ( ! $query->have_posts() ) {
				wp_reset_postdata();
				return; // nothing to show → render nothing (no empty rail).
			}

			echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput -- core sidebar markup.

			if ( '' !== trim( (string) $instance['title'] ) ) {
				$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );
				echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput -- core sidebar markup; title escaped.
			}

			$show_thumb = ! empty( $instance['show_thumb'] );
			$show_date  = ! empty( $instance['show_date'] );

			echo '<ul class="mike-postlist">';
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();
				?>
				<li class="mike-postlist__item mike-card mike-card--compact">
					<?php
					if ( $show_thumb && has_post_thumbnail( $post_id ) ) {
						// Reuse the builder's compact thumb look; 1:1 reads best in a rail.
						if ( function_exists( 'mike_card_thumb' ) ) {
							mike_card_thumb( $post_id, '1:1', 'mike-thumb', false );
						}
					}
					?>
					<div class="mike-card__body">
						<h3 class="mike-card__title"><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo wp_kses_post( get_the_title( $post_id ) ); ?></a></h3>
						<?php if ( $show_date ) : ?>
							<div class="mike-card__meta">
								<time class="mike-card__date" datetime="<?php echo esc_attr( get_the_date( 'c', $post_id ) ); ?>"><?php echo esc_html( get_the_date( '', $post_id ) ); ?></time>
							</div>
						<?php endif; ?>
					</div>
				</li>
				<?php
			}
			echo '</ul>';
			wp_reset_postdata();

			echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput -- core sidebar markup.
		}

		/** Build the WP_Query from the saved instance (trust boundary: instance is sanitized on save). */
		protected function build_query( $instance ) {
			$count = max( 1, (int) $instance['count'] );
			$query = array(
				'post_type'           => 'post',
				'posts_per_page'      => $count,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			);

			if ( 'popular' === $instance['source'] ) {
				$query['orderby'] = 'comment_count';
				$query['order']   = 'DESC';
			}
			if ( 'category' === $instance['source'] && (int) $instance['category'] > 0 ) {
				$query['cat'] = (int) $instance['category'];
			}

			return new WP_Query( $query );
		}

		/** Admin form. */
		public function form( $instance ) {
			$instance = wp_parse_args( (array) $instance, $this->defaults() );
			?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'mike' ); ?></label>
				<input class="widefat" type="text" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>">
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'source' ) ); ?>"><?php esc_html_e( 'Show:', 'mike' ); ?></label>
				<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'source' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'source' ) ); ?>">
					<option value="latest"   <?php selected( $instance['source'], 'latest' ); ?>><?php esc_html_e( 'Latest posts', 'mike' ); ?></option>
					<option value="popular"  <?php selected( $instance['source'], 'popular' ); ?>><?php esc_html_e( 'Popular (most commented)', 'mike' ); ?></option>
					<option value="category" <?php selected( $instance['source'], 'category' ); ?>><?php esc_html_e( 'Posts in a category', 'mike' ); ?></option>
				</select>
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'category' ) ); ?>"><?php esc_html_e( 'Category (when “Posts in a category”):', 'mike' ); ?></label>
				<?php
				wp_dropdown_categories( array(
					'show_option_none' => esc_html__( '— Select —', 'mike' ),
					'option_none_value' => 0,
					'hide_empty'       => false,
					'selected'         => (int) $instance['category'],
					'id'               => $this->get_field_id( 'category' ),
					'name'             => $this->get_field_name( 'category' ),
					'class'            => 'widefat',
				) );
				?>
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Number of posts:', 'mike' ); ?></label>
				<input class="tiny-text" type="number" min="1" max="20" step="1" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" value="<?php echo esc_attr( (int) $instance['count'] ); ?>">
			</p>
			<p>
				<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_thumb' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_thumb' ) ); ?>" <?php checked( ! empty( $instance['show_thumb'] ) ); ?>>
				<label for="<?php echo esc_attr( $this->get_field_id( 'show_thumb' ) ); ?>"><?php esc_html_e( 'Show thumbnails', 'mike' ); ?></label>
			</p>
			<p>
				<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_date' ) ); ?>" <?php checked( ! empty( $instance['show_date'] ) ); ?>>
				<label for="<?php echo esc_attr( $this->get_field_id( 'show_date' ) ); ?>"><?php esc_html_e( 'Show date', 'mike' ); ?></label>
			</p>
			<?php
		}

		/** Sanitize on save (trust boundary). */
		public function update( $new_instance, $old_instance ) {
			$instance               = $this->defaults();
			$instance['title']      = sanitize_text_field( isset( $new_instance['title'] ) ? $new_instance['title'] : '' );
			$instance['source']     = in_array( ( isset( $new_instance['source'] ) ? $new_instance['source'] : '' ), array( 'latest', 'popular', 'category' ), true ) ? $new_instance['source'] : 'latest';
			$instance['category']   = isset( $new_instance['category'] ) ? max( 0, (int) $new_instance['category'] ) : 0;
			$instance['count']      = isset( $new_instance['count'] ) ? max( 1, min( 20, (int) $new_instance['count'] ) ) : 5;
			$instance['show_thumb'] = empty( $new_instance['show_thumb'] ) ? 0 : 1;
			$instance['show_date']  = empty( $new_instance['show_date'] ) ? 0 : 1;
			return $instance;
		}
	}
endif;

if ( ! function_exists( 'mike_register_post_list_widget' ) ) :
	function mike_register_post_list_widget() {
		register_widget( 'Mike_Post_List_Widget' );
	}
endif;
add_action( 'widgets_init', 'mike_register_post_list_widget' );
