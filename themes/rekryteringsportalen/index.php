<?php
/**
 * Fallback template (blog, archives).
 *
 * @package Rekryteringsportalen
 */

get_header();
?>
<div class="container container--narrow">
	<header class="page-header">
		<h1 class="page-title"><?php echo esc_html( is_home() ? single_post_title( '', false ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
	</header>
	<?php if ( have_posts() ) : ?>
		<div class="post-list">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'post-card' ); ?>>
					<?php if ( has_post_thumbnail() ) : ?>
						<a href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'rp-card', array( 'loading' => 'lazy' ) ); ?></a>
					<?php endif; ?>
					<h2 class="post-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p class="post-card__meta"><?php echo esc_html( get_the_date() ); ?></p>
					<?php the_excerpt(); ?>
				</article>
			<?php endwhile; ?>
		</div>
		<?php rp_theme_pagination(); ?>
	<?php else : ?>
		<p class="rp-empty"><?php esc_html_e( 'Inget innehåll hittades.', 'rekryteringsportalen' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
