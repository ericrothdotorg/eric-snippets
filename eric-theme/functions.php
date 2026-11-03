<?php
/**
 * Eric — theme functions.
 *
 * Prefix: er_theme_ (site convention er_, plus "theme" so nothing collides
 * with er_ functions in the snippets). Style handles: eric, eric-block-*.
 *
 * @package eric
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/**
 * 1. Theme setup.
 * - Editor gets the same style.css as the front end.
 * - Core block patterns stay removed.
 */
function er_theme_setup() {
	add_editor_style( 'style.css' );
	remove_theme_support( 'core-block-patterns' );
}
add_action( 'after_setup_theme', 'er_theme_setup' );

/**
 * 2. Front-end stylesheet.
 * One file: Part A (base) + Part B (site). filemtime() busts the cache on every change.
 */
function er_theme_enqueue_styles() {
	$path = get_theme_file_path( 'style.css' );
	wp_enqueue_style(
		'eric',
		get_theme_file_uri( 'style.css' ),
		array(),
		file_exists( $path ) ? filemtime( $path ) : null
	);
}
add_action( 'wp_enqueue_scripts', 'er_theme_enqueue_styles' );

/**
 * 3. Per-block CSS from assets/styles.
 * core-image.css → loads only on pages that contain core/image, and so on.
 */
function er_theme_enqueue_block_styles() {
	foreach ( (array) glob( get_theme_file_path( 'assets/styles/*.css' ) ) as $file ) {
		$filename   = basename( $file, '.css' );
		$block_name = str_replace( 'core-', 'core/', $filename );

		wp_enqueue_block_style(
			$block_name,
			array(
				'handle' => "eric-block-{$filename}",
				'src'    => get_theme_file_uri( "assets/styles/{$filename}.css" ),
				'path'   => get_theme_file_path( "assets/styles/{$filename}.css" ),
			)
		);
	}
}
add_action( 'init', 'er_theme_enqueue_block_styles' );

/**
 * 4. Block styles in the editor's style picker.
 * Only the two used in content (rounded-full: This Site, My IQ;
 * separator-thin: AUM (OM) + search template). CSS is in assets/styles.
 */
function er_theme_register_block_styles() {
	register_block_style(
		'core/image',
		array(
			'name'  => 'rounded-full',
			'label' => __( 'Rounded Full', 'eric' ),
		)
	);
	register_block_style(
		'core/separator',
		array(
			'name'  => 'separator-thin',
			'label' => __( 'Thin', 'eric' ),
		)
	);
}
add_action( 'init', 'er_theme_register_block_styles' );

/**
 * 5. Excerpt box for Pages.
 */
function er_theme_enable_page_excerpts() {
	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'init', 'er_theme_enable_page_excerpts' );

/**
 * 6. "Sidebar" template-part area (used by parts/sidebar.html).
 */
function er_theme_template_part_areas( array $areas ) {
	$areas[] = array(
		'area'        => 'sidebar',
		'area_tag'    => 'section',
		'label'       => __( 'Sidebar', 'eric' ),
		'description' => __( 'The Sidebar template defines a page area that can be found on the Page (With Sidebar) template.', 'eric' ),
		'icon'        => 'sidebar',
	);
	return $areas;
}
add_filter( 'default_wp_template_part_areas', 'er_theme_template_part_areas' );

/**
 * 7. Mobile menu submenus — everything for the mobile overlay menu lives here.
 *
 * Two separate problems:
 * a) WP 7.1 bug: while the mobile overlay is open, core binds every submenu toggle's
 *    aria-expanded to state.isSubmenuOpen, which is true for all of them. The accordion
 *    rule in style.css §10 (navigation base) keys on that attribute, so every submenu
 *    expanded at once. The PHP below rebinds to state.isMenuOpen (per submenu, as before
 *    7.1); screen readers then hear each submenu's own state. Does nothing once core
 *    stops emitting state.isSubmenuOpen.
 * b) Core design: an open submenu closes as soon as focus moves elsewhere. In the stacked
 *    overlay list that makes the list jump and the next tap lands on nothing. The CSS + JS
 *    below ignore core's state inside the overlay and toggle each submenu independently
 *    via .er-submenu-open (tap to open, tap again to close; several can be open).
 *
 * The template is rendered before wp_head(), so the filter only marks that a navigation
 * block was rendered; the CSS + JS are added afterwards in wp_enqueue_scripts, and only
 * then. Enqueueing from inside the render filter would pull style.css ahead of the global
 * styles (theme.json), and every :root :where() rule there would then override style.css.
 * The CSS is appended to style.css itself ('eric' inline), so it stays last in the theme CSS.
 */
function er_theme_nav_rendered( $set = false ) {
	static $rendered = false;
	if ( $set ) {
		$rendered = true;
	}
	return $rendered;
}

function er_theme_mobile_submenus( $block_content ) {
	// a) Per-submenu aria-expanded.
	$tags = new WP_HTML_Tag_Processor( $block_content );
	while ( $tags->next_tag(
		array(
			'tag_name'   => 'BUTTON',
			'class_name' => 'wp-block-navigation-submenu__toggle',
		)
	) ) {
		if ( 'state.isSubmenuOpen' === $tags->get_attribute( 'data-wp-bind--aria-expanded' ) ) {
			$tags->set_attribute( 'data-wp-bind--aria-expanded', 'state.isMenuOpen' );
		}
	}

	er_theme_nav_rendered( true );
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/navigation', 'er_theme_mobile_submenus' );

// b) Independent open/close inside the overlay (≤ 1050px = overlay breakpoint).
function er_theme_mobile_submenus_assets() {
	if ( ! er_theme_nav_rendered() ) {
		return;
	}

	wp_add_inline_style(
		'eric',
		'@media (max-width: 1050px) {'
		. '.er-header .is-menu-open .wp-block-navigation-submenu__toggle[aria-expanded="true"] ~ .wp-block-navigation__submenu-container {display: none;}'
		. '.er-header .is-menu-open .wp-block-navigation-item.er-submenu-open > .wp-block-navigation__submenu-container {display: flex;}'
		. '}'
	);

	wp_register_script( 'eric-mobile-submenus', false, array(), null, array( 'in_footer' => true ) );
	wp_enqueue_script( 'eric-mobile-submenus' );
	wp_add_inline_script(
		'eric-mobile-submenus',
		"document.addEventListener('click', function (e) {"
		. "var toggle = e.target.closest('.wp-block-navigation-submenu__toggle'); if (!toggle) return;"
		. "var overlay = toggle.closest('.wp-block-navigation__responsive-container');"
		. "if (!overlay || !overlay.classList.contains('is-menu-open')) return;"
		. "var item = toggle.closest('.wp-block-navigation-item'); if (item) item.classList.toggle('er-submenu-open');"
		. '});'
	);
}
add_action( 'wp_enqueue_scripts', 'er_theme_mobile_submenus_assets', 20 );

/**
 * 8. WordPress default presets.
 * Core prints its own default colours, gradients, duotones and shadows as CSS on every
 * page, even though theme.json hides them in the editor (defaultPalette / defaultGradients /
 * defaultDuotone / shadow.defaultPresets: false). Keep only the defaults that content
 * still uses; everything else is dropped from the global styles.
 * Kept: black, cyan-bluish-gray, luminous-vivid-orange (colour classes in posts) and the
 * "natural" shadow (box-shadow in posts). Values = WordPress core defaults.
 */
function er_theme_trim_default_presets( $theme_json ) {
	return $theme_json->update_with(
		array(
			'version'  => 3,
			'settings' => array(
				'color'  => array(
					'palette'   => array(
						array( 'name' => 'Black', 'slug' => 'black', 'color' => '#000000' ),
						array( 'name' => 'Cyan bluish gray', 'slug' => 'cyan-bluish-gray', 'color' => '#abb8c3' ),
						array( 'name' => 'Luminous vivid orange', 'slug' => 'luminous-vivid-orange', 'color' => '#ff6900' ),
					),
					'gradients' => array(),
					'duotone'   => array(),
				),
				'shadow' => array(
					'presets' => array(
						array( 'name' => 'Natural', 'slug' => 'natural', 'shadow' => '6px 6px 9px rgba(0, 0, 0, 0.2)' ),
					),
				),
			),
		)
	);
}
add_filter( 'wp_theme_json_data_default', 'er_theme_trim_default_presets' );
