<?php
/**
 * Company list filter form. Works without JS (plain GET); JS upgrades it to live filtering.
 *
 * @package Rekryteringsportalen
 *
 * @var array{action?:string, lock?:string} $args lock = taxonomy fixed by the current archive.
 */

$rp_action = (string) ( $args['action'] ?? get_post_type_archive_link( RP_Post_Types::COMPANY ) );
$rp_lock   = (string) ( $args['lock'] ?? '' );
$rp_taxes  = array(
	RP_Post_Types::TAX_SERVICE  => 'Tjänst',
	RP_Post_Types::TAX_INDUSTRY => 'Bransch',
	RP_Post_Types::TAX_REGION   => 'Region',
);
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$rp_q    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$rp_min  = isset( $_GET['min_betyg'] ) ? (float) $_GET['min_betyg'] : 0;
$rp_sort = isset( $_GET['sortera'] ) ? sanitize_key( wp_unslash( $_GET['sortera'] ) ) : '';
// phpcs:enable
?>
<form class="rp-filters" method="get" action="<?php echo esc_url( $rp_action ); ?>" data-rp-filter>
	<details class="rp-filters__details" open>
		<summary class="rp-filters__summary"><?php echo rp_icon( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Filtrera', 'rekryteringsportalen' ); ?></summary>

		<p class="rp-field">
			<label for="rp-f-q"><?php esc_html_e( 'Företagsnamn', 'rekryteringsportalen' ); ?></label>
			<input id="rp-f-q" type="search" name="q" value="<?php echo esc_attr( $rp_q ); ?>" placeholder="<?php esc_attr_e( 'Sök på namn', 'rekryteringsportalen' ); ?>">
		</p>

		<?php foreach ( $rp_taxes as $rp_tax => $rp_label ) : ?>
			<?php
			if ( $rp_tax === $rp_lock ) {
				continue;
			}
			$rp_terms   = get_terms(
				array(
					'taxonomy'   => $rp_tax,
					'hide_empty' => true,
				)
			);
			$rp_current = isset( $_GET[ $rp_tax ] ) ? sanitize_title( wp_unslash( $_GET[ $rp_tax ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! is_array( $rp_terms ) || ! $rp_terms ) {
				continue;
			}
			?>
			<p class="rp-field">
				<label for="rp-f-<?php echo esc_attr( $rp_tax ); ?>"><?php echo esc_html( $rp_label ); ?></label>
				<select id="rp-f-<?php echo esc_attr( $rp_tax ); ?>" name="<?php echo esc_attr( $rp_tax ); ?>">
					<option value=""><?php esc_html_e( 'Alla', 'rekryteringsportalen' ); ?></option>
					<?php foreach ( $rp_terms as $rp_term ) : ?>
						<option value="<?php echo esc_attr( $rp_term->slug ); ?>" <?php selected( $rp_current, $rp_term->slug ); ?>>
							<?php echo esc_html( ( $rp_term->parent ? '— ' : '' ) . $rp_term->name . ' (' . $rp_term->count . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endforeach; ?>

		<fieldset class="rp-field rp-min-rating">
			<legend><?php esc_html_e( 'Lägsta betyg', 'rekryteringsportalen' ); ?></legend>
			<?php
			// String keys: a float key (4.5) would be truncated to 4 and overwrite "4+".
			$rp_options = array(
				'0'   => 'Alla',
				'3'   => '3+',
				'4'   => '4+',
				'4.5' => '4,5+',
			);
			foreach ( $rp_options as $rp_val => $rp_text ) :
				?>
				<label class="rp-pill">
					<input type="radio" name="min_betyg" value="<?php echo esc_attr( (string) $rp_val ); ?>" <?php checked( (float) $rp_val === $rp_min ); ?>>
					<span><?php echo $rp_val ? '★ ' : ''; ?><?php echo esc_html( $rp_text ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<p class="rp-field">
			<label for="rp-f-sort"><?php esc_html_e( 'Sortera', 'rekryteringsportalen' ); ?></label>
			<select id="rp-f-sort" name="sortera">
				<?php foreach ( RP_Query::sort_options() as $rp_key => $rp_text ) : ?>
					<option value="<?php echo esc_attr( 'rekommenderat' === $rp_key ? '' : $rp_key ); ?>" <?php selected( $rp_sort, 'rekommenderat' === $rp_key ? '' : $rp_key ); ?>><?php echo esc_html( $rp_text ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<div class="rp-filters__actions">
			<button type="submit" class="rp-button rp-button--primary rp-filters__submit"><?php esc_html_e( 'Visa resultat', 'rekryteringsportalen' ); ?></button>
			<a class="rp-filters__reset" href="<?php echo esc_url( $rp_action ); ?>"><?php esc_html_e( 'Rensa filter', 'rekryteringsportalen' ); ?></a>
		</div>
	</details>
</form>
