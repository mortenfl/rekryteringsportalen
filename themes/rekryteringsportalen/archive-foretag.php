<?php
/**
 * Company list: /foretag/.
 *
 * @package Rekryteringsportalen
 */

get_header();
$rp_new = RP_Query::new_companies_count( 30 );
?>
<div class="container">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Rekryteringsföretag', 'rekryteringsportalen' ); ?></h1>
		<p class="page-lead"><?php esc_html_e( 'Jämför rekryterings- och bemanningsföretag i Sverige. Filtrera på tjänst, bransch och region, och se vad kandidater och kunder tycker.', 'rekryteringsportalen' ); ?></p>
		<?php if ( $rp_new ) : ?>
			<p class="rp-new-banner">
				<span class="rp-new-banner__dot" aria-hidden="true"></span>
				<?php
				/* translators: %d: count. */
				echo esc_html( sprintf( _n( '%d nytt rekryteringsföretag senaste 30 dagarna', '%d nya rekryteringsföretag senaste 30 dagarna', $rp_new, 'rekryteringsportalen' ), $rp_new ) );
				?>
				<a href="<?php echo esc_url( add_query_arg( 'sortera', 'nyast', (string) get_post_type_archive_link( RP_Post_Types::COMPANY ) ) ); ?>"><?php esc_html_e( 'Visa nyaste', 'rekryteringsportalen' ); ?></a>
			</p>
		<?php endif; ?>
		<p class="rp-add-cta"><?php esc_html_e( 'Saknas ditt rekryteringsföretag?', 'rekryteringsportalen' ); ?> <a href="<?php echo esc_url( rp_page_url( 'lagg-till-foretag' ) ); ?>"><?php esc_html_e( 'Lägg till det gratis →', 'rekryteringsportalen' ); ?></a></p>
	</header>

	<div class="layout-sidebar">
		<aside class="layout-sidebar__aside" aria-label="<?php esc_attr_e( 'Filter', 'rekryteringsportalen' ); ?>">
			<?php rp_part( 'company-filters' ); ?>
		</aside>
		<div class="layout-sidebar__main">
			<?php rp_part( 'company-results' ); ?>
		</div>
	</div>
</div>
<?php
get_template_part( 'template-parts/cta-band' );
get_footer();
