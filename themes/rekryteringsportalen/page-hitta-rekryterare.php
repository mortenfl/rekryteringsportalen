<?php
/**
 * Lead form for companies that need to recruit.
 *
 * @package Rekryteringsportalen
 */

if ( ! rp_theme_core_active() ) {
	get_template_part( 'page' );
	return;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="container layout-sidebar layout-sidebar--right">
		<div class="layout-sidebar__main">
			<header class="page-header">
				<h1 class="page-title"><?php esc_html_e( 'Ska ni rekrytera? Få förslag från rätt rekryteringsföretag', 'rekryteringsportalen' ); ?></h1>
				<div class="page-lead"><?php the_content(); ?></div>
			</header>
			<div class="form-card">
				<?php echo do_shortcode( '[rp_lead_formular]' ); ?>
			</div>
		</div>
		<aside class="layout-sidebar__aside">
			<div class="side-card">
				<h2 class="side-card__title"><?php esc_html_e( 'Så funkar det', 'rekryteringsportalen' ); ?></h2>
				<ol class="steps">
					<li><strong><?php esc_html_e( 'Beskriv behovet', 'rekryteringsportalen' ); ?></strong><span><?php esc_html_e( 'Roll, bransch och region tar en minut att fylla i.', 'rekryteringsportalen' ); ?></span></li>
					<li><strong><?php esc_html_e( 'Vi matchar', 'rekryteringsportalen' ); ?></strong><span><?php echo esc_html( sprintf( 'Förfrågan skickas till max %d specialiserade rekryteringsföretag.', (int) RP_Settings::get( 'max_lead_mottagare' ) ) ); ?></span></li>
					<li><strong><?php esc_html_e( 'Jämför och välj', 'rekryteringsportalen' ); ?></strong><span><?php esc_html_e( 'Ni blir kontaktade, jämför betygen här och väljer fritt.', 'rekryteringsportalen' ); ?></span></li>
				</ol>
			</div>
			<div class="side-card">
				<h2 class="side-card__title"><?php esc_html_e( 'Högst betyg just nu', 'rekryteringsportalen' ); ?></h2>
				<?php echo rp_toplist( 'topp', 5, '', '', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</aside>
	</div>
	<?php
endwhile;

get_footer();
