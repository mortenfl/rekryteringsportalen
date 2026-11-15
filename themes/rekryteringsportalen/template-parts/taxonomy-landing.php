<?php
/**
 * Landing page for a service, industry or region: intro, top 3 and filtered list.
 *
 * @package Rekryteringsportalen
 */

$rp_term = get_queried_object();
if ( ! $rp_term instanceof WP_Term ) {
	return;
}
$rp_intro = array(
	RP_Post_Types::TAX_SERVICE  => 'Rekryteringsföretag som erbjuder %s',
	RP_Post_Types::TAX_INDUSTRY => 'Rekryteringsföretag inom %s',
	RP_Post_Types::TAX_REGION   => 'Rekryteringsföretag i %s',
);
?>
<div class="container">
	<header class="page-header">
		<h1 class="page-title"><?php echo esc_html( sprintf( $rp_intro[ $rp_term->taxonomy ] ?? '%s', $rp_term->name ) ); ?></h1>
		<?php if ( $rp_term->description ) : ?>
			<div class="page-lead"><?php echo wp_kses_post( wpautop( $rp_term->description ) ); ?></div>
		<?php endif; ?>
		<p><a class="rp-link-arrow" href="<?php echo esc_url( RP_Query::toplist_url( $rp_term->taxonomy, $rp_term->slug ) ); ?>"><?php echo rp_icon( 'trophy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( sprintf( 'Topp 10 – %s', $rp_term->name ) ); ?></a></p>
	</header>

	<div class="layout-sidebar">
		<aside class="layout-sidebar__aside" aria-label="<?php esc_attr_e( 'Filter', 'rekryteringsportalen' ); ?>">
			<?php
			rp_part(
				'company-filters',
				array(
					'action' => (string) get_term_link( $rp_term ),
					'lock'   => $rp_term->taxonomy,
				)
			);
			?>
		</aside>
		<div class="layout-sidebar__main">
			<?php rp_part( 'company-results' ); ?>
		</div>
	</div>
</div>
