<?php
/**
 * Job card.
 *
 * @package Rekryteringsportalen
 *
 * @var array{post_id?:int, show_company?:bool} $args
 */

$rp_id       = (int) ( $args['post_id'] ?? get_the_ID() );
$rp_company  = (int) get_post_meta( $rp_id, 'rp_foretag_id', true );
$rp_city     = (string) get_post_meta( $rp_id, 'rp_ort', true );
$rp_type     = (string) get_post_meta( $rp_id, 'rp_anstallningsform', true );
$rp_deadline = (string) get_post_meta( $rp_id, 'rp_sista_ansokningsdag', true );
$rp_show     = $args['show_company'] ?? true;
?>
<article class="job-card">
	<?php if ( $rp_show && $rp_company ) : ?>
		<a class="job-card__logo" href="<?php echo esc_url( (string) get_permalink( $rp_company ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo rp_company_logo( $rp_company ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
	<?php endif; ?>
	<div class="job-card__body">
		<h3 class="job-card__title"><a href="<?php echo esc_url( (string) get_permalink( $rp_id ) ); ?>"><?php echo esc_html( get_the_title( $rp_id ) ); ?></a></h3>
		<p class="job-card__meta">
			<?php if ( $rp_show && $rp_company ) : ?>
				<a href="<?php echo esc_url( (string) get_permalink( $rp_company ) ); ?>"><?php echo esc_html( get_the_title( $rp_company ) ); ?></a>
			<?php endif; ?>
			<?php if ( $rp_city ) : ?>
				<span><?php echo rp_icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $rp_city ); ?></span>
			<?php endif; ?>
			<?php if ( $rp_type ) : ?>
				<span class="rp-chip rp-chip--small"><?php echo esc_html( $rp_type ); ?></span>
			<?php endif; ?>
		</p>
	</div>
	<?php if ( $rp_deadline ) : ?>
		<p class="job-card__deadline"><?php echo rp_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span><?php esc_html_e( 'Sista dag', 'rekryteringsportalen' ); ?><br><time datetime="<?php echo esc_attr( $rp_deadline ); ?>"><?php echo esc_html( date_i18n( 'j M', strtotime( $rp_deadline ) ) ); ?></time></span>
		</p>
	<?php endif; ?>
</article>
