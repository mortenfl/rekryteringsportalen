<?php
/**
 * Single job.
 *
 * @package Rekryteringsportalen
 */

get_header();

while ( have_posts() ) :
	the_post();
	$rp_id       = get_the_ID();
	$rp_company  = (int) get_post_meta( $rp_id, 'rp_foretag_id', true );
	$rp_apply    = (string) get_post_meta( $rp_id, 'rp_ansok_url', true );
	$rp_deadline = (string) get_post_meta( $rp_id, 'rp_sista_ansokningsdag', true );
	$rp_meta     = array_filter(
		array(
			'Ort'                 => (string) get_post_meta( $rp_id, 'rp_ort', true ),
			'Anställningsform'    => (string) get_post_meta( $rp_id, 'rp_anstallningsform', true ),
			'Sista ansökningsdag' => $rp_deadline ? date_i18n( 'j F Y', strtotime( $rp_deadline ) ) : '',
			'Publicerad'          => get_the_date( 'j F Y' ),
		)
	);
	?>
	<div class="container layout-sidebar layout-sidebar--right">
		<article <?php post_class( 'layout-sidebar__main job' ); ?>>
			<header class="page-header">
				<h1 class="page-title"><?php the_title(); ?></h1>
				<?php if ( $rp_company ) : ?>
					<p class="job__company"><a href="<?php echo esc_url( (string) get_permalink( $rp_company ) ); ?>"><?php echo esc_html( get_the_title( $rp_company ) ); ?></a></p>
				<?php endif; ?>
				<?php echo rp_term_list( $rp_id, RP_Post_Types::TAX_INDUSTRY ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</header>
			<div class="entry-content"><?php the_content(); ?></div>
			<?php if ( $rp_apply ) : ?>
				<p><a class="rp-button rp-button--primary rp-button--large" href="<?php echo esc_url( $rp_apply ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ansök hos rekryteringsföretaget', 'rekryteringsportalen' ); ?> <?php echo rp_icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></p>
			<?php endif; ?>
		</article>

		<aside class="layout-sidebar__aside">
			<div class="side-card">
				<h2 class="side-card__title"><?php esc_html_e( 'Om tjänsten', 'rekryteringsportalen' ); ?></h2>
				<dl class="facts">
					<?php foreach ( $rp_meta as $rp_label => $rp_value ) : ?>
						<div class="facts__row"><dt><?php echo esc_html( $rp_label ); ?></dt><dd><?php echo esc_html( $rp_value ); ?></dd></div>
					<?php endforeach; ?>
				</dl>
			</div>
			<?php if ( $rp_company ) : ?>
				<div class="side-card">
					<h2 class="side-card__title"><?php esc_html_e( 'Rekryteringsföretaget', 'rekryteringsportalen' ); ?></h2>
					<div class="mini-company">
						<?php echo rp_company_logo( $rp_company ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div>
							<a href="<?php echo esc_url( (string) get_permalink( $rp_company ) ); ?>"><strong><?php echo esc_html( get_the_title( $rp_company ) ); ?></strong></a>
							<?php echo rp_rating_badge( $rp_company, 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
					<p><a class="rp-link-arrow" href="<?php echo esc_url( get_permalink( $rp_company ) . '#omdomen' ); ?>"><?php esc_html_e( 'Läs omdömen om företaget →', 'rekryteringsportalen' ); ?></a></p>
				</div>
			<?php endif; ?>
		</aside>
	</div>
	<?php
endwhile;

get_footer();
