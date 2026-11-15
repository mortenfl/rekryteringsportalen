<?php
/**
 * Footer.
 *
 * @package Rekryteringsportalen
 */

$rp_url = static function ( string $slug ): string {
	return function_exists( 'rp_page_url' ) ? rp_page_url( $slug ) : home_url( '/' . $slug . '/' );
};
?>
</main>

<footer class="site-footer">
	<div class="container site-footer__grid">
		<div class="site-footer__about">
			<p class="site-footer__brand"><?php bloginfo( 'name' ); ?></p>
			<p><?php esc_html_e( 'Jämför Sveriges rekryteringsföretag. Se betyg och omdömen från kandidater och kunder, vilka tjänster de erbjuder och vilka jobb de har ute.', 'rekryteringsportalen' ); ?></p>
			<p class="site-footer__disclaimer"><?php esc_html_e( 'Placering i topplistor och betyg kan aldrig köpas. Annonsörer märks tydligt.', 'rekryteringsportalen' ); ?></p>
		</div>
		<nav aria-label="<?php esc_attr_e( 'För kandidater', 'rekryteringsportalen' ); ?>">
			<h2 class="site-footer__title"><?php esc_html_e( 'Utforska', 'rekryteringsportalen' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/foretag/' ) ); ?>">Alla rekryteringsföretag</a></li>
				<li><a href="<?php echo esc_url( $rp_url( 'topplistor' ) ); ?>">Topp 10 och botten 10</a></li>
				<li><a href="<?php echo esc_url( home_url( '/jobb/' ) ); ?>">Lediga jobb</a></li>
				<li><a href="<?php echo esc_url( home_url( '/jobbportaler/' ) ); ?>">Jobbportaler</a></li>
			</ul>
		</nav>
		<nav aria-label="<?php esc_attr_e( 'För företag', 'rekryteringsportalen' ); ?>">
			<h2 class="site-footer__title"><?php esc_html_e( 'För företag', 'rekryteringsportalen' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( $rp_url( 'hitta-rekryterare' ) ); ?>">Hitta en rekryterare</a></li>
				<li><a href="<?php echo esc_url( $rp_url( 'for-foretag' ) ); ?>">Annonspaket för rekryteringsföretag</a></li>
			</ul>
		</nav>
		<nav aria-label="<?php esc_attr_e( 'Om webbplatsen', 'rekryteringsportalen' ); ?>">
			<h2 class="site-footer__title"><?php esc_html_e( 'Om oss', 'rekryteringsportalen' ); ?></h2>
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'depth'          => 1,
					)
				);
			} else {
				?>
				<ul>
					<li><a href="<?php echo esc_url( $rp_url( 'sa-fungerar-betygen' ) ); ?>">Så fungerar betygen</a></li>
					<li><a href="<?php echo esc_url( $rp_url( 'riktlinjer-omdomen' ) ); ?>">Riktlinjer för omdömen</a></li>
					<?php if ( get_privacy_policy_url() ) : ?>
						<li><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">Integritetspolicy</a></li>
					<?php endif; ?>
				</ul>
			<?php } ?>
		</nav>
	</div>
	<div class="container site-footer__bottom">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
