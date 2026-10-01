<?php

declare( strict_types = 1 );

add_action( 'taxonomy_location_custom_fields', function ( $metabox ) {
	/* translators: [admin] */
	amnesty_cmb2_wrap_open( $metabox, __( 'Death Penalty', 'amnesty' ), false, false, [
		'show-on' => 'type',
		'show-if' => 'default',
	] );

	$metabox->add_field( [
		'id'               => 'death_penalty',
		/* translators: [admin] */
		'name'             => __( 'Status in Law', 'amnesty' ),
		'type'             => 'select',
		'show_option_none' => true,
		'options'          => get_death_penalty_status_labels(),
	] );

	amnesty_cmb2_wrap_close( $metabox );
}, 10 );

register_rest_field( 'location', 'death_penalty_status', [
	'get_callback' => function ( array $term ) {
		return get_death_penalty_status( (object) $term );
	},
] );
