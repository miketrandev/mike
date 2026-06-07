<?php
/**
 * Mini Live Customizer Framework
 * -----------------------------------------------------------------------------
 * A thin wrapper over the WordPress Customizer so theme options can be declared
 * in one call instead of repeating add_setting() + add_control() boilerplate.
 *
 * Usage:
 *
 *   mike_register_section( 'header', array(
 *       'title'    => esc_html__( 'Header', 'mike' ),
 *       'priority' => 30,
 *   ) );
 *
 *   mike_register_option( array(
 *       'id'      => 'header_layout',
 *       'type'    => 'image_radio',
 *       'section' => 'header',
 *       'label'   => esc_html__( 'Header Layout', 'mike' ),
 *       'default' => 1,
 *       'options' => array(
 *           1 => array( 'src' => '.../layout-1.png', 'title' => 'Centered' ),
 *           2 => array( 'src' => '.../layout-2.png', 'title' => 'Left' ),
 *       ),
 *   ) );
 *
 * Then read the saved value anywhere with get_theme_mod( 'header_layout', 1 ).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/* -----------------------------------------------------------------------------
   Registry
   Holds queued sections and options until `customize_register` fires.

   Config registers options on `customize_register` (default priority 10); the
   build step flushes the queue into $wp_customize later (priority 20). This
   keeps all register_*() calls inside a hook — never fired at bare file-load
   time — using WordPress's own hook, no custom action needed.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_customizer_registry' ) ) :
	function mike_customizer_registry( $bucket, $item = null ) {
		static $store = array(
			'panels'   => array(),
			'sections' => array(),
			'options'  => array(),
		);

		if ( null !== $item ) {
			$store[ $bucket ][] = $item;
		}

		return $store[ $bucket ];
	}
endif;


/**
 * Register a Customizer panel — a container for related sections (e.g. "Ads"
 * containing After header / Before post / After post / Footer). Use sparingly:
 * most theme features need only a section. A panel pays off when the editor's
 * mental model is "pick a place, then configure it" and there are 3+ siblings.
 *
 * @param string $id   Panel ID (referenced by section's `panel` arg).
 * @param array  $args Standard add_panel() args (title, priority, description...).
 */
if ( ! function_exists( 'mike_register_panel' ) ) :
	function mike_register_panel( $id, $args = array() ) {
		mike_customizer_registry( 'panels', array(
			'id'   => $id,
			'args' => $args,
		) );
	}
endif;


/**
 * Register a Customizer section.
 *
 * @param string $id   Section ID (used by options' `section` arg).
 * @param array  $args Standard add_section() args (title, priority, description...).
 */
if ( ! function_exists( 'mike_register_section' ) ) :
	function mike_register_section( $id, $args = array() ) {
		mike_customizer_registry( 'sections', array(
			'id'   => $id,
			'args' => $args,
		) );
	}
endif;


/**
 * Register a single theme option (one setting + one control).
 *
 * @param array $args {
 *     @type string $id                Required. Setting ID, read via get_theme_mod().
 *     @type string $section           Required. Section ID to attach the control to.
 *     @type string $type              Control type. Native WP types or 'image_radio'.
 *     @type string $label             Control label.
 *     @type string $description       Optional control description.
 *     @type mixed  $default           Default value.
 *     @type string $transport         'refresh' (default) or 'postMessage'.
 *     @type mixed  $sanitize_callback Override the type-based default sanitizer.
 *     @type array  $options           For select/radio/image_radio: choices.
 *     @type int    $priority          Optional control priority.
 * }
 */
if ( ! function_exists( 'mike_register_option' ) ) :
	function mike_register_option( $args ) {
		mike_customizer_registry( 'options', $args );
	}
endif;


/* -----------------------------------------------------------------------------
   Default sanitizers
   Always set a sanitizer (security + theme-check), pick a sane one per type,
   but let callers override via 'sanitize_callback'.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_default_sanitizer' ) ) :
	function mike_default_sanitizer( $type, $args = array() ) {
		switch ( $type ) {
			case 'checkbox':
				return 'wp_validate_boolean';

			case 'textarea':
				return 'wp_kses_post';

			case 'email':
				return 'sanitize_email';

			case 'url':
				return 'esc_url_raw';

			case 'number':
				return 'absint';

			case 'color':
				return 'sanitize_hex_color';

			case 'image':
				// Image_Control stores a URL.
				return 'esc_url_raw';

			case 'media_image':
				// Media_Control stores an ATTACHMENT ID — preferable to a raw URL
				// when the template needs to read attachment metadata (alt text,
				// caption, srcset). Sanitize as a positive int; 0 = "nothing
				// selected" (the empty state of WP_Customize_Media_Control).
				return 'absint';

			case 'select':
			case 'radio':
			case 'image_radio':
				// Only accept values that exist among the declared options.
				$choices = isset( $args['options'] ) ? array_keys( $args['options'] ) : array();
				return function ( $value ) use ( $choices ) {
					return in_array( $value, array_map( 'strval', $choices ), true ) ? $value : '';
				};

			case 'multicheckbox':
			case 'multiselect':
				// Value is an array (or a comma-joined string from the hidden input);
				// keep only entries among the declared options.
				$choices = isset( $args['options'] ) ? array_map( 'strval', array_keys( $args['options'] ) ) : array();
				return function ( $value ) use ( $choices ) {
					if ( ! is_array( $value ) ) {
						$value = ( '' === $value || null === $value ) ? array() : explode( ',', (string) $value );
					}
					$value = array_map( 'strval', $value );
					return array_values( array_intersect( $value, $choices ) );
				};

			case 'columns':
				// "desktop,tablet,mobile" — exactly 3 ints clamped to 1..6.
				return function ( $value ) {
					$parts = array_map( 'intval', array_pad( explode( ',', (string) $value ), 3, 0 ) );
					$clamp = function ( $n, $fallback ) {
						$n = (int) $n;
						return ( $n >= 1 && $n <= 6 ) ? $n : $fallback;
					};
					return $clamp( $parts[0], 3 ) . ',' . $clamp( $parts[1], 2 ) . ',' . $clamp( $parts[2], 1 );
				};

			default:
				return 'sanitize_text_field';
		}
	}
endif;


/* -----------------------------------------------------------------------------
   Flush the registry into the live Customizer manager.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_customizer_build' ) ) :
function mike_customizer_build( $wp_customize ) {

	// Panels first — sections inside a panel reference it by id, so panels
	// must exist before the sections register.
	foreach ( mike_customizer_registry( 'panels' ) as $panel ) {
		$wp_customize->add_panel( $panel['id'], $panel['args'] );
	}

	// Sections.
	foreach ( mike_customizer_registry( 'sections' ) as $section ) {
		$wp_customize->add_section( $section['id'], $section['args'] );
	}

	// Options (setting + control).
	foreach ( mike_customizer_registry( 'options' ) as $args ) {

		if ( empty( $args['id'] ) || empty( $args['section'] ) ) {
			continue;
		}

		$id   = $args['id'];
		$type = isset( $args['type'] ) ? $args['type'] : 'text';

		// 'html' is a DISPLAY-ONLY control (panel decoration: a heading, divider, or
		// note between options). It has no real value — BUT WordPress's customizer
		// JS does not reliably mount a setting-LESS custom control (it renders only
		// controls it received settings data for). So we bind it to a tiny dummy
		// setting (sanitize → '', not saved to the DB in any meaningful way) so the
		// control is a first-class citizen and its render_content() actually runs.
		// This is the core-blessed pattern for content-only controls.
		$is_display = ( 'html' === $type );

		if ( $is_display ) {
			// Dummy setting: existential only, so the control mounts. type 'option'
			// with a noop sanitizer; never read anywhere.
			$wp_customize->add_setting( $id, array(
				'default'           => '',
				'sanitize_callback' => function () {
					return '';
				},
				'transport'         => 'postMessage',
			) );
		} else {
			$sanitize = isset( $args['sanitize_callback'] )
				? $args['sanitize_callback']
				: mike_default_sanitizer( $type, $args );

			// Setting.
			$wp_customize->add_setting( $id, array(
				'default'           => isset( $args['default'] ) ? $args['default'] : '',
				'sanitize_callback' => $sanitize,
				'transport'         => isset( $args['transport'] ) ? $args['transport'] : 'refresh',
			) );
		}

		// Shared control args. The display control now DOES bind to its dummy
		// setting (so it mounts) — render_content() supplies the visible markup.
		$control_args = array(
			'section' => $args['section'],
			'label'   => isset( $args['label'] ) ? $args['label'] : '',
		);
		$control_args['settings'] = $id;
		if ( isset( $args['description'] ) ) {
			$control_args['description'] = $args['description'];
		}
		if ( isset( $args['priority'] ) ) {
			$control_args['priority'] = $args['priority'];
		}
		// input_attrs (e.g. min/max/step for number) — passed straight to the core
		// control. Optional; only set when provided.
		if ( isset( $args['input_attrs'] ) && is_array( $args['input_attrs'] ) ) {
			$control_args['input_attrs'] = $args['input_attrs'];
		}
		// active_callback — show/hide a control based on another setting's value
		// (WP-native conditional UI, no custom JS). Used e.g. for "Custom ratio"
		// which only shows when the ratio select is 'custom'.
		if ( isset( $args['active_callback'] ) && is_callable( $args['active_callback'] ) ) {
			$control_args['active_callback'] = $args['active_callback'];
		}
		$choices = isset( $args['options'] ) ? $args['options'] : array();
		if ( in_array( $type, array( 'select', 'radio' ), true ) ) {
			$control_args['choices'] = $choices;
		}

		$control_id = $id . '_control';

		// Native types pass a 'type' string; richer controls need a control object.
		switch ( $type ) {

			case 'color':
				$wp_customize->add_control( new WP_Customize_Color_Control(
					$wp_customize, $control_id, $control_args
				) );
				break;

			case 'image':
				// Image_Control stores the URL as the setting value (matches the
				// esc_url_raw sanitizer), so templates can use it directly.
				$wp_customize->add_control( new WP_Customize_Image_Control(
					$wp_customize, $control_id, $control_args
				) );
				break;

			case 'media_image':
				// Media_Control stores the attachment ID (an int). Templates fetch
				// the URL via wp_get_attachment_image_url() and alt via the
				// _wp_attachment_image_alt meta the media library captures on upload.
				$control_args['mime_type'] = 'image';
				$wp_customize->add_control( new WP_Customize_Media_Control(
					$wp_customize, $control_id, $control_args
				) );
				break;

			case 'image_radio':
				$control_args['choices'] = $choices;
				$wp_customize->add_control( new Mike_Image_Radio_Control(
					$wp_customize, $control_id, $control_args
				) );
				break;

			case 'multicheckbox':
				$control_args['choices'] = $choices;
				$wp_customize->add_control( new Mike_Multicheckbox_Control(
					$wp_customize, $control_id, $control_args
				) );
				break;

			case 'multiselect':
				$control_args['choices'] = $choices;
				$wp_customize->add_control( new Mike_Multiselect_Control(
					$wp_customize, $control_id, $control_args
				) );
				break;

			case 'columns':
				$wp_customize->add_control( new Mike_Columns_Control(
					$wp_customize, $control_id, $control_args
				) );
				break;

			case 'html':
				// Display-only: a heading / divider / note in the panel. The markup
				// comes from 'content' (printed via wp_kses_post, so safe but flexible).
				$control_args['content'] = isset( $args['content'] ) ? $args['content'] : '';
				$wp_customize->add_control( new Mike_Html_Control(
					$wp_customize, $control_id, $control_args
				) );
				break;

			default:
				$control_args['type'] = $type;
				$wp_customize->add_control( $control_id, $control_args );
				break;
		}
	}
}
endif;

/*
 * Flush the queued sections/options into the live manager.
 * Priority 20: config registers options on customize_register at the default
 * priority (10), filling the queue before this drains it.
 */
add_action( 'customize_register', 'mike_customizer_build', 20 );


/* -----------------------------------------------------------------------------
   Custom controls — defined only where WordPress offers no native equivalent.
   image_radio  : visual radio (single value), pure server-rendered, no JS.
   multicheckbox: pick many from a list (array value).
   multiselect  : pick many from a <select multiple> (array value).

   The multi-value controls sync their inputs to the customizer setting with a
   tiny script (framework/customizer-controls.js) — unavoidable, since WP's
   $this->link() binds a single value per input.
----------------------------------------------------------------------------- */

add_action( 'customize_register', 'mike_register_custom_controls', 5 );

if ( ! function_exists( 'mike_register_custom_controls' ) ) :
function mike_register_custom_controls() {

	if ( class_exists( 'Mike_Image_Radio_Control' ) || ! class_exists( 'WP_Customize_Control' ) ) {
		return;
	}

	class Mike_Image_Radio_Control extends WP_Customize_Control {

		public $type = 'mike_image_radio';

		public function render_content() {

			if ( empty( $this->choices ) ) {
				return;
			}

			$name = '_customize-radio-' . $this->id;
			?>
			<fieldset class="mike-image-radio">
				<?php if ( ! empty( $this->label ) ) : ?>
					<legend class="customize-control-title"><?php echo esc_html( $this->label ); ?></legend>
				<?php endif; ?>

				<?php if ( ! empty( $this->description ) ) : ?>
					<span class="description customize-control-description"><?php echo wp_kses_post( $this->description ); ?></span>
				<?php endif; ?>

				<div class="mike-image-radio__grid">
					<?php foreach ( $this->choices as $value => $choice ) :
						$value = (string) $value;
						$src   = isset( $choice['src'] ) ? $choice['src'] : '';
						$title = isset( $choice['title'] ) ? $choice['title'] : $value;
						$width = isset( $choice['width'] ) ? $choice['width'] : '';
						$uid   = $name . '-' . $value;
						// Optional per-image width (any CSS unit, e.g. '120px', '30%').
						$style = $width ? ' style="max-width:' . esc_attr( $width ) . '"' : '';
						?>
						<label class="mike-image-radio__item" for="<?php echo esc_attr( $uid ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput -- value escaped above ?>>
							<input
								type="radio"
								class="mike-image-radio__input screen-reader-text"
								id="<?php echo esc_attr( $uid ); ?>"
								name="<?php echo esc_attr( $name ); ?>"
								value="<?php echo esc_attr( $value ); ?>"
								<?php $this->link(); ?>
								<?php checked( $this->value(), $value ); ?>
							/>
							<?php if ( $src ) : ?>
								<img class="mike-image-radio__img" src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( $title ); ?>" />
							<?php endif; ?>
							<span class="mike-image-radio__label"><?php echo esc_html( $title ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<?php
		}
	}

	/* Pick many from a checkbox list. Value stored as an array. */
	class Mike_Multicheckbox_Control extends WP_Customize_Control {

		public $type = 'mike_multicheckbox';

		public function render_content() {

			if ( empty( $this->choices ) ) {
				return;
			}

			$values = (array) $this->value();
			?>
			<fieldset class="mike-multicheck">
				<?php if ( ! empty( $this->label ) ) : ?>
					<legend class="customize-control-title"><?php echo esc_html( $this->label ); ?></legend>
				<?php endif; ?>

				<?php if ( ! empty( $this->description ) ) : ?>
					<span class="description customize-control-description"><?php echo wp_kses_post( $this->description ); ?></span>
				<?php endif; ?>

				<?php foreach ( $this->choices as $value => $label ) :
					$value = (string) $value;
					?>
					<label class="mike-multicheck__item">
						<input
							type="checkbox"
							class="mike-multicheck__input"
							value="<?php echo esc_attr( $value ); ?>"
							<?php checked( in_array( $value, array_map( 'strval', $values ), true ) ); ?>
						/>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>

				<input type="hidden" class="mike-multi-value" <?php $this->link(); ?> value="<?php echo esc_attr( implode( ',', $values ) ); ?>" />
			</fieldset>
			<?php
		}
	}

	/* Pick many from a <select multiple>. Value stored as an array. */
	class Mike_Multiselect_Control extends WP_Customize_Control {

		public $type = 'mike_multiselect';

		public function render_content() {

			if ( empty( $this->choices ) ) {
				return;
			}

			$values = (array) $this->value();
			?>
			<label class="mike-multiselect">
				<?php if ( ! empty( $this->label ) ) : ?>
					<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php endif; ?>

				<?php if ( ! empty( $this->description ) ) : ?>
					<span class="description customize-control-description"><?php echo wp_kses_post( $this->description ); ?></span>
				<?php endif; ?>

				<select class="mike-multiselect__input" multiple>
					<?php foreach ( $this->choices as $value => $label ) :
						$value = (string) $value;
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( in_array( $value, array_map( 'strval', $values ), true ) ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<input type="hidden" class="mike-multi-value" <?php $this->link(); ?> value="<?php echo esc_attr( implode( ',', $values ) ); ?>" />
			</label>
			<?php
		}
	}

	class Mike_Columns_Control extends WP_Customize_Control {

		public $type = 'mike_columns';

		public function render_content() {
			// Value is a "desktop,tablet,mobile" string (e.g. "3,2,1"). Three number
			// inputs feed one hidden linked input (JS joins them), so the editor sets
			// responsive columns in one grouped control — same model as the builder's
			// Post Grid columns field.
			$raw   = (string) $this->value();
			$parts = array_map( 'intval', array_pad( explode( ',', $raw ), 3, 0 ) );
			$cols  = array(
				'desktop' => $parts[0] > 0 ? $parts[0] : 3,
				'tablet'  => $parts[1] > 0 ? $parts[1] : 2,
				'mobile'  => $parts[2] > 0 ? $parts[2] : 1,
			);
			$labels = array(
				'desktop' => esc_html__( 'Desktop', 'mike' ),
				'tablet'  => esc_html__( 'Tablet', 'mike' ),
				'mobile'  => esc_html__( 'Mobile', 'mike' ),
			);
			?>
			<span class="mike-columns">
				<?php if ( ! empty( $this->label ) ) : ?>
					<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $this->description ) ) : ?>
					<span class="description customize-control-description"><?php echo wp_kses_post( $this->description ); ?></span>
				<?php endif; ?>

				<span class="mike-columns__fields">
					<?php foreach ( $labels as $device => $label ) : ?>
						<label class="mike-columns__field">
							<span class="mike-columns__label"><?php echo esc_html( $label ); ?></span>
							<input type="number" min="1" max="6" step="1"
								class="mike-columns__input" data-device="<?php echo esc_attr( $device ); ?>"
								value="<?php echo esc_attr( $cols[ $device ] ); ?>" />
						</label>
					<?php endforeach; ?>
				</span>

				<input type="hidden" class="mike-columns-value" <?php $this->link(); ?> value="<?php echo esc_attr( $cols['desktop'] . ',' . $cols['tablet'] . ',' . $cols['mobile'] ); ?>" />
			</span>
			<?php
		}
	}

	/* Display-only: a heading / divider / note in the panel. No setting, no value —
	   just markup (from 'content', through wp_kses_post) to organize a long section. */
	class Mike_Html_Control extends WP_Customize_Control {

		public $type    = 'mike_html';
		public $content = '';

		public function render_content() {
			if ( '' === trim( (string) $this->content ) ) {
				return;
			}
			echo '<div class="mike-html-control">' . wp_kses_post( $this->content ) . '</div>';
		}
	}
}
endif;


/* -----------------------------------------------------------------------------
   Customizer control assets.
   CSS for all custom controls; a small JS shim only for the multi-value ones.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_customizer_control_assets' ) ) :
	function mike_customizer_control_assets() {
		$theme_version = mike_asset_version();
		$base          = get_template_directory_uri() . '/framework/';

		wp_enqueue_style(
			'mike-customizer-controls',
			$base . 'customizer-controls.css',
			array(),
			$theme_version
		);

		wp_enqueue_script(
			'mike-customizer-controls',
			$base . 'customizer-controls.js',
			array( 'jquery', 'customize-controls' ),
			$theme_version,
			true
		);
	}
endif;
add_action( 'customize_controls_enqueue_scripts', 'mike_customizer_control_assets' );
