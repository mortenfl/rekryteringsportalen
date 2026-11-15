<?php
/**
 * Template functions and shortcodes used by the theme.
 *
 * All output is escaped. Functions are prefixed `rp_` and guarded so a theme may override them.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'rp_stars' ) ) {
	/**
	 * Star rating as accessible inline SVG.
	 *
	 * @param float  $rating Rating 0–5.
	 * @param string $size   sm|md|lg.
	 */
	function rp_stars( float $rating, string $size = 'md' ): string {
		$rating = max( 0, min( 5, $rating ) );
		$label  = $rating > 0 ? sprintf( 'Betyg %s av 5', number_format_i18n( $rating, 1 ) ) : 'Inga betyg ännu';
		$html   = '<span class="rp-stars rp-stars--' . esc_attr( $size ) . '" role="img" aria-label="' . esc_attr( $label ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$fill  = max( 0, min( 1, $rating - ( $i - 1 ) ) );
			$pct   = (int) round( $fill * 100 );
			$gid   = 'rpg' . wp_unique_id();
			$html .= '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><defs><linearGradient id="' . esc_attr( $gid ) . '"><stop offset="' . $pct . '%" stop-color="currentColor"/><stop offset="' . $pct . '%" stop-color="var(--rp-star-empty, #d6dbe2)"/></linearGradient></defs>'
				. '<path fill="url(#' . esc_attr( $gid ) . ')" d="M12 2.5l2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.52l-5.88 3.09 1.12-6.55L2.48 9.42l6.58-.96z"/></svg>';
		}
		return $html . '</span>';
	}
}

if ( ! function_exists( 'rp_rating_badge' ) ) {
	/**
	 * Stars + average + count.
	 *
	 * @param int    $company_id Company.
	 * @param string $size       Size.
	 */
	function rp_rating_badge( int $company_id, string $size = 'md' ): string {
		$avg   = (float) get_post_meta( $company_id, 'rp_snittbetyg', true );
		$count = (int) get_post_meta( $company_id, 'rp_antal_betyg', true );
		$html  = '<span class="rp-rating">' . rp_stars( $avg, $size );
		if ( $count ) {
			$html .= '<span class="rp-rating__value">' . esc_html( number_format_i18n( $avg, 1 ) ) . '</span>';
			/* translators: %d: number of reviews. */
			$html .= '<span class="rp-rating__count">(' . esc_html( sprintf( _n( '%d omdöme', '%d omdömen', $count, 'rekryteringsportalen-core' ), $count ) ) . ')</span>';
		} else {
			$html .= '<span class="rp-rating__count">Inga omdömen ännu</span>';
		}
		return $html . '</span>';
	}
}

if ( ! function_exists( 'rp_company_logo' ) ) {
	/**
	 * Company logo, or a letter placeholder.
	 *
	 * @param int    $company_id Company.
	 * @param string $size       Image size.
	 */
	function rp_company_logo( int $company_id, string $size = 'thumbnail' ): string {
		$logo = (int) get_post_meta( $company_id, 'rp_logo', true );
		if ( ! $logo ) {
			$logo = (int) get_post_thumbnail_id( $company_id );
		}
		if ( $logo ) {
			return wp_get_attachment_image(
				$logo,
				$size,
				false,
				array(
					'class'   => 'rp-logo',
					'loading' => 'lazy',
					'alt'     => get_the_title( $company_id ) . ' logotyp',
				)
			);
		}
		$title  = get_the_title( $company_id );
		$letter = mb_strtoupper( mb_substr( $title, 0, 1 ) );
		$hue    = abs( crc32( $title ) ) % 360;
		return '<span class="rp-logo rp-logo--placeholder" style="--rp-logo-hue:' . (int) $hue . '" aria-hidden="true">' . esc_html( $letter ) . '</span>';
	}
}

if ( ! function_exists( 'rp_term_list' ) ) {
	/**
	 * Term chips.
	 *
	 * @param int    $post_id  Post.
	 * @param string $taxonomy Taxonomy.
	 * @param int    $limit    Max chips (0 = all).
	 */
	function rp_term_list( int $post_id, string $taxonomy, int $limit = 0 ): string {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return '';
		}
		$more = 0;
		if ( $limit && count( $terms ) > $limit ) {
			$more  = count( $terms ) - $limit;
			$terms = array_slice( $terms, 0, $limit );
		}
		$html = '<ul class="rp-chips">';
		foreach ( $terms as $t ) {
			$html .= '<li><a class="rp-chip" href="' . esc_url( (string) get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a></li>';
		}
		if ( $more ) {
			$html .= '<li><span class="rp-chip rp-chip--muted">+' . (int) $more . '</span></li>';
		}
		return $html . '</ul>';
	}
}

if ( ! function_exists( 'rp_sponsored_label' ) ) {
	/**
	 * "Utvald partner" label for companies with a package. Clearly marked as advertising.
	 *
	 * @param int $company_id Company.
	 */
	function rp_sponsored_label( int $company_id ): string {
		if ( RP_Packages::NONE === RP_Packages::get( $company_id ) ) {
			return '';
		}
		return '<span class="rp-sponsored" title="Annonsör – företaget har köpt ett annonspaket. Påverkar inte betyget.">Utvald partner <span class="rp-sponsored__ad">Annons</span></span>';
	}
}

if ( ! function_exists( 'rp_page_url' ) ) {
	/**
	 * URL of one of the plugin pages (topplistor, hitta-rekryterare, for-foretag ...).
	 *
	 * @param string $slug Slug.
	 */
	function rp_page_url( string $slug ): string {
		$pages = get_option( 'rp_pages', array() );
		if ( is_array( $pages ) && ! empty( $pages[ $slug ] ) && 'publish' === get_post_status( (int) $pages[ $slug ] ) ) {
			return (string) get_permalink( (int) $pages[ $slug ] );
		}
		return home_url( '/' . $slug . '/' );
	}
}

if ( ! function_exists( 'rp_portal_url' ) ) {
	/**
	 * Outbound job portal URL with UTM source.
	 *
	 * @param int $portal_id Portal.
	 */
	function rp_portal_url( int $portal_id ): string {
		$url = (string) get_post_meta( $portal_id, 'rp_url', true );
		return '' === $url ? '' : add_query_arg( 'utm_source', 'rekryteringsportalen', $url );
	}
}

if ( ! function_exists( 'rp_breadcrumbs' ) ) {
	/**
	 * Breadcrumb navigation.
	 */
	function rp_breadcrumbs(): string {
		$crumbs = RP_SEO::breadcrumbs();
		if ( count( $crumbs ) < 2 ) {
			return '';
		}
		$html = '<nav class="rp-breadcrumbs" aria-label="Brödsmulor"><ol>';
		$last = count( $crumbs ) - 1;
		foreach ( $crumbs as $i => $c ) {
			$html .= $i === $last
				? '<li aria-current="page">' . esc_html( $c['name'] ) . '</li>'
				: '<li><a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['name'] ) . '</a></li>';
		}
		return $html . '</ol></nav>';
	}
}

if ( ! function_exists( 'rp_rating_summary' ) ) {
	/**
	 * Average, stars and 5★…1★ distribution bars.
	 *
	 * @param int $company_id Company.
	 */
	function rp_rating_summary( int $company_id ): string {
		$ratings = RP_Ratings::ratings( $company_id );
		$avg     = RP_Rating_Math::average( $ratings );
		$dist    = RP_Rating_Math::distribution( $ratings );
		$html    = '<div class="rp-summary"><div class="rp-summary__score"><span class="rp-summary__avg">' . esc_html( $ratings ? number_format_i18n( $avg, 1 ) : '–' ) . '</span>'
			. rp_stars( $avg, 'lg' )
			/* translators: %d: number of reviews. */
			. '<span class="rp-summary__count">' . esc_html( sprintf( _n( 'Baserat på %d omdöme', 'Baserat på %d omdömen', count( $ratings ), 'rekryteringsportalen-core' ), count( $ratings ) ) ) . '</span></div>'
			. '<dl class="rp-summary__bars">';
		foreach ( $dist as $star => $row ) {
			$html .= '<div class="rp-bar"><dt>' . (int) $star . ' <span aria-hidden="true">★</span><span class="screen-reader-text">stjärnor</span></dt>'
				. '<dd><span class="rp-bar__track"><span class="rp-bar__fill" style="width:' . (int) $row['percent'] . '%"></span></span><span class="rp-bar__count">' . (int) $row['count'] . '</span></dd></div>';
		}
		return $html . '</dl></div>';
	}
}

if ( ! function_exists( 'rp_review_form' ) ) {
	/**
	 * Review form: 1–5 stars (radio buttons = keyboard accessible) + mandatory comment.
	 *
	 * @param int $company_id Company.
	 */
	function rp_review_form( int $company_id ): string {
		$min    = RP_Ratings::MIN_LENGTH;
		$html   = RP_Forms::notice( 'review' );
		$html  .= '<form class="rp-form rp-review-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html  .= RP_Forms::hidden_fields( 'rp_submit_review' );
		$html  .= '<input type="hidden" name="rp_foretag" value="' . (int) $company_id . '">';
		$html  .= '<fieldset class="rp-star-input"><legend>Ditt betyg * <span class="rp-star-input__hint">1 = sämst, 5 = bäst</span></legend><div class="rp-star-input__stars">';
		$labels = array(
			1 => 'Mycket dåligt',
			2 => 'Dåligt',
			3 => 'Okej',
			4 => 'Bra',
			5 => 'Utmärkt',
		);
		for ( $i = 1; $i <= 5; $i++ ) {
			$html .= '<input type="radio" id="rp-star-' . $i . '" name="rp_betyg" value="' . $i . '" required>'
				. '<label for="rp-star-' . $i . '" title="' . esc_attr( $labels[ $i ] ) . '"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.5l2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.52l-5.88 3.09 1.12-6.55L2.48 9.42l6.58-.96z"/></svg>'
				/* translators: 1: stars, 2: label. */
				. '<span class="screen-reader-text">' . esc_html( sprintf( '%1$d av 5 – %2$s', $i, $labels[ $i ] ) ) . '</span></label>';
		}
		$html .= '</div><output class="rp-star-input__label" aria-live="polite"></output></fieldset>';
		$html .= '<p class="rp-field rp-field--wide"><label for="rp-kommentar">Varför ger du detta betyg? *</label>'
			. '<textarea id="rp-kommentar" name="rp_kommentar" rows="5" required minlength="' . (int) $min . '" maxlength="' . (int) RP_Ratings::MAX_LENGTH . '" placeholder="Beskriv din upplevelse – vad var bra och vad kunde varit bättre?"></textarea>'
			. '<span class="rp-field__help">Minst ' . (int) $min . ' tecken. <span class="rp-counter" data-min="' . (int) $min . '"></span></span></p>';
		$html .= '<div class="rp-form__grid">';
		$html .= '<p class="rp-field"><label for="rp-roll">Jag är</label><select id="rp-roll" name="rp_roll"><option value="">Välj (valfritt)</option>';
		foreach ( RP_Ratings::roles() as $key => $label ) {
			$html .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		$html      .= '</select></p>';
		$html      .= '<p class="rp-field"><label for="rp-namn">Namn eller signatur</label><input id="rp-namn" name="rp_namn" type="text" maxlength="60" autocomplete="nickname" placeholder="Visas publikt – lämna tomt för Anonym"></p>';
		$html      .= '<p class="rp-field rp-field--wide"><label for="rp-epost">E-post *</label><input id="rp-epost" name="rp_epost" type="email" required autocomplete="email"><span class="rp-field__help">Visas aldrig publikt. Vi skickar en länk för att bekräfta ditt omdöme.</span></p>';
		$html      .= '</div>';
		$guidelines = rp_page_url( 'riktlinjer-omdomen' );
		$html      .= RP_Forms::consent_field( 'Jag har läst riktlinjerna och godkänner att omdömet publiceras och att mina uppgifter behandlas enligt {integritet}. *' );
		$html      .= '<p class="rp-form__note"><a href="' . esc_url( $guidelines ) . '">Läs riktlinjerna för omdömen</a></p>';
		$html      .= '<p><button type="submit" class="rp-button rp-button--primary">Skicka omdöme</button></p></form>';
		return $html;
	}
}

if ( ! function_exists( 'rp_review_list' ) ) {
	/**
	 * Approved reviews with "Rapportera" and the company's reply (package 1+).
	 *
	 * @param int $company_id Company.
	 * @param int $per_page   Reviews per page.
	 */
	function rp_review_list( int $company_id, int $per_page = 10 ): string {
		$page    = isset( $_GET['omdomen-sida'] ) ? max( 1, absint( $_GET['omdomen-sida'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$reviews = RP_Ratings::get_reviews( $company_id, $per_page, $page );
		$total   = (int) get_post_meta( $company_id, 'rp_antal_betyg', true );
		$replies = RP_Packages::has_extended( $company_id );
		$roles   = RP_Ratings::roles();

		if ( ! $reviews ) {
			return '<p class="rp-empty">Det finns inga omdömen ännu. Bli först med att betygsätta ' . esc_html( get_the_title( $company_id ) ) . '!</p>';
		}

		$html = '<ol class="rp-reviews">';
		foreach ( $reviews as $review ) {
			$rating = (int) get_comment_meta( $review->comment_ID, 'rp_betyg', true );
			$role   = (string) get_comment_meta( $review->comment_ID, 'rp_roll', true );
			$reply  = $replies ? (string) get_comment_meta( $review->comment_ID, 'rp_foretagssvar', true ) : '';
			$html  .= '<li class="rp-review" id="omdome-' . (int) $review->comment_ID . '"><article>'
				. '<header class="rp-review__header">' . rp_stars( (float) $rating, 'sm' )
				. '<span class="rp-review__author">' . esc_html( $review->comment_author ) . '</span>'
				. ( isset( $roles[ $role ] ) ? '<span class="rp-review__role">' . esc_html( $roles[ $role ] ) . '</span>' : '' )
				. '<time datetime="' . esc_attr( mysql2date( 'c', $review->comment_date_gmt ) ) . '">' . esc_html( mysql2date( 'j F Y', $review->comment_date ) ) . '</time></header>'
				. '<div class="rp-review__body">' . wpautop( esc_html( $review->comment_content ) ) . '</div>';
			if ( '' !== $reply ) {
				$html .= '<div class="rp-review__reply"><strong>Svar från ' . esc_html( get_the_title( $company_id ) ) . '</strong>' . wpautop( esc_html( $reply ) ) . '</div>';
			}
			$html .= '<details class="rp-report"><summary>Rapportera</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
				. RP_Forms::hidden_fields( 'rp_report_review' )
				. '<input type="hidden" name="rp_omdome" value="' . (int) $review->comment_ID . '">'
				. '<label for="rp-anl-' . (int) $review->comment_ID . '">Varför bryter omdömet mot riktlinjerna?</label>'
				. '<textarea id="rp-anl-' . (int) $review->comment_ID . '" name="rp_anledning" rows="2" maxlength="500"></textarea>'
				. '<button type="submit" class="rp-button rp-button--small">Skicka rapport</button></form></details>';
			$html .= '</article></li>';
		}
		$html .= '</ol>';

		$pages = (int) ceil( $total / $per_page );
		if ( $pages > 1 ) {
			$html .= '<nav class="rp-pagination" aria-label="Fler omdömen">'
				. paginate_links(
					array(
						'base'      => add_query_arg( 'omdomen-sida', '%#%' ) . '#omdomen',
						'format'    => '',
						'current'   => $page,
						'total'     => $pages,
						'prev_text' => '← Nyare',
						'next_text' => 'Äldre →',
					)
				) . '</nav>';
		}
		return $html;
	}
}

if ( ! function_exists( 'rp_toplist' ) ) {
	/**
	 * Ranked list (top or bottom).
	 *
	 * @param string $type     topp|botten.
	 * @param int    $limit    Count.
	 * @param string $taxonomy Optional taxonomy.
	 * @param string $term     Optional term slug.
	 * @param bool   $compact  Compact variant (front page).
	 */
	function rp_toplist( string $type = 'topp', int $limit = 10, string $taxonomy = '', string $term = '', bool $compact = false ): string {
		$posts = RP_Query::toplist( $type, $limit, $taxonomy, $term );
		if ( ! $posts ) {
			return '<p class="rp-empty">Inga företag har ännu tillräckligt många omdömen (minst ' . (int) RP_Settings::get( 'min_omdomen' ) . ') för att komma med på listan.</p>';
		}
		$html = '<ol class="rp-toplist rp-toplist--' . esc_attr( $type ) . ( $compact ? ' rp-toplist--compact' : '' ) . '">';
		foreach ( $posts as $i => $p ) {
			$html .= '<li class="rp-toplist__item"><span class="rp-toplist__rank" aria-hidden="true">' . (int) ( $i + 1 ) . '</span>'
				. '<a class="rp-toplist__logo" href="' . esc_url( (string) get_permalink( $p ) ) . '" tabindex="-1" aria-hidden="true">' . rp_company_logo( $p->ID ) . '</a>'
				. '<div class="rp-toplist__main"><a class="rp-toplist__name" href="' . esc_url( (string) get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a>'
				. rp_rating_badge( $p->ID, 'sm' );
			if ( ! $compact ) {
				$city  = (string) get_post_meta( $p->ID, 'rp_ort', true );
				$html .= $city ? '<span class="rp-toplist__meta">' . esc_html( $city ) . '</span>' : '';
			}
			$html .= '</div></li>';
		}
		return $html . '</ol>';
	}
}

/**
 * [rp_topplista typ="topp|botten" antal="10" tjanst="" bransch="" region="" kompakt="0"]
 *
 * @param array<string,string>|string $atts Attributes.
 */
function rp_shortcode_toplist( $atts ): string {
	$atts = shortcode_atts(
		array(
			'typ'     => 'topp',
			'antal'   => 10,
			'tjanst'  => '',
			'bransch' => '',
			'region'  => '',
			'kompakt' => '0',
		),
		$atts,
		'rp_topplista'
	);
	$tax  = '';
	$term = '';
	foreach ( array( 'tjanst', 'bransch', 'region' ) as $t ) {
		if ( '' !== $atts[ $t ] ) {
			$tax  = $t;
			$term = sanitize_title( $atts[ $t ] );
			break;
		}
	}
	return rp_toplist( 'botten' === $atts['typ'] ? 'botten' : 'topp', max( 1, min( 50, (int) $atts['antal'] ) ), $tax, $term, '1' === (string) $atts['kompakt'] );
}
add_shortcode( 'rp_topplista', 'rp_shortcode_toplist' );

/**
 * [rp_jobbportaler kategori=""]
 *
 * @param array<string,string>|string $atts Attributes.
 */
function rp_shortcode_portals( $atts ): string {
	$atts = shortcode_atts(
		array(
			'kategori' => '',
			'antal'    => -1,
		),
		$atts,
		'rp_jobbportaler'
	);
	$args = array(
		'post_type'      => RP_Post_Types::PORTAL,
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['antal'],
		'orderby'        => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
	);
	if ( '' !== $atts['kategori'] ) {
		$args['meta_key']   = 'rp_kategori'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$args['meta_value'] = $atts['kategori']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	}
	$html = '<ul class="rp-portals">';
	foreach ( get_posts( $args ) as $p ) {
		$html .= rp_portal_card( $p->ID );
	}
	return $html . '</ul>';
}
add_shortcode( 'rp_jobbportaler', 'rp_shortcode_portals' );

if ( ! function_exists( 'rp_portal_card' ) ) {
	/**
	 * Job portal card.
	 *
	 * @param int $id Portal.
	 */
	function rp_portal_card( int $id ): string {
		$url  = rp_portal_url( $id );
		$logo = get_post_thumbnail_id( $id )
			? get_the_post_thumbnail(
				$id,
				'thumbnail',
				array(
					'class'   => 'rp-logo',
					'loading' => 'lazy',
					'alt'     => '',
				)
			)
			: rp_company_logo( $id );
		return '<li class="rp-portal">' . $logo
			. '<div class="rp-portal__body"><h3 class="rp-portal__name">' . esc_html( get_the_title( $id ) ) . '</h3>'
			. '<p class="rp-portal__desc">' . esc_html( (string) get_post_meta( $id, 'rp_beskrivning', true ) ) . '</p></div>'
			. ( $url ? '<a class="rp-button rp-button--outline rp-portal__link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow" data-rp-portal="' . (int) $id . '">Gå till ' . esc_html( get_the_title( $id ) ) . '<span class="screen-reader-text"> (öppnas i ny flik)</span></a>' : '' )
			. '</li>';
	}
}

if ( ! function_exists( 'rp_contact_form' ) ) {
	/**
	 * Contact form to the company (package 1+).
	 *
	 * @param int $company_id Company.
	 */
	function rp_contact_form( int $company_id ): string {
		if ( ! RP_Packages::has_extended( $company_id ) ) {
			return '';
		}
		return RP_Forms::notice( 'contact' )
			. '<form class="rp-form rp-contact-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. RP_Forms::hidden_fields( 'rp_contact_company' )
			. '<input type="hidden" name="rp_foretag" value="' . (int) $company_id . '">'
			. '<div class="rp-form__grid">'
			. '<p class="rp-field"><label for="rp-k-namn">Namn *</label><input id="rp-k-namn" name="rp_namn" type="text" required autocomplete="name"></p>'
			. '<p class="rp-field"><label for="rp-k-epost">E-post *</label><input id="rp-k-epost" name="rp_epost" type="email" required autocomplete="email"></p>'
			. '<p class="rp-field rp-field--wide"><label for="rp-k-meddelande">Meddelande *</label><textarea id="rp-k-meddelande" name="rp_meddelande" rows="4" required minlength="10"></textarea></p>'
			. '</div>'
			. RP_Forms::consent_field( 'Jag godkänner att meddelandet och mina kontaktuppgifter skickas till företaget enligt {integritet}. *' )
			. '<p><button type="submit" class="rp-button rp-button--primary">Skicka meddelande</button></p></form>';
	}
}

if ( ! function_exists( 'rp_company_lines' ) ) {
	/**
	 * Parse a "lines" field (Team, Kundcase): one item per line, columns separated by "|".
	 *
	 * @param int    $company_id Company.
	 * @param string $meta       Meta key.
	 * @return array<int, string[]>
	 */
	function rp_company_lines( int $company_id, string $meta ): array {
		$raw = (string) get_post_meta( $company_id, $meta, true );
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[] = array_map( 'trim', explode( '|', $line ) );
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'rp_video_embed' ) ) {
	/**
	 * Privacy-friendly video embed (YouTube nocookie / Vimeo dnt).
	 *
	 * @param string $url Video URL.
	 */
	function rp_video_embed( string $url ): string {
		if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~', $url, $m ) ) {
			$src = 'https://www.youtube-nocookie.com/embed/' . $m[1];
		} elseif ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $url, $m ) ) {
			$src = 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
		} else {
			return '';
		}
		return '<div class="rp-video"><iframe src="' . esc_url( $src ) . '" title="Video" loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>';
	}
}

if ( ! function_exists( 'rp_package_cards' ) ) {
	/**
	 * The two package cards for the sales page.
	 */
	function rp_package_cards(): string {
		$s        = RP_Settings::all();
		$features = array(
			RP_Packages::EXTENDED => array( 'Lång presentation med bilder och formatering', 'Omslagsbild och bildgalleri', 'Video', 'Team och kontaktpersoner med foto', 'Kundcase och referenser', 'Svara publikt på omdömen', 'Kontaktformulär direkt till er', 'Inga konkurrenter på er sida', 'Markering som Utvald partner' ),
			RP_Packages::LEADS    => array( 'Allt i Paket 1', 'Förfrågningar från företag som ska rekrytera', 'Matchas på er bransch och region', 'Max ' . (int) $s['max_lead_mottagare'] . ' rekryteringsföretag per förfrågan', 'Rättvis fördelning mellan partners', 'Leads direkt till er inkorg' ),
		);
		$html     = '<div class="rp-packages">';
		foreach ( $features as $paket => $list ) {
			$is_leads = RP_Packages::LEADS === $paket;
			$html    .= '<section class="rp-package' . ( $is_leads ? ' rp-package--featured' : '' ) . '">'
				. ( $is_leads ? '<span class="rp-package__flag">Mest värde</span>' : '' )
				. '<h3 class="rp-package__title">' . esc_html( RP_Packages::labels()[ $paket ] ) . '</h3>'
				. '<p class="rp-package__price"><span>' . esc_html( (string) $s[ $is_leads ? 'pris_paket2' : 'pris_paket1' ] ) . '</span> kr/mån <small>exkl. moms</small></p>'
				. '<p class="rp-package__desc">' . esc_html( (string) $s[ $is_leads ? 'beskrivning_paket2' : 'beskrivning_paket1' ] ) . '</p><ul class="rp-package__features">';
			foreach ( $list as $f ) {
				$html .= '<li>' . esc_html( $f ) . '</li>';
			}
			$html .= '</ul><a class="rp-button ' . ( $is_leads ? 'rp-button--primary' : 'rp-button--outline' ) . ' rp-button--block" href="' . esc_url( add_query_arg( 'paket', $paket ) . '#bestall' ) . '">Välj ' . esc_html( RP_Packages::short_labels()[ $paket ] ) . '</a></section>';
		}
		return $html . '</div>';
	}
}
add_shortcode( 'rp_paket', 'rp_package_cards' );
