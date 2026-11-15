<?php
/**
 * Job portals: /jobbportaler/, grouped by category.
 *
 * @package Rekryteringsportalen
 */

get_header();
$rp_groups = array();
while ( have_posts() ) {
	the_post();
	$rp_cat = (string) get_post_meta( get_the_ID(), 'rp_kategori', true );
	$rp_groups[ '' !== $rp_cat ? $rp_cat : 'Allmän' ][] = get_the_ID();
}
$rp_order = array_flip( RP_Post_Types::portal_categories() );
uksort( $rp_groups, static fn( $a, $b ) => ( $rp_order[ $a ] ?? 99 ) <=> ( $rp_order[ $b ] ?? 99 ) );
$rp_titles = array(
	'Allmän'           => 'Stora jobbportaler',
	'Tech'             => 'Tech och ingenjörer',
	'Chef'             => 'Chefer och specialister',
	'Student'          => 'Studenter och nyexaminerade',
	'Offentlig sektor' => 'Offentlig sektor',
	'Frilans'          => 'Frilans och konsultuppdrag',
);
?>
<div class="container">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Jobbportaler', 'rekryteringsportalen' ); ?></h1>
		<p class="page-lead"><?php esc_html_e( 'Sök vidare bland miljontals annonser. Här är Sveriges största och mest användbara jobbportaler, samlade på ett ställe.', 'rekryteringsportalen' ); ?></p>
	</header>
	<?php if ( $rp_groups ) : ?>
		<nav class="rp-anchor-nav" aria-label="<?php esc_attr_e( 'Kategorier', 'rekryteringsportalen' ); ?>">
			<?php foreach ( array_keys( $rp_groups ) as $rp_cat ) : ?>
				<a class="rp-chip" href="#<?php echo esc_attr( sanitize_title( $rp_cat ) ); ?>"><?php echo esc_html( $rp_titles[ $rp_cat ] ?? $rp_cat ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php foreach ( $rp_groups as $rp_cat => $rp_ids ) : ?>
			<section class="portal-group" id="<?php echo esc_attr( sanitize_title( $rp_cat ) ); ?>">
				<h2><?php echo esc_html( $rp_titles[ $rp_cat ] ?? $rp_cat ); ?></h2>
				<ul class="rp-portals">
					<?php foreach ( $rp_ids as $rp_pid ) : ?>
						<?php echo rp_portal_card( $rp_pid ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
	<?php else : ?>
		<p class="rp-empty"><?php esc_html_e( 'Inga jobbportaler har lagts till ännu.', 'rekryteringsportalen' ); ?></p>
	<?php endif; ?>
	<p class="rp-disclaimer"><?php esc_html_e( 'Länkarna leder till externa webbplatser. Rekryteringsportalen ansvarar inte för innehållet där.', 'rekryteringsportalen' ); ?></p>
</div>
<?php
get_footer();
