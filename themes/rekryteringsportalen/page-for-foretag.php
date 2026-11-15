<?php
/**
 * Sales page for recruitment companies: two packages, comparison, FAQ and order form.
 *
 * @package Rekryteringsportalen
 */

if ( ! rp_theme_core_active() ) {
	get_template_part( 'page' );
	return;
}

get_header();
$rp_s    = RP_Settings::all();
$rp_rows = array(
	array( 'Grundpresentation med kontaktuppgifter och tjänster', true, true, true ),
	array( 'Visas i listor, topplistor och sök', true, true, true ),
	array( 'Lediga jobb (manuellt eller via feed)', true, true, true ),
	array( 'Omdömen från kandidater och kunder', true, true, true ),
	array( 'Lång presentation med bilder och formatering', false, true, true ),
	array( 'Omslagsbild, galleri och video', false, true, true ),
	array( 'Team och kundcase', false, true, true ),
	array( 'Svara publikt på omdömen', false, true, true ),
	array( 'Kontaktformulär direkt till er', false, true, true ),
	array( 'Inga konkurrenter på er sida', false, true, true ),
	array( 'Markering som Utvald partner', false, true, true ),
	array( 'Leads från företag som ska rekrytera', false, false, true ),
);
$rp_faq  = array(
	'Kan vi köpa en bättre placering i topplistorna?' => 'Nej. Topplistorna och betygen bygger enbart på omdömen. Annonspaket ger en bättre presentation och synlighet, men påverkar aldrig betyget eller placeringen i topplistorna.',
	'Kan vi ta bort negativa omdömen?'                => 'Nej, men med ett paket kan ni svara publikt på omdömen. Omdömen som bryter mot våra riktlinjer kan rapporteras och granskas av oss.',
	'Hur fungerar leads?'                             => sprintf( 'Företag som ska rekrytera beskriver sitt behov. Förfrågan skickas till max %d rekryteringsföretag med Paket 2 som matchar bransch och region. Leads fördelas rättvist mellan partners.', (int) $rp_s['max_lead_mottagare'] ),
	'Vad är bindningstiden?'                          => 'Paketen löper månadsvis. Kontakta oss för årsavtal.',
	'Vårt företag finns inte på sajten – vad gör vi?' => 'Fyll i formuläret så lägger vi upp er och aktiverar paketet.',
);
$rp_yes  = '<span class="cmp-yes">' . rp_icon( 'check' ) . '<span class="screen-reader-text">Ingår</span></span>';
$rp_no   = '<span class="cmp-no" aria-hidden="true">–</span><span class="screen-reader-text">Ingår inte</span>';

while ( have_posts() ) :
	the_post();
	?>
	<section class="sales-hero">
		<div class="container">
			<h1 class="page-title"><?php the_title(); ?></h1>
			<div class="page-lead"><?php the_content(); ?></div>
		</div>
	</section>

	<div class="container">
		<?php echo rp_package_cards(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<section class="sales-section">
			<h2><?php esc_html_e( 'Jämför paketen', 'rekryteringsportalen' ); ?></h2>
			<div class="table-scroll">
				<table class="compare-table">
					<thead><tr><th scope="col"><?php esc_html_e( 'Funktion', 'rekryteringsportalen' ); ?></th><th scope="col"><?php esc_html_e( 'Gratis', 'rekryteringsportalen' ); ?></th><th scope="col">Paket 1</th><th scope="col">Paket 2</th></tr></thead>
					<tbody>
						<?php foreach ( $rp_rows as $rp_row ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $rp_row[0] ); ?></th>
								<?php for ( $rp_i = 1; $rp_i <= 3; $rp_i++ ) : ?>
									<td><?php echo $rp_row[ $rp_i ] ? $rp_yes : $rp_no; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<?php endfor; ?>
							</tr>
						<?php endforeach; ?>
						<tr class="compare-table__price"><th scope="row"><?php esc_html_e( 'Pris per månad', 'rekryteringsportalen' ); ?></th><td>0 kr</td><td><?php echo esc_html( $rp_s['pris_paket1'] ); ?> kr</td><td><?php echo esc_html( $rp_s['pris_paket2'] ); ?> kr</td></tr>
					</tbody>
				</table>
			</div>
		</section>

		<section class="sales-section">
			<h2><?php esc_html_e( 'Vanliga frågor', 'rekryteringsportalen' ); ?></h2>
			<div class="faq">
				<?php foreach ( $rp_faq as $rp_q => $rp_a ) : ?>
					<details class="faq__item"><summary><?php echo esc_html( $rp_q ); ?></summary><p><?php echo esc_html( $rp_a ); ?></p></details>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="sales-section sales-order">
			<h2><?php esc_html_e( 'Beställ', 'rekryteringsportalen' ); ?></h2>
			<?php echo do_shortcode( '[rp_paketformular]' ); ?>
		</section>
	</div>
	<?php
endwhile;

get_footer();
