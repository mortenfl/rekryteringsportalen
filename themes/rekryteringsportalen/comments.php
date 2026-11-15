<?php
/**
 * Comments for regular blog posts. Company reviews use their own form (see single-foretag.php).
 *
 * @package Rekryteringsportalen
 */

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments__title">
			<?php
			/* translators: %d: comment count. */
			echo esc_html( sprintf( _n( '%d kommentar', '%d kommentarer', get_comments_number(), 'rekryteringsportalen' ), get_comments_number() ) );
			?>
		</h2>
		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 40,
					'type'        => 'comment',
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php
	comment_form(
		array(
			'title_reply'  => __( 'Lämna en kommentar', 'rekryteringsportalen' ),
			'class_submit' => 'rp-button rp-button--primary',
		)
	);
	?>
</section>
