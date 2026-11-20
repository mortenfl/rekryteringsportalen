<?php
/**
 * Rekryteringsportalen theme setup.
 *
 * Presentation only. All data and features live in the plugin "Rekryteringsportalen Core".
 *
 * @package Rekryteringsportalen
 */

defined( 'ABSPATH' ) || exit;

define( 'RP_THEME_VERSION', '1.1.1' );

/**
 * Whether the companion plugin is active.
 */
function rp_theme_core_active(): bool {
	return class_exists( 'RP_Post_Types' );
}

/**
 * Theme supports, menus, image sizes.
 */
function rp_theme_setup(): void {
	load_theme_textdomain( 'rekryteringsportalen', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 64,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_editor_style( 'assets/css/editor.css' );

	add_image_size( 'rp-hero', 1600, 500, true );
	add_image_size( 'rp-card', 640, 400, true );

	register_nav_menus(
		array(
			'primary' => __( 'Huvudmeny', 'rekryteringsportalen' ),
			'footer'  => __( 'Sidfot', 'rekryteringsportalen' ),
		)
	);
}
add_action( 'after_setup_theme', 'rp_theme_setup' );

/**
 * Front-end assets. No jQuery; one CSS file and one small ES module.
 */
function rp_theme_assets(): void {
	$dir = get_template_directory_uri() . '/assets';
	wp_enqueue_style( 'rp-main', $dir . '/css/main.css', array(), RP_THEME_VERSION );
	wp_enqueue_script(
		'rp-main',
		$dir . '/js/main.js',
		array(),
		RP_THEME_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_localize_script(
		'rp-main',
		'rpTheme',
		array(
			'rest' => esc_url_raw( rest_url( 'rp/v1/' ) ),
		)
	);
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'rp_theme_assets' );

/**
 * Remove block library CSS on templates that don't render block content (saves ~10 kB).
 */
function rp_theme_trim_block_css(): void {
	if ( is_post_type_archive() || is_tax() || is_search() ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'classic-theme-styles' );
	}
}
add_action( 'wp_enqueue_scripts', 'rp_theme_trim_block_css', 100 );

/**
 * Body classes.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function rp_theme_body_class( array $classes ): array {
	if ( rp_theme_core_active() && is_singular( RP_Post_Types::COMPANY ) ) {
		$classes[] = 'rp-paket-' . RP_Packages::get( (int) get_queried_object_id() );
	}
	return $classes;
}
add_filter( 'body_class', 'rp_theme_body_class' );

/**
 * Notice when the plugin is missing.
 */
function rp_theme_missing_plugin_notice(): void {
	if ( rp_theme_core_active() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Rekryteringsportalen:</strong> ' . esc_html__( 'Temat kräver pluginet "Rekryteringsportalen Core". Aktivera det under Tillägg.', 'rekryteringsportalen' ) . '</p></div>';
}
add_action( 'admin_notices', 'rp_theme_missing_plugin_notice' );

/**
 * Create and assign a default main menu on theme activation (only when no menu is assigned).
 */
function rp_theme_create_menu(): void {
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	if ( ! empty( $locations['primary'] ) ) {
		return;
	}
	$menu_id = wp_create_nav_menu( 'Huvudmeny' );
	if ( is_wp_error( $menu_id ) ) {
		$menu = wp_get_nav_menu_object( 'Huvudmeny' );
		if ( ! $menu ) {
			return;
		}
		$menu_id = $menu->term_id;
	}

	$items = array(
		array( 'Företag', home_url( '/foretag/' ) ),
		array( 'Lediga jobb', home_url( '/jobb/' ) ),
		array( 'Topplistor', function_exists( 'rp_page_url' ) ? rp_page_url( 'topplistor' ) : home_url( '/topplistor/' ) ),
		array( 'Jobbportaler', home_url( '/jobbportaler/' ) ),
		array( 'Hitta rekryterare', function_exists( 'rp_page_url' ) ? rp_page_url( 'hitta-rekryterare' ) : home_url( '/hitta-rekryterare/' ) ),
		array( 'För företag', function_exists( 'rp_page_url' ) ? rp_page_url( 'for-foretag' ) : home_url( '/for-foretag/' ) ),
	);
	if ( ! wp_get_nav_menu_items( $menu_id ) ) {
		foreach ( $items as $i => [ $title, $url ] ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'    => $title,
					'menu-item-url'      => $url,
					'menu-item-status'   => 'publish',
					'menu-item-type'     => 'custom',
					'menu-item-position' => $i + 1,
				)
			);
		}
	}
	$locations['primary'] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}
add_action( 'after_switch_theme', 'rp_theme_create_menu' );

/**
 * Fallback menu when none is assigned.
 */
function rp_theme_fallback_menu(): void {
	$links = array(
		'Företag'           => home_url( '/foretag/' ),
		'Lediga jobb'       => home_url( '/jobb/' ),
		'Topplistor'        => home_url( '/topplistor/' ),
		'Jobbportaler'      => home_url( '/jobbportaler/' ),
		'Hitta rekryterare' => home_url( '/hitta-rekryterare/' ),
		'För företag'       => home_url( '/for-foretag/' ),
	);
	echo '<ul class="site-nav__list">';
	foreach ( $links as $label => $url ) {
		echo '<li class="menu-item"><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul>';
}

/**
 * Pagination for archives.
 */
function rp_theme_pagination(): void {
	$links = paginate_links(
		array(
			'type'      => 'list',
			'prev_text' => '← Föregående',
			'next_text' => 'Nästa →',
			'mid_size'  => 1,
		)
	);
	if ( $links ) {
		echo '<nav class="rp-pagination" aria-label="Sidnumrering">' . wp_kses_post( $links ) . '</nav>';
	}
}

/**
 * Inline SVG icon.
 *
 * @param string $name Icon name.
 */
function rp_icon( string $name ): string {
	$paths = array(
		'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'pin'       => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>',
		'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'phone'     => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
		'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'external'  => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		'check'     => '<path d="m5 12 5 5L20 7"/>',
		'menu'      => '<path d="M4 6h16M4 12h16M4 18h16"/>',
		'filter'    => '<path d="M4 5h16l-6 8v6l-4-2v-4z"/>',
		'trophy'    => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4"/>',
		'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10v7M8 7v.01M12 17v-4a2 2 0 0 1 4 0v4M12 10v7"/>',
		'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="rp-icon rp-icon--' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Get a template part and pass arguments.
 *
 * @param string               $slug Slug inside template-parts/.
 * @param array<string, mixed> $args Arguments.
 */
function rp_part( string $slug, array $args = array() ): void {
	get_template_part( 'template-parts/' . $slug, null, $args );
}

/**
 * Archive title without the "Arkiv:" prefix.
 *
 * @param string $title Title.
 */
function rp_theme_archive_title( string $title ): string {
	if ( is_post_type_archive() ) {
		return post_type_archive_title( '', false );
	}
	if ( is_tax() || is_category() || is_tag() ) {
		return single_term_title( '', false );
	}
	return $title;
}
add_filter( 'get_the_archive_title', 'rp_theme_archive_title' );
