<?php

/**
 * Register a field to handle precedence when retrieving taxonomy terms
 */

declare( strict_types = 1 );

add_action( 'amnesty_register_taxonomy', function ( $cmb2 ) {
	$taxonomies = get_taxonomies( [ 'amnesty' => true ], 'objects' );

	if ( empty( $taxonomies ) ) {
		return;
	}

	$options = [];

	foreach ( $taxonomies as $taxonomy ) {
		$options[ $taxonomy->name ] = $taxonomy->label;
	}

	amnesty_cmb2_wrap_open( $cmb2, /* translators: [admin] */ __( 'Taxonomy Precedence', 'amnesty' ) );

	$cmb2->add_field( [
		'id'      => 'taxonomy_precedence',
		/* translators: [admin] */
		'name'    => __( 'Taxonomy Precedence', 'amnesty' ),
		/* translators: [admin] */
		'desc'    => __( 'In which order taxonomies should be ranked when retrieving a term to display prominently for an item', 'amnesty' ),
		'type'    => 'order',
		'options' => $options,
	] );

	amnesty_cmb2_wrap_close( $cmb2 );
} );
