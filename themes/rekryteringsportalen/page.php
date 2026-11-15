<?php
/**
 * Page.
 *
 * @package Rekryteringsportalen
 */

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'container container--narrow' ); ?>>
		<header class="page-header"><h1 class="page-title"><?php the_title(); ?></h1></header>
		<div class="entry-content"><?php the_content(); ?></div>
		<?php wp_link_pages(); ?>
	</article>
	<?php
endwhile;
get_footer();
