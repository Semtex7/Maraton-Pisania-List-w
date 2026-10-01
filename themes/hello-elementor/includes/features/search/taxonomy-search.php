<?php

declare( strict_types = 1 );

if ( ! function_exists( 'build_filter_dropdown' ) ) {
	/**
	 * Build search filter drop downs
	 *
	 * @package Amnesty\Search
	 *
	 * @param WP_Taxonomy $tax_item taxonomy object
	 * @param boolean     $list whether to use list markup
	 *
	 * @return string
	 */
	function build_filter_dropdown( $tax_item, $list = false ) {
		$queried = absint( filter_input( INPUT_GET, "q{$tax_item->name}", FILTER_SANITIZE_NUMBER_INT ) );
		$terms   = get_terms( [ 'taxonomy' => $tax_item->name ] );

		if ( count( $terms ) < 1 ) {
			return '';
		}

		if ( $list ) {
			$x = '<li class="element-select">';
		} else {
			$x = '<div class="element-select">';
		}

		$x .= sprintf( '<select multiple id="%1$s" name="q%1$s" aria-label="%2$s">', $tax_item->name, sprintf( /* translators: [front] %s: taxonomy label */ __( 'Limit results to specific %s', 'amnesty' ), $tax_item->label ) );
		$x .= sprintf( '<option value="" disabled>%s</option>', sprintf( /* translators: [front] %s: taxonomy label */ __( 'Select %s', 'amnesty' ), $tax_item->label ) );

		foreach ( $terms as $term ) {
			$x .= sprintf( '<option value="%1$s" %3$s>%2$s</option>', $term->term_id, $term->name, selected( $term->term_id, $queried, false ) );
		}

		$x .= '</select>';

		if ( $list ) {
			$x .= '</li>';
		} else {
			$x .= '</div>';
		}

		return $x;
	}
}

if ( ! function_exists( 'build_filter_checkbox' ) ) {
	/**
	 * Build search filter drop downs
	 *
	 * @package Amnesty\Search
	 *
	 * @param WP_Taxonomy $tax_item taxonomy object
	 *
	 * @return string
	 */
	function build_filter_checkbox( $tax_item ) {
		$queried = query_var_to_array( "q{$tax_item->name}" );
		$args    = [ 'taxonomy' => $tax_item->name ];

		// prevent retrieval of hidden locations
		if ( ( get_option( 'amnesty_location_slug' ) ?: 'location' ) === $args['taxonomy'] ) {
			$args['meta_key']     = 'hidden';
			$args['meta_compare'] = 'NOT EXISTS';
		}

		$terms = get_terms( $args );

		if ( count( $terms ) < 1 ) {
			return '';
		}

		$x  = sprintf( '<div class="checkbox-dropdownList-trigger" role="listbox" aria-labelledby="dropdown-%1$s">', esc_attr( $tax_item->name ) );
		$x .= sprintf( '<div id="dropdown-%1$s" class="checkbox-dropdown" tabindex="0">%2$s</div>', esc_attr( $tax_item->name ), esc_html( $tax_item->label ) );
		$x .= '<ul class="checkbox-dropdownList" role="group">';

		foreach ( $terms as $term ) {
			$selected = in_array( $term->term_id, $queried, true );
			$checked  = checked( $selected, true, false );

			$x .= sprintf( '<li tabindex="0" role="option" aria-selected="%s">', esc_attr( $selected ? 'true' : 'false' ) );
			$x .= '<label>';
			$x .= sprintf(
				'<input type="checkbox" name="q%4$s[]" value="%1$s" %3$s><span>%2$s</span>',
				esc_attr( $term->term_id ),
				esc_html( $term->name ),
				$checked,
				esc_attr( $tax_item->name )
			);
			$x .= '</label>';
			$x .= '</li>';
		}

		$x .= '</ul>';
		$x .= '</div>';

		return $x;
	}
}
