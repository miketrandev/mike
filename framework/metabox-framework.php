<?php
/**
 * Mini Metabox Framework
 * -----------------------------------------------------------------------------
 * Declare a post metabox in one call — like register_option() for the customizer.
 * The engine renders fields (reusing the theme's field-type vocabulary),
 * handles the nonce, and saves sanitized values to post meta.
 *
 * Pure functions, no class (WP metaboxes are callback-based — no class needed).
 *
 * Usage (config, e.g. in inc/featured.php):
 *
 *   add_action( 'init', function () {
 *       mike_register_metabox( array(
 *           'id'      => 'mike_post_options',
 *           'title'   => esc_html__( 'Post Options', 'mike' ),
 *           'screen'  => 'post',
 *           'context' => 'side',
 *           'fields'  => array(
 *               array( 'key' => '_mike_featured', 'type' => 'checkbox', 'label' => esc_html__( 'Feature this post', 'mike' ) ),
 *           ),
 *       ) );
 *   } );
 *
 * Field shape (same vocabulary as the builder/customizer):
 *   array( 'key' => '_meta_key', 'type' => 'checkbox|text|textarea|select|number',
 *          'label' => '', 'default' => '', 'choices' => array() )
 *
 * NOTE: register on a hook (init/load-post), never at file-load — labels use i18n.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------------
   Registry — queued metabox definitions, flushed on add_meta_boxes.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_metabox_registry' ) ) :
	function mike_metabox_registry( $definition = null ) {
		static $store = array();
		if ( is_array( $definition ) && ! empty( $definition['id'] ) ) {
			$store[ $definition['id'] ] = wp_parse_args( $definition, array(
				'id'       => '',
				'title'    => '',
				'screen'   => 'post',
				'context'  => 'side',
				'priority' => 'default',
				'fields'   => array(),
			) );
		}
		return $store;
	}
endif;

if ( ! function_exists( 'mike_register_metabox' ) ) :
	function mike_register_metabox( $definition ) {
		mike_metabox_registry( $definition );
	}
endif;

/* -----------------------------------------------------------------------------
   Add the queued metaboxes.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_add_metaboxes' ) ) :
	function mike_add_metaboxes() {
		foreach ( mike_metabox_registry() as $box ) {
			add_meta_box(
				$box['id'],
				$box['title'],
				'mike_render_metabox',
				$box['screen'],
				$box['context'],
				$box['priority'],
				array( 'box' => $box )
			);
		}
	}
endif;
add_action( 'add_meta_boxes', 'mike_add_metaboxes' );

/* -----------------------------------------------------------------------------
   Render a metabox's fields.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_render_metabox' ) ) :
	function mike_render_metabox( $post, $metabox ) {
		$box = isset( $metabox['args']['box'] ) ? $metabox['args']['box'] : array();
		if ( empty( $box['fields'] ) ) {
			return;
		}

		wp_nonce_field( 'mike_metabox_' . $box['id'], 'mike_metabox_nonce_' . $box['id'] );

		echo '<div class="mike-metabox">';
		foreach ( $box['fields'] as $field ) {
			mike_render_metabox_field( $field, $post->ID );
		}
		echo '</div>';
	}
endif;

if ( ! function_exists( 'mike_render_metabox_field' ) ) :
	function mike_render_metabox_field( $field, $post_id ) {
		$key     = isset( $field['key'] ) ? $field['key'] : '';
		if ( '' === $key ) {
			return;
		}
		$type    = isset( $field['type'] ) ? $field['type'] : 'text';
		$label   = isset( $field['label'] ) ? $field['label'] : '';
		$default = isset( $field['default'] ) ? $field['default'] : '';
		$stored  = get_post_meta( $post_id, $key, true );
		$value   = ( '' === $stored && metadata_exists( 'post', $post_id, $key ) === false ) ? $default : $stored;
		$id      = 'mike_mb_' . sanitize_html_class( $key );

		echo '<p class="mike-metabox__field">';

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $key ),
					checked( $value, '1', false ),
					esc_html( $label )
				);
				break;

			case 'textarea':
				printf( '<label for="%1$s">%2$s</label><br/>', esc_attr( $id ), esc_html( $label ) );
				printf( '<textarea id="%1$s" name="%2$s" rows="3" style="width:100%%">%3$s</textarea>', esc_attr( $id ), esc_attr( $key ), esc_textarea( $value ) );
				break;

			case 'select':
				printf( '<label for="%1$s">%2$s</label><br/>', esc_attr( $id ), esc_html( $label ) );
				printf( '<select id="%1$s" name="%2$s" style="width:100%%">', esc_attr( $id ), esc_attr( $key ) );
				$choices = isset( $field['choices'] ) ? $field['choices'] : array();
				foreach ( $choices as $ckey => $clabel ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $ckey ), selected( $value, $ckey, false ), esc_html( $clabel ) );
				}
				echo '</select>';
				break;

			case 'number':
				printf( '<label for="%1$s">%2$s</label><br/>', esc_attr( $id ), esc_html( $label ) );
				printf( '<input type="number" id="%1$s" name="%2$s" value="%3$s" style="width:100%%" />', esc_attr( $id ), esc_attr( $key ), esc_attr( $value ) );
				break;

			default: // text
				printf( '<label for="%1$s">%2$s</label><br/>', esc_attr( $id ), esc_html( $label ) );
				printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s" style="width:100%%" />', esc_attr( $id ), esc_attr( $key ), esc_attr( $value ) );
		}

		echo '</p>';
	}
endif;

/* -----------------------------------------------------------------------------
   Save — nonce-checked, capability-checked, sanitized per type.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_save_metaboxes' ) ) :
	function mike_save_metaboxes( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( mike_metabox_registry() as $box ) {
			$nonce_name = 'mike_metabox_nonce_' . $box['id'];
			if ( ! isset( $_POST[ $nonce_name ] ) ||
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), 'mike_metabox_' . $box['id'] ) ) {
				continue; // this box wasn't submitted / failed nonce.
			}

			foreach ( $box['fields'] as $field ) {
				$key  = isset( $field['key'] ) ? $field['key'] : '';
				$type = isset( $field['type'] ) ? $field['type'] : 'text';
				if ( '' === $key ) {
					continue;
				}

				if ( 'checkbox' === $type ) {
					// Unchecked boxes don't POST — set/clear explicitly.
					if ( ! empty( $_POST[ $key ] ) ) {
						update_post_meta( $post_id, $key, '1' );
					} else {
						delete_post_meta( $post_id, $key );
					}
					continue;
				}

				if ( ! isset( $_POST[ $key ] ) ) {
					continue;
				}
				$raw = wp_unslash( $_POST[ $key ] );

				switch ( $type ) {
					case 'textarea':
						$clean = sanitize_textarea_field( $raw );
						break;
					case 'number':
						$clean = (string) (int) $raw;
						break;
					case 'select':
						$choices = isset( $field['choices'] ) ? array_keys( $field['choices'] ) : array();
						$clean   = in_array( $raw, array_map( 'strval', $choices ), true ) ? $raw : '';
						break;
					default:
						$clean = sanitize_text_field( $raw );
				}

				if ( '' === $clean ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $clean );
				}
			}
		}
	}
endif;
add_action( 'save_post', 'mike_save_metaboxes' );
