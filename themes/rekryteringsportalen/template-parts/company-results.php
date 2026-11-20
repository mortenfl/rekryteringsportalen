<?php
/**
 * Company results (main query). Wrapped in #rp-results so live filtering can swap it.
 *
 * @package Rekryteringsportalen
 */

global $wp_query;
$rp_total = (int) $wp_query->found_posts;
?>
<div id="rp-results" class="rp-results" aria-live="polite" aria-busy="false">
	<p class="rp-results__count">
		<?php
		/* translators: %d: number of companies. */
		echo esc_html( sprintf( _n( '%d rekryteringsföretag', '%d rekryteringsföretag', $rp_total, 'rekryteringsportalen' ), $rp_total ) );
		?>
	</p>
	<?php if ( have_posts() ) : ?>
		<div class="company-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				rp_part( 'company-card', array( 'post_id' => get_the_ID() ) );
			endwhile;
			?>
		</div>
		<?php rp_theme_pagination(); ?>
	<?php else : ?>
		<div class="rp-empty">
			<p><?php esc_html_e( 'Inga rekryteringsföretag matchar dina filter.', 'rekryteringsportalen' ); ?></p>
			<p><a href="<?php echo esc_url( (string) get_post_type_archive_link( RP_Post_Types::COMPANY ) ); ?>"><?php esc_html_e( 'Visa alla företag', 'rekryteringsportalen' ); ?></a> · <a href="<?php echo esc_url( rp_page_url( 'lagg-till-foretag' ) ); ?>"><?php esc_html_e( 'Saknas ett företag? Lägg till det gratis', 'rekryteringsportalen' ); ?></a></p>
		</div>
	<?php endif; ?>
</div>
