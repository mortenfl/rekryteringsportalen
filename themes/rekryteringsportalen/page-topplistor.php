<?php
/**
 * Top 10 and bottom 10. Filterable by service, industry or region via /topplistor/{taxonomy}/{term}/.
 *
 * @package Rekryteringsportalen
 */

if ( ! rp_theme_core_active() ) {
	get_template_part( 'page' );
	return;
}

get_header();

$rp_tax  = sanitize_key( (string) get_query_var( 'rp_lista_tax' ) );
$rp_slug = sanitize_title( (string) get_query_var( 'rp_lista_term' ) );
$rp_term = ( $rp_tax && $rp_slug ) ? get_term_by( 'slug', $rp_slug, $rp_tax ) : false;
if ( ! $rp_term ) {
	$rp_tax  = '';
	$rp_slug = '';
}
$rp_suffix = $rp_term ? ' – ' . $rp_term->name : '';
$rp_taxes  = array(
	RP_Post_Types::TAX_SERVICE  => 'Tjänst',
	RP_Post_Types::TAX_INDUSTRY => 'Bransch',
	RP_Post_Types::TAX_REGION   => 'Region',
);

while ( have_posts() ) :
	the_post();
	?>
	<div class="container">
		<header class="page-header">
			<h1 class="page-title"><?php echo esc_html( get_the_title() . $rp_suffix ); ?></h1>
			<div class="page-lead"><?php the_content(); ?></div>
		</header>

		<nav class="toplist-filter" aria-label="<?php esc_attr_e( 'Filtrera topplistor', 'rekryteringsportalen' ); ?>">
			<a class="rp-chip<?php echo $rp_term ? '' : ' is-active'; ?>" href="<?php echo esc_url( RP_Query::toplist_url() ); ?>"<?php echo $rp_term ? '' : ' aria-current="page"'; ?>><?php esc_html_e( 'Alla', 'rekryteringsportalen' ); ?></a>
			<?php foreach ( $rp_taxes as $rp_t => $rp_label ) : ?>
				<?php
				$rp_terms = get_terms(
					array(
						'taxonomy'   => $rp_t,
						'hide_empty' => true,
						'parent'     => RP_Post_Types::TAX_REGION === $rp_t ? '' : 0,
					)
				);
				?>
				<?php if ( is_array( $rp_terms ) && $rp_terms ) : ?>
					<label class="screen-reader-text" for="rp-tl-<?php echo esc_attr( $rp_t ); ?>"><?php echo esc_html( $rp_label ); ?></label>
					<select id="rp-tl-<?php echo esc_attr( $rp_t ); ?>" class="toplist-filter__select" data-rp-navigate>
						<option value=""><?php echo esc_html( $rp_label ); ?></option>
						<?php foreach ( $rp_terms as $rp_x ) : ?>
							<option value="<?php echo esc_url( RP_Query::toplist_url( $rp_t, $rp_x->slug ) ); ?>" <?php selected( $rp_term && $rp_term->term_id === $rp_x->term_id ); ?>><?php echo esc_html( $rp_x->name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			<?php endforeach; ?>
			<noscript><p><?php esc_html_e( 'Välj kategori via länkarna på respektive tjänst-, bransch- eller regionsida.', 'rekryteringsportalen' ); ?></p></noscript>
		</nav>

		<section class="toplist-card">
			<h2><?php echo rp_icon( 'trophy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Topp 10', 'rekryteringsportalen' ); ?></h2>
			<p class="toplist-card__sub"><?php esc_html_e( 'Högst viktat betyg', 'rekryteringsportalen' ); ?></p>
			<?php echo rp_toplist( 10, $rp_tax, $rp_slug, false, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>

		<aside class="rp-info">
			<h2><?php esc_html_e( 'Så räknas listan', 'rekryteringsportalen' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						'Bara företag med minst %d godkända omdömen kommer med. Vi använder ett viktat (Bayesianskt) betyg så att ett företag med ett enda 5-stjärnigt omdöme inte hamnar före ett företag med hundra omdömen och snittet 4,8. Annonsörer får ingen fördel i listorna.',
						(int) RP_Settings::get( 'min_omdomen' )
					)
				);
				?>
				<a href="<?php echo esc_url( rp_page_url( 'sa-fungerar-betygen' ) ); ?>"><?php esc_html_e( 'Läs mer om betygen', 'rekryteringsportalen' ); ?></a>
			</p>
		</aside>
	</div>
	<?php
endwhile;

get_footer();
