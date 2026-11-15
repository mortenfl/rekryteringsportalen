<?php
/**
 * Blog post.
 *
 * @package Rekryteringsportalen
 */

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'container container--narrow' ); ?>>
		<header class="page-header">
			<h1 class="page-title"><?php the_title(); ?></h1>
			<p class="post-card__meta"><?php echo esc_html( get_the_date() ); ?></p>
		</header>
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'large', array( 'class' => 'entry-image' ) ); ?>
		<?php endif; ?>
		<div class="entry-content"><?php the_content(); ?></div>
		<?php wp_link_pages(); ?>
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</article>
	<?php
endwhile;
get_footer();
