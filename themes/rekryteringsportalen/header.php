<?php
/**
 * Header.
 *
 * @package Rekryteringsportalen
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#1E4E8C">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#innehall"><?php esc_html_e( 'Hoppa till innehållet', 'rekryteringsportalen' ); ?></a>

<header class="site-header">
	<div class="container site-header__inner">
		<div class="site-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="site-brand__name" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="site-brand__mark" aria-hidden="true">R</span>
					<span><?php bloginfo( 'name' ); ?></span>
				</a>
			<?php endif; ?>
		</div>

		<button class="site-nav__toggle" type="button" aria-expanded="false" aria-controls="huvudmeny">
			<?php echo rp_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Meny', 'rekryteringsportalen' ); ?></span>
		</button>

		<nav class="site-nav" id="huvudmeny" aria-label="<?php esc_attr_e( 'Huvudmeny', 'rekryteringsportalen' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'site-nav__list',
					'fallback_cb'    => 'rp_theme_fallback_menu',
					'depth'          => 2,
				)
			);
			?>
			<form class="site-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="screen-reader-text" for="site-search-input"><?php esc_html_e( 'Sök företag eller jobb', 'rekryteringsportalen' ); ?></label>
				<input id="site-search-input" type="search" name="s" placeholder="<?php esc_attr_e( 'Sök företag eller jobb', 'rekryteringsportalen' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
				<button type="submit" aria-label="<?php esc_attr_e( 'Sök', 'rekryteringsportalen' ); ?>"><?php echo rp_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			</form>
		</nav>
	</div>
</header>

<main id="innehall" class="site-main" tabindex="-1">
<?php
if ( function_exists( 'rp_breadcrumbs' ) && ! is_front_page() ) {
	echo '<div class="container">' . rp_breadcrumbs() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
