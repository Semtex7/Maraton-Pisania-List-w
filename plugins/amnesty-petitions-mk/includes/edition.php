<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/* ====================================================================
 * EDITION (MARATHON YEAR) DATE RANGE
 * Global range set in Petitions -> Ustawienia -> Edycja. Used by the
 * events counter, the letters counter and the "sign all" option.
 * ==================================================================== */

/**
 * Converts a stored Y-m-d option into a WP_Date_Query array.
 */
function amnesty_edition_date_parts( $date ) {
    if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $m ) ) {
        return null;
    }
    return array( 'year' => (int) $m[1], 'month' => (int) $m[2], 'day' => (int) $m[3] );
}

/**
 * date_query for posts published within the current edition.
 * Falls back to the current calendar year when no range is configured.
 */
function amnesty_get_edition_date_query() {
    $start = amnesty_edition_date_parts( get_option( 'amnesty_edition_start', '' ) );
    $end   = amnesty_edition_date_parts( get_option( 'amnesty_edition_end', '' ) );

    if ( ! $start && ! $end ) {
        return array( array( 'year' => (int) current_time( 'Y' ) ) );
    }

    $range = array( 'inclusive' => true );
    if ( $start ) {
        $range['after'] = $start;
    }
    if ( $end ) {
        $range['before'] = $end; // Inclusive "before" with a date covers the whole day.
    }

    return array( $range );
}

/**
 * IDs of published petitions from the current edition.
 */
function amnesty_get_edition_petition_ids() {
    return get_posts( array(
        'post_type'      => 'amnesty_petition',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'date_query'     => amnesty_get_edition_date_query(),
        'fields'         => 'ids',
    ) );
}

/**
 * Letters in the current edition: signatures from the table + offline letters.
 */
function amnesty_count_edition_letters() {
    global $wpdb;

    $petition_ids = amnesty_get_edition_petition_ids();
    if ( empty( $petition_ids ) ) {
        return 0;
    }

    $table_name   = $wpdb->prefix . 'amnesty_signatures';
    $ids_string   = implode( ',', array_map( 'intval', $petition_ids ) );
    $total        = (int) $wpdb->get_var( "SELECT COUNT(id) FROM $table_name WHERE petition_id IN ($ids_string)" );

    foreach ( $petition_ids as $petition_id ) {
        $total += (int) get_post_meta( $petition_id, '_amnesty_petition_extra_letters', true );
    }

    return $total;
}

/**
 * Published events (CPT "wydarzenie") created within the current edition.
 */
function amnesty_count_edition_events() {
    $query = new WP_Query( array(
        'post_type'      => 'wydarzenie',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'date_query'     => amnesty_get_edition_date_query(),
        'no_found_rows'  => false,
    ) );

    return (int) $query->found_posts;
}
