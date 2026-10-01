<?php

/**
 * Miscellaneous taxonomy filters
 */

declare( strict_types = 1 );

/**
 * Allow some post types access to the category taxonomy
 */
add_action( 'init', function () {
	register_taxonomy_for_object_type( 'category', 'attachment' );
	register_taxonomy_for_object_type( 'category', 'page' );
} );

/**
 * Add extra post types to campaign taxonomy
 */
add_filter( 'amnesty_taxonomy_campaign_object_types', function ( array $types = [] ): array {
	return array_merge( $types, [ 'attachment', get_option( 'aip_petition_slug' ) ?: 'petition' ] );
} );

/**
 * Add extra post types to locations taxonomy
 */
add_filter( 'amnesty_taxonomy_location_object_types', function ( array $types = [] ): array {
	return array_merge( $types, [ get_option( 'aip_petition_slug' ) ?: 'petition' ] );
} );

/**
 * Add extra post types to resource-type taxonomy
 */
add_filter( 'amnesty_taxonomy_resource-type_object_types', function ( array $types = [] ): array {
	return array_merge( $types, [ get_option( 'aip_petition_slug' ) ?: 'petition' ] );
} );

/**
 * Add extra post types to topics type taxonomy
 */
add_filter( 'amnesty_taxonomy_topic_object_types', function ( array $types = [] ): array {
	return array_merge( $types, [ 'attachment', get_option( 'aip_petition_slug' ) ?: 'petition' ] );
} );

/**
 * Add currently-requested category to the filter query var list
 */
add_filter( 'request', function ( array $query_vars ): array {
	// this is fired prior to `is_category()` being populated with the correct value
	if ( ! isset( $query_vars['category_name'] ) ) {
		return $query_vars;
	}

	$id = get_cat_ID( $query_vars['category_name'] );

	$query_vars['qcategory'] = strval( absint( $id ) );

	return $query_vars;
} );
