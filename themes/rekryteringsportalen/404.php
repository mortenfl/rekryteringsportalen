<?php
/**
 * 404.
 *
 * @package Rekryteringsportalen
 */

get_header();
?>
<div class="container container--narrow not-found">
	<p class="not-found__code" aria-hidden="true">404</p>
	<h1 class="page-title"><?php esc_html_e( 'Sidan kunde inte hittas', 'rekryteringsportalen' ); ?></h1>
	<p><?php esc_html_e( 'Sidan kan ha flyttats eller tagits bort. Sök efter ett rekryteringsföretag eller ett jobb:', 'rekryteringsportalen' ); ?></p>
	<form class="hero-search hero-search--inline" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label class="screen-reader-text" for="nf-search"><?php esc_html_e( 'Sök', 'rekryteringsportalen' ); ?></label>
		<?php echo rp_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<input id="nf-search" type="search" name="s">
		<button type="submit" class="rp-button rp-button--primary"><?php esc_html_e( 'Sök', 'rekryteringsportalen' ); ?></button>
	</form>
	<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '← Till startsidan', 'rekryteringsportalen' ); ?></a></p>
</div>
<?php
get_footer();
