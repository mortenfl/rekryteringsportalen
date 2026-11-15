<?php
/**
 * Job list: /jobb/.
 *
 * @package Rekryteringsportalen
 */

get_header();
global $wp_query;
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$rp_q       = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$rp_type    = isset( $_GET['anstallning'] ) ? sanitize_text_field( wp_unslash( $_GET['anstallning'] ) ) : '';
$rp_company = isset( $_GET['foretag_id'] ) ? absint( $_GET['foretag_id'] ) : 0;
// phpcs:enable
$rp_action = (string) get_post_type_archive_link( RP_Post_Types::JOB );
?>
<div class="container">
	<header class="page-header">
		<h1 class="page-title">
			<?php
			echo esc_html(
				$rp_company
					? sprintf( 'Lediga jobb hos %s', get_the_title( $rp_company ) )
					: __( 'Lediga jobb hos rekryteringsföretag', 'rekryteringsportalen' )
			);
			?>
		</h1>
		<p class="page-lead"><?php esc_html_e( 'Aktuella uppdrag från rekryterings- och bemanningsföretagen på Rekryteringsportalen.', 'rekryteringsportalen' ); ?></p>
	</header>

	<div class="layout-sidebar">
		<aside class="layout-sidebar__aside" aria-label="<?php esc_attr_e( 'Filter', 'rekryteringsportalen' ); ?>">
			<form class="rp-filters" method="get" action="<?php echo esc_url( $rp_action ); ?>" data-rp-filter>
				<details class="rp-filters__details" open>
					<summary class="rp-filters__summary"><?php echo rp_icon( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Filtrera', 'rekryteringsportalen' ); ?></summary>
					<?php if ( $rp_company ) : ?>
						<input type="hidden" name="foretag_id" value="<?php echo (int) $rp_company; ?>">
					<?php endif; ?>
					<p class="rp-field">
						<label for="rp-j-q"><?php esc_html_e( 'Sökord', 'rekryteringsportalen' ); ?></label>
						<input id="rp-j-q" type="search" name="q" value="<?php echo esc_attr( $rp_q ); ?>" placeholder="<?php esc_attr_e( 'T.ex. controller', 'rekryteringsportalen' ); ?>">
					</p>
					<?php
					foreach ( array(
						RP_Post_Types::TAX_INDUSTRY => 'Bransch',
						RP_Post_Types::TAX_REGION   => 'Region',
					) as $rp_tax => $rp_label ) :
						?>
																			<?php
																				$rp_terms = get_terms(
																					array(
																						'taxonomy'   => $rp_tax,
																						'hide_empty' => false,
																					)
																				);
																			$rp_current   = isset( $_GET[ $rp_tax ] ) ? sanitize_title( wp_unslash( $_GET[ $rp_tax ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
																			?>
						<p class="rp-field">
							<label for="rp-j-<?php echo esc_attr( $rp_tax ); ?>"><?php echo esc_html( $rp_label ); ?></label>
							<select id="rp-j-<?php echo esc_attr( $rp_tax ); ?>" name="<?php echo esc_attr( $rp_tax ); ?>">
								<option value=""><?php esc_html_e( 'Alla', 'rekryteringsportalen' ); ?></option>
																			<?php foreach ( is_array( $rp_terms ) ? $rp_terms : array() as $rp_term ) : ?>
									<option value="<?php echo esc_attr( $rp_term->slug ); ?>" <?php selected( $rp_current, $rp_term->slug ); ?>><?php echo esc_html( ( $rp_term->parent ? '— ' : '' ) . $rp_term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
					<?php endforeach; ?>
					<p class="rp-field">
						<label for="rp-j-type"><?php esc_html_e( 'Anställningsform', 'rekryteringsportalen' ); ?></label>
						<select id="rp-j-type" name="anstallning">
							<option value=""><?php esc_html_e( 'Alla', 'rekryteringsportalen' ); ?></option>
							<?php foreach ( RP_Post_Types::employment_types() as $rp_t ) : ?>
								<option <?php selected( $rp_type, $rp_t ); ?>><?php echo esc_html( $rp_t ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<div class="rp-filters__actions">
						<button type="submit" class="rp-button rp-button--primary rp-filters__submit"><?php esc_html_e( 'Visa jobb', 'rekryteringsportalen' ); ?></button>
						<a class="rp-filters__reset" href="<?php echo esc_url( $rp_action ); ?>"><?php esc_html_e( 'Rensa filter', 'rekryteringsportalen' ); ?></a>
					</div>
				</details>
			</form>
		</aside>

		<div class="layout-sidebar__main">
			<div id="rp-results" class="rp-results" aria-live="polite" aria-busy="false">
				<p class="rp-results__count">
					<?php
					/* translators: %d: jobs. */
					echo esc_html( sprintf( _n( '%d ledigt jobb', '%d lediga jobb', (int) $wp_query->found_posts, 'rekryteringsportalen' ), (int) $wp_query->found_posts ) );
					?>
				</p>
				<?php if ( have_posts() ) : ?>
					<div class="job-list">
						<?php
						while ( have_posts() ) :
							the_post();
							rp_part( 'job-card', array( 'post_id' => get_the_ID() ) );
						endwhile;
						?>
					</div>
					<?php rp_theme_pagination(); ?>
				<?php else : ?>
					<div class="rp-empty"><p><?php esc_html_e( 'Inga jobb matchar dina filter just nu.', 'rekryteringsportalen' ); ?></p>
					<p><a href="<?php echo esc_url( home_url( '/jobbportaler/' ) ); ?>"><?php esc_html_e( 'Sök vidare på jobbportalerna →', 'rekryteringsportalen' ); ?></a></p></div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
<?php
get_footer();
