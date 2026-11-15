<?php
/**
 * Search: companies and jobs in separate groups.
 *
 * @package Rekryteringsportalen
 */

get_header();
$rp_companies = array();
$rp_jobs      = array();
$rp_other     = array();
while ( have_posts() ) {
	the_post();
	$rp_type = get_post_type();
	if ( rp_theme_core_active() && RP_Post_Types::COMPANY === $rp_type ) {
		$rp_companies[] = get_the_ID();
	} elseif ( rp_theme_core_active() && RP_Post_Types::JOB === $rp_type ) {
		$rp_jobs[] = get_the_ID();
	} else {
		$rp_other[] = get_the_ID();
	}
}
?>
<div class="container">
	<header class="page-header">
		<h1 class="page-title">
			<?php
			/* translators: %s: search query. */
			printf( esc_html__( 'Sökresultat för "%s"', 'rekryteringsportalen' ), esc_html( get_search_query() ) );
			?>
		</h1>
		<form class="hero-search hero-search--inline" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="search-again"><?php esc_html_e( 'Sök igen', 'rekryteringsportalen' ); ?></label>
			<?php echo rp_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input id="search-again" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>">
			<button type="submit" class="rp-button rp-button--primary"><?php esc_html_e( 'Sök', 'rekryteringsportalen' ); ?></button>
		</form>
	</header>

	<?php if ( ! $rp_companies && ! $rp_jobs && ! $rp_other ) : ?>
		<div class="rp-empty">
			<p><?php esc_html_e( 'Inga träffar. Prova ett annat sökord eller bläddra bland alla företag.', 'rekryteringsportalen' ); ?></p>
			<p><a class="rp-button rp-button--outline" href="<?php echo esc_url( home_url( '/foretag/' ) ); ?>"><?php esc_html_e( 'Alla rekryteringsföretag', 'rekryteringsportalen' ); ?></a></p>
		</div>
	<?php endif; ?>

	<?php if ( $rp_companies ) : ?>
		<section class="section section--tight">
			<h2><?php echo esc_html( sprintf( 'Rekryteringsföretag (%d)', count( $rp_companies ) ) ); ?></h2>
			<div class="company-grid">
				<?php foreach ( $rp_companies as $rp_id ) : ?>
					<?php rp_part( 'company-card', array( 'post_id' => $rp_id ) ); ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $rp_jobs ) : ?>
		<section class="section section--tight">
			<h2><?php echo esc_html( sprintf( 'Lediga jobb (%d)', count( $rp_jobs ) ) ); ?></h2>
			<div class="job-list">
				<?php foreach ( $rp_jobs as $rp_id ) : ?>
					<?php rp_part( 'job-card', array( 'post_id' => $rp_id ) ); ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $rp_other ) : ?>
		<section class="section section--tight">
			<h2><?php esc_html_e( 'Övrigt', 'rekryteringsportalen' ); ?></h2>
			<ul>
				<?php foreach ( $rp_other as $rp_id ) : ?>
					<li><a href="<?php echo esc_url( (string) get_permalink( $rp_id ) ); ?>"><?php echo esc_html( get_the_title( $rp_id ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
	<?php rp_theme_pagination(); ?>
</div>
<?php
get_footer();
