<?php
/**
 * Company presentation. Layout depends on the package:
 * - Inget paket: basic facts, jobs, reviews, similar companies, "claim this page" box.
 * - Paket 1/2:   + cover image, long description, gallery, video, team, customer cases,
 *                  replies to reviews, contact form, no competitors on the page.
 *
 * @package Rekryteringsportalen
 */

get_header();

while ( have_posts() ) :
	the_post();
	$rp_id       = get_the_ID();
	$rp_extended = RP_Packages::has_extended( $rp_id );
	$rp_site     = (string) get_post_meta( $rp_id, 'rp_webbplats', true );
	$rp_hero     = $rp_extended ? (int) get_post_meta( $rp_id, 'rp_hero', true ) : 0;
	$rp_jobs     = RP_Jobs::for_company( $rp_id, 20 );
	$rp_short    = (string) get_post_meta( $rp_id, 'rp_kort_beskrivning', true );
	$rp_career   = (string) get_post_meta( $rp_id, 'rp_karriarsida_url', true );
	$rp_facts    = array_filter(
		array(
			'Ort'       => array( 'pin', (string) get_post_meta( $rp_id, 'rp_ort', true ) ),
			'Grundat'   => array( 'calendar', (string) get_post_meta( $rp_id, 'rp_grundat', true ) ),
			'Anställda' => array( 'users', (string) get_post_meta( $rp_id, 'rp_antal_anstallda', true ) ),
			'Org.nr'    => array( 'briefcase', (string) get_post_meta( $rp_id, 'rp_orgnr', true ) ),
			'Telefon'   => array( 'phone', (string) get_post_meta( $rp_id, 'rp_telefon', true ) ),
			'E-post'    => array( 'mail', (string) get_post_meta( $rp_id, 'rp_epost', true ) ),
		),
		static fn( $f ) => '' !== $f[1]
	);
	?>
	<article <?php post_class( 'company' . ( $rp_extended ? ' company--extended' : '' ) ); ?>>

		<?php if ( $rp_hero ) : ?>
			<div class="company-hero">
				<?php
				echo wp_get_attachment_image(
					$rp_hero,
					'rp-hero',
					false,
					array(
						'class'         => 'company-hero__img',
						'alt'           => '',
						'fetchpriority' => 'high',
					)
				);
				?>
			</div>
		<?php endif; ?>

		<header class="company-header<?php echo $rp_hero ? ' company-header--overlap' : ''; ?>">
			<div class="container company-header__inner">
				<div class="company-header__logo"><?php echo rp_company_logo( $rp_id, 'medium' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div class="company-header__main">
					<?php echo rp_sponsored_label( $rp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<h1 class="company-header__title"><?php the_title(); ?></h1>
					<a class="company-header__rating" href="#omdomen"><?php echo rp_rating_badge( $rp_id, 'md' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					<?php echo rp_term_list( $rp_id, RP_Post_Types::TAX_SERVICE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="company-header__actions">
					<a class="rp-button rp-button--primary" href="#betygsatt"><?php esc_html_e( 'Betygsätt', 'rekryteringsportalen' ); ?></a>
					<?php if ( $rp_site ) : ?>
						<a class="rp-button rp-button--outline" href="<?php echo esc_url( $rp_site ); ?>" target="_blank" rel="noopener<?php echo $rp_extended ? '' : ' nofollow'; ?>"><?php echo rp_icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Webbplats', 'rekryteringsportalen' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</header>

		<nav class="company-tabs" aria-label="<?php esc_attr_e( 'Innehåll på sidan', 'rekryteringsportalen' ); ?>">
			<div class="container">
				<ul>
					<li><a href="#om"><?php esc_html_e( 'Om företaget', 'rekryteringsportalen' ); ?></a></li>
					<li><a href="#jobb"><?php esc_html_e( 'Lediga jobb', 'rekryteringsportalen' ); ?> <span class="rp-count"><?php echo (int) count( $rp_jobs ); ?></span></a></li>
					<li><a href="#omdomen"><?php esc_html_e( 'Omdömen', 'rekryteringsportalen' ); ?> <span class="rp-count"><?php echo (int) get_post_meta( $rp_id, 'rp_antal_betyg', true ); ?></span></a></li>
					<?php if ( $rp_extended ) : ?>
						<li><a href="#kontakt"><?php esc_html_e( 'Kontakt', 'rekryteringsportalen' ); ?></a></li>
					<?php endif; ?>
				</ul>
			</div>
		</nav>

		<div class="container layout-sidebar layout-sidebar--right">
			<div class="layout-sidebar__main">

				<section id="om" class="company-section">
					<h2><?php echo esc_html( sprintf( 'Om %s', get_the_title() ) ); ?></h2>
					<?php if ( $rp_extended && '' !== trim( get_the_content() ) ) : ?>
						<div class="entry-content"><?php the_content(); ?></div>
					<?php elseif ( $rp_short ) : ?>
						<p class="company-lead"><?php echo esc_html( $rp_short ); ?></p>
					<?php else : ?>
						<p class="rp-empty"><?php esc_html_e( 'Företaget har inte lagt till någon beskrivning ännu.', 'rekryteringsportalen' ); ?></p>
					<?php endif; ?>

					<dl class="company-terms">
						<?php
						foreach ( array(
							RP_Post_Types::TAX_INDUSTRY => 'Branscher',
							RP_Post_Types::TAX_REGION   => 'Verksamma i',
						) as $rp_tax => $rp_label ) :
							?>
							<?php $rp_list = rp_term_list( $rp_id, $rp_tax ); ?>
							<?php if ( $rp_list ) : ?>
								<div><dt><?php echo esc_html( $rp_label ); ?></dt><dd><?php echo $rp_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd></div>
							<?php endif; ?>
						<?php endforeach; ?>
					</dl>
				</section>

				<?php if ( $rp_extended ) : ?>
					<?php
					$rp_video   = rp_video_embed( (string) get_post_meta( $rp_id, 'rp_video_url', true ) );
					$rp_gallery = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $rp_id, 'rp_galleri', true ) ) ) );
					$rp_team    = rp_company_lines( $rp_id, 'rp_team' );
					$rp_cases   = rp_company_lines( $rp_id, 'rp_kundcase' );
					?>
					<?php if ( $rp_video ) : ?>
						<section class="company-section"><h2 class="screen-reader-text"><?php esc_html_e( 'Video', 'rekryteringsportalen' ); ?></h2><?php echo $rp_video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
					<?php endif; ?>

					<?php if ( $rp_gallery ) : ?>
						<section class="company-section">
							<h2><?php esc_html_e( 'Bilder', 'rekryteringsportalen' ); ?></h2>
							<div class="company-gallery">
								<?php foreach ( $rp_gallery as $rp_img ) : ?>
									<a href="<?php echo esc_url( (string) wp_get_attachment_image_url( $rp_img, 'large' ) ); ?>"><?php echo wp_get_attachment_image( $rp_img, 'rp-card', false, array( 'loading' => 'lazy' ) ); ?></a>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>

					<?php if ( $rp_team ) : ?>
						<section class="company-section">
							<h2><?php esc_html_e( 'Möt teamet', 'rekryteringsportalen' ); ?></h2>
							<ul class="team-grid">
								<?php foreach ( $rp_team as $rp_person ) : ?>
									<?php
									[ $rp_name, $rp_role, $rp_mail, $rp_photo ] = array_pad( $rp_person, 4, '' );
									?>
									<li class="team-member">
										<?php if ( ctype_digit( $rp_photo ) ) : ?>
											<?php
											echo wp_get_attachment_image(
												(int) $rp_photo,
												'thumbnail',
												false,
												array(
													'class' => 'team-member__photo',
													'loading' => 'lazy',
													'alt' => '',
												)
											);
											?>
										<?php elseif ( $rp_photo ) : ?>
											<img class="team-member__photo" src="<?php echo esc_url( $rp_photo ); ?>" alt="" loading="lazy" width="96" height="96">
										<?php else : ?>
											<span class="team-member__photo team-member__photo--placeholder" aria-hidden="true"><?php echo esc_html( mb_substr( $rp_name, 0, 1 ) ); ?></span>
										<?php endif; ?>
										<strong><?php echo esc_html( $rp_name ); ?></strong>
										<span><?php echo esc_html( $rp_role ); ?></span>
										<?php if ( is_email( $rp_mail ) ) : ?>
											<a href="mailto:<?php echo esc_attr( $rp_mail ); ?>"><?php echo esc_html( $rp_mail ); ?></a>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>

					<?php if ( $rp_cases ) : ?>
						<section class="company-section">
							<h2><?php esc_html_e( 'Kundcase', 'rekryteringsportalen' ); ?></h2>
							<div class="case-grid">
								<?php foreach ( $rp_cases as $rp_case ) : ?>
									<?php [ $rp_title, $rp_text, $rp_client ] = array_pad( $rp_case, 3, '' ); ?>
									<article class="case-card">
										<h3><?php echo esc_html( $rp_title ); ?></h3>
										<p><?php echo esc_html( $rp_text ); ?></p>
										<?php if ( $rp_client ) : ?>
											<p class="case-card__client"><?php echo esc_html( $rp_client ); ?></p>
										<?php endif; ?>
									</article>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>
				<?php endif; ?>

				<section id="jobb" class="company-section">
					<h2><?php esc_html_e( 'Lediga jobb', 'rekryteringsportalen' ); ?></h2>
					<?php if ( $rp_jobs ) : ?>
						<div class="job-list">
							<?php foreach ( $rp_jobs as $rp_job ) : ?>
								<?php
								rp_part(
									'job-card',
									array(
										'post_id'      => $rp_job->ID,
										'show_company' => false,
									)
								);
								?>
							<?php endforeach; ?>
						</div>
						<p><a class="rp-link-arrow" href="<?php echo esc_url( add_query_arg( 'foretag_id', $rp_id, (string) get_post_type_archive_link( RP_Post_Types::JOB ) ) ); ?>"><?php esc_html_e( 'Alla jobb från företaget →', 'rekryteringsportalen' ); ?></a></p>
					<?php else : ?>
						<p class="rp-empty"><?php esc_html_e( 'Inga lediga jobb publicerade just nu.', 'rekryteringsportalen' ); ?>
						<?php if ( $rp_career ) : ?>
							<a href="<?php echo esc_url( $rp_career ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Se företagets karriärsida', 'rekryteringsportalen' ); ?></a>
						<?php endif; ?></p>
					<?php endif; ?>
				</section>

				<section id="omdomen" class="company-section">
					<h2><?php esc_html_e( 'Omdömen', 'rekryteringsportalen' ); ?></h2>
					<?php echo rp_rating_summary( $rp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo rp_review_list( $rp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>

				<section id="betygsatt" class="company-section review-form-section">
					<h2><?php echo esc_html( sprintf( 'Betygsätt %s', get_the_title() ) ); ?></h2>
					<p><?php esc_html_e( 'Har du sökt jobb via, anlitat eller arbetat på företaget? Dela din upplevelse och hjälp andra att välja rätt.', 'rekryteringsportalen' ); ?></p>
					<?php echo rp_review_form( $rp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>

				<?php if ( $rp_extended ) : ?>
					<section id="kontakt" class="company-section">
						<h2><?php echo esc_html( sprintf( 'Kontakta %s', get_the_title() ) ); ?></h2>
						<?php echo rp_contact_form( $rp_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</section>
				<?php endif; ?>
			</div>

			<aside class="layout-sidebar__aside">
				<div class="side-card">
					<h2 class="side-card__title"><?php esc_html_e( 'Fakta', 'rekryteringsportalen' ); ?></h2>
					<dl class="facts">
						<?php foreach ( $rp_facts as $rp_label => [ $rp_icon, $rp_value ] ) : ?>
							<div class="facts__row">
								<dt><?php echo rp_icon( $rp_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $rp_label ); ?></dt>
								<dd>
									<?php if ( 'E-post' === $rp_label ) : ?>
										<a href="mailto:<?php echo esc_attr( $rp_value ); ?>"><?php echo esc_html( $rp_value ); ?></a>
									<?php elseif ( 'Telefon' === $rp_label ) : ?>
										<a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $rp_value ) ); ?>"><?php echo esc_html( $rp_value ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $rp_value ); ?>
									<?php endif; ?>
								</dd>
							</div>
						<?php endforeach; ?>
					</dl>
					<ul class="side-card__links">
						<?php if ( $rp_site ) : ?>
							<li><a href="<?php echo esc_url( $rp_site ); ?>" target="_blank" rel="noopener<?php echo $rp_extended ? '' : ' nofollow'; ?>"><?php echo rp_icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( RP_Normalizer::domain( $rp_site ) ); ?></a></li>
						<?php endif; ?>
						<?php $rp_li = (string) get_post_meta( $rp_id, 'rp_linkedin', true ); ?>
						<?php if ( $rp_li ) : ?>
							<li><a href="<?php echo esc_url( $rp_li ); ?>" target="_blank" rel="noopener nofollow"><?php echo rp_icon( 'linkedin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>LinkedIn</a></li>
						<?php endif; ?>
						<?php if ( $rp_career ) : ?>
							<li><a href="<?php echo esc_url( $rp_career ); ?>" target="_blank" rel="noopener"><?php echo rp_icon( 'briefcase' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Karriärsida', 'rekryteringsportalen' ); ?></a></li>
						<?php endif; ?>
					</ul>
				</div>

				<?php if ( ! $rp_extended ) : ?>
					<div class="side-card side-card--claim">
						<h2 class="side-card__title"><?php esc_html_e( 'Är detta ditt företag?', 'rekryteringsportalen' ); ?></h2>
						<p><?php esc_html_e( 'Gör anspråk på sidan och uppgradera till en utökad presentation med bilder, team, kundcase och möjlighet att svara på omdömen.', 'rekryteringsportalen' ); ?></p>
						<a class="rp-button rp-button--primary rp-button--block" href="<?php echo esc_url( add_query_arg( 'foretag', $rp_id, rp_page_url( 'for-foretag' ) ) . '#bestall' ); ?>"><?php esc_html_e( 'Uppgradera', 'rekryteringsportalen' ); ?></a>
					</div>

					<?php
					$rp_services = wp_get_object_terms( $rp_id, RP_Post_Types::TAX_SERVICE, array( 'fields' => 'ids' ) );
					$rp_similar  = ( is_array( $rp_services ) && $rp_services ) ? get_posts(
						array(
							'post_type'      => RP_Post_Types::COMPANY,
							'posts_per_page' => 4,
							'post__not_in'   => array( $rp_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
							'no_found_rows'  => true,
							'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
								array(
									'taxonomy' => RP_Post_Types::TAX_SERVICE,
									'terms'    => $rp_services,
								),
							),
							'meta_key'       => 'rp_bayes_betyg', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							'orderby'        => 'meta_value_num',
							'order'          => 'DESC',
						)
					) : array();
					?>
					<?php if ( $rp_similar ) : ?>
						<div class="side-card">
							<h2 class="side-card__title"><?php esc_html_e( 'Liknande företag', 'rekryteringsportalen' ); ?></h2>
							<ul class="mini-list">
								<?php foreach ( $rp_similar as $rp_s ) : ?>
									<li>
										<?php echo rp_company_logo( $rp_s->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<span><a href="<?php echo esc_url( (string) get_permalink( $rp_s ) ); ?>"><?php echo esc_html( get_the_title( $rp_s ) ); ?></a>
										<?php echo rp_stars( (float) get_post_meta( $rp_s->ID, 'rp_snittbetyg', true ), 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</aside>
		</div>
	</article>
	<?php
endwhile;

get_footer();
