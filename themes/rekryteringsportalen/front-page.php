<?php
/**
 * Front page: search, quick filters, top list, newest companies, latest jobs, reviews, job portals, CTAs.
 *
 * Shows the page content (if a static front page with content is set) below the hero.
 *
 * @package Rekryteringsportalen
 */

get_header();

if ( ! rp_theme_core_active() ) {
	echo '<div class="container"><p class="rp-empty">' . esc_html__( 'Aktivera pluginet Rekryteringsportalen Core.', 'rekryteringsportalen' ) . '</p></div>';
	get_footer();
	return;
}

$rp_company_count = (int) wp_count_posts( RP_Post_Types::COMPANY )->publish;
$rp_job_count     = (int) wp_count_posts( RP_Post_Types::JOB )->publish;
$rp_review_count  = (int) get_comments(
	array(
		'type'   => RP_Ratings::TYPE,
		'status' => 'approve',
		'count'  => true,
	)
);
$rp_services      = get_terms(
	array(
		'taxonomy'   => RP_Post_Types::TAX_SERVICE,
		'hide_empty' => true,
		'number'     => 8,
		'orderby'    => 'count',
		'order'      => 'DESC',
	)
);
$rp_newest        = get_posts(
	array(
		'post_type'      => RP_Post_Types::COMPANY,
		'posts_per_page' => 6,
		'no_found_rows'  => true,
	)
);
$rp_jobs          = get_posts(
	array(
		'post_type'      => RP_Post_Types::JOB,
		'posts_per_page' => 6,
		'no_found_rows'  => true,
	)
);
$rp_reviews       = RP_Ratings::latest( 6 );
?>
<section class="hero">
	<div class="container hero__inner">
		<h1 class="hero__title"><?php esc_html_e( 'Hitta rätt rekryteringsföretag', 'rekryteringsportalen' ); ?></h1>
		<p class="hero__lead"><?php esc_html_e( 'Jämför betyg och omdömen från kandidater och kunder. Se vilka tjänster företagen erbjuder och vilka jobb de har ute just nu.', 'rekryteringsportalen' ); ?></p>
		<form class="hero-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="hero-search"><?php esc_html_e( 'Sök rekryteringsföretag eller jobb', 'rekryteringsportalen' ); ?></label>
			<?php echo rp_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input id="hero-search" type="search" name="s" placeholder="<?php esc_attr_e( 'Sök rekryteringsföretag eller jobb', 'rekryteringsportalen' ); ?>" autocomplete="off">
			<button type="submit" class="rp-button rp-button--accent"><?php esc_html_e( 'Sök', 'rekryteringsportalen' ); ?></button>
		</form>
		<?php if ( is_array( $rp_services ) && $rp_services ) : ?>
			<ul class="hero__chips" aria-label="<?php esc_attr_e( 'Populära tjänster', 'rekryteringsportalen' ); ?>">
				<?php foreach ( $rp_services as $rp_t ) : ?>
					<li><a class="rp-chip rp-chip--light" href="<?php echo esc_url( (string) get_term_link( $rp_t ) ); ?>"><?php echo esc_html( $rp_t->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<dl class="hero__stats">
			<div><dt><?php esc_html_e( 'Rekryteringsföretag', 'rekryteringsportalen' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $rp_company_count ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Omdömen', 'rekryteringsportalen' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $rp_review_count ) ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Lediga jobb', 'rekryteringsportalen' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $rp_job_count ) ); ?></dd></div>
		</dl>
	</div>
</section>

<?php
while ( have_posts() ) :
	the_post();
	if ( '' !== trim( (string) get_the_content() ) ) :
		?>
		<section class="section"><div class="container entry-content"><?php the_content(); ?></div></section>
		<?php
	endif;
endwhile;
?>

<section class="section">
	<div class="container">
		<div class="toplists">
			<div class="toplist-card toplist-card--top">
				<div class="section-head">
					<h2><?php echo rp_icon( 'trophy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Topp 10', 'rekryteringsportalen' ); ?></h2>
					<a href="<?php echo esc_url( rp_page_url( 'topplistor' ) ); ?>"><?php esc_html_e( 'Alla topplistor →', 'rekryteringsportalen' ); ?></a>
				</div>
				<?php echo rp_toplist( 'topp', 10, '', '', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="toplist-card toplist-card--bottom">
				<div class="section-head">
					<h2><?php esc_html_e( 'Botten 10', 'rekryteringsportalen' ); ?></h2>
					<a href="<?php echo esc_url( rp_page_url( 'sa-fungerar-betygen' ) ); ?>"><?php esc_html_e( 'Så räknas betygen', 'rekryteringsportalen' ); ?></a>
				</div>
				<?php echo rp_toplist( 'botten', 10, '', '', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>
</section>

<?php if ( $rp_newest ) : ?>
	<section class="section section--surface">
		<div class="container">
			<div class="section-head">
				<h2><?php esc_html_e( 'Senast tillagda rekryteringsföretag', 'rekryteringsportalen' ); ?></h2>
				<a href="<?php echo esc_url( add_query_arg( 'sortera', 'nyast', (string) get_post_type_archive_link( RP_Post_Types::COMPANY ) ) ); ?>"><?php esc_html_e( 'Alla företag →', 'rekryteringsportalen' ); ?></a>
			</div>
			<div class="company-grid">
				<?php foreach ( $rp_newest as $rp_p ) : ?>
					<?php rp_part( 'company-card', array( 'post_id' => $rp_p->ID ) ); ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="section">
	<div class="container two-col">
		<div>
			<div class="section-head">
				<h2><?php esc_html_e( 'Senaste jobben', 'rekryteringsportalen' ); ?></h2>
				<a href="<?php echo esc_url( (string) get_post_type_archive_link( RP_Post_Types::JOB ) ); ?>"><?php esc_html_e( 'Alla jobb →', 'rekryteringsportalen' ); ?></a>
			</div>
			<?php if ( $rp_jobs ) : ?>
				<div class="job-list">
					<?php foreach ( $rp_jobs as $rp_j ) : ?>
						<?php rp_part( 'job-card', array( 'post_id' => $rp_j->ID ) ); ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="rp-empty"><?php esc_html_e( 'Inga jobb publicerade ännu.', 'rekryteringsportalen' ); ?></p>
			<?php endif; ?>
		</div>
		<div>
			<div class="section-head">
				<h2><?php esc_html_e( 'Senaste omdömena', 'rekryteringsportalen' ); ?></h2>
			</div>
			<?php if ( $rp_reviews ) : ?>
				<ul class="latest-reviews">
					<?php foreach ( $rp_reviews as $rp_r ) : ?>
						<li class="latest-review">
							<div class="latest-review__head">
								<?php echo rp_stars( (float) get_comment_meta( $rp_r->comment_ID, 'rp_betyg', true ), 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<a href="<?php echo esc_url( get_permalink( (int) $rp_r->comment_post_ID ) . '#omdome-' . $rp_r->comment_ID ); ?>"><?php echo esc_html( get_the_title( (int) $rp_r->comment_post_ID ) ); ?></a>
							</div>
							<p><?php echo esc_html( wp_trim_words( $rp_r->comment_content, 22, '…' ) ); ?></p>
							<span class="latest-review__meta"><?php echo esc_html( $rp_r->comment_author . ' · ' . human_time_diff( (int) strtotime( $rp_r->comment_date_gmt . ' UTC' ) ) . ' sedan' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="rp-empty"><?php esc_html_e( 'Inga omdömen ännu.', 'rekryteringsportalen' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="section section--surface">
	<div class="container">
		<div class="section-head">
			<h2><?php esc_html_e( 'Sök vidare på jobbportalerna', 'rekryteringsportalen' ); ?></h2>
			<a href="<?php echo esc_url( home_url( '/jobbportaler/' ) ); ?>"><?php esc_html_e( 'Alla jobbportaler →', 'rekryteringsportalen' ); ?></a>
		</div>
		<?php echo do_shortcode( '[rp_jobbportaler antal="6"]' ); ?>
	</div>
</section>

<?php
get_template_part( 'template-parts/cta-band' );
get_footer();
