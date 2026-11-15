<?php
/**
 * Two call-to-action cards: companies that need to recruit / recruitment companies.
 *
 * @package Rekryteringsportalen
 */

?>
<section class="cta-band" aria-label="<?php esc_attr_e( 'Kom igång', 'rekryteringsportalen' ); ?>">
	<div class="container cta-band__grid">
		<div class="cta-card">
			<h2 class="cta-card__title"><?php esc_html_e( 'Ska ni rekrytera?', 'rekryteringsportalen' ); ?></h2>
			<p><?php esc_html_e( 'Beskriv behovet så får ni förslag från upp till tre rekryteringsföretag som är specialiserade på er bransch och region. Kostnadsfritt.', 'rekryteringsportalen' ); ?></p>
			<a class="rp-button rp-button--primary" href="<?php echo esc_url( rp_page_url( 'hitta-rekryterare' ) ); ?>"><?php esc_html_e( 'Hitta rekryterare', 'rekryteringsportalen' ); ?></a>
		</div>
		<div class="cta-card cta-card--alt">
			<h2 class="cta-card__title"><?php esc_html_e( 'Är ni ett rekryteringsföretag?', 'rekryteringsportalen' ); ?></h2>
			<p><?php esc_html_e( 'Syns där kandidater och kunder jämför. Få en utökad presentation eller ta emot förfrågningar från företag som ska rekrytera.', 'rekryteringsportalen' ); ?></p>
			<a class="rp-button rp-button--outline-light" href="<?php echo esc_url( rp_page_url( 'for-foretag' ) ); ?>"><?php esc_html_e( 'Se annonspaketen', 'rekryteringsportalen' ); ?></a>
		</div>
	</div>
</section>
