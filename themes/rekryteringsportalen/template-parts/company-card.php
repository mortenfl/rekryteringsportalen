<?php
/**
 * Company card.
 *
 * @package Rekryteringsportalen
 *
 * @var array{post_id?:int} $args
 */

$rp_id    = (int) ( $args['post_id'] ?? get_the_ID() );
$rp_city  = (string) get_post_meta( $rp_id, 'rp_ort', true );
$rp_short = (string) get_post_meta( $rp_id, 'rp_kort_beskrivning', true );
$rp_jobs  = (int) get_post_meta( $rp_id, 'rp_antal_jobb', true );
$rp_paid  = RP_Packages::NONE !== RP_Packages::get( $rp_id );
?>
<article class="company-card<?php echo $rp_paid ? ' company-card--featured' : ''; ?>">
	<header class="company-card__header">
		<?php echo rp_company_logo( $rp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="company-card__titles">
			<h3 class="company-card__name"><a href="<?php echo esc_url( (string) get_permalink( $rp_id ) ); ?>"><?php echo esc_html( get_the_title( $rp_id ) ); ?></a></h3>
			<?php if ( $rp_city ) : ?>
				<p class="company-card__city"><?php echo rp_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $rp_city ); ?></p>
			<?php endif; ?>
		</div>
	</header>
	<?php echo rp_sponsored_label( $rp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="company-card__rating"><?php echo rp_rating_badge( $rp_id, 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<?php if ( $rp_short ) : ?>
		<p class="company-card__desc"><?php echo esc_html( wp_trim_words( $rp_short, 24, '…' ) ); ?></p>
	<?php endif; ?>
	<?php echo rp_term_list( $rp_id, RP_Post_Types::TAX_SERVICE, 3 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<footer class="company-card__footer">
		<span class="company-card__jobs"><?php echo rp_icon( 'briefcase' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			/* translators: %d: number of jobs. */
			echo esc_html( $rp_jobs ? sprintf( _n( '%d ledigt jobb', '%d lediga jobb', $rp_jobs, 'rekryteringsportalen' ), $rp_jobs ) : __( 'Inga lediga jobb', 'rekryteringsportalen' ) );
			?>
		</span>
		<a class="company-card__more" href="<?php echo esc_url( (string) get_permalink( $rp_id ) ); ?>" aria-hidden="true" tabindex="-1"><?php esc_html_e( 'Läs mer →', 'rekryteringsportalen' ); ?></a>
	</footer>
</article>
