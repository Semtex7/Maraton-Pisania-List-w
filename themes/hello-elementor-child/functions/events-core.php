<?php

// 1. REGISTER CUSTOM POST TYPE

function register_events_cpt() {
    $labels = array(
        'name'                  => 'Wydarzenia',
        'singular_name'         => 'Wydarzenie',
        'menu_name'             => 'Wydarzenia na mapie',
        'add_new'               => 'Dodaj nowe',
        'add_new_item'          => 'Dodaj nowe wydarzenie',
        'edit_item'             => 'Edytuj wydarzenie',
        'all_items'             => 'Wszystkie wydarzenia',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 20, 
        'menu_icon'          => 'dashicons-location',
        'supports'           => array( 'title', 'custom-fields' ),
        'has_archive'        => true,
    );

    // Keep 'wydarzenie' to maintain database compatibility
    register_post_type( 'wydarzenie', $args );
}

add_action( 'init', 'register_events_cpt' );


// 1a. EVENT DATES
// The add-event form stores date and time separately (ACF fields data_rozpoczecia/data_zakonczenia
// + godzina_*), the panel edit form stores data_czas_* (Y-m-d H:i:s). Both are written on save;
// this helper reads whichever exists. Returns 'Y-m-d H:i:s', 'Y-m-d' (date only) or ''.

function maraton_get_event_datetime( $post_id, $which = 'start' ) {
    $prefix = ( 'end' === $which ) ? 'zakonczenia' : 'rozpoczecia';

    $combined = get_post_meta( $post_id, 'data_czas_' . $prefix, true );
    if ( ! empty( $combined ) ) {
        return $combined;
    }

    $date = get_post_meta( $post_id, 'data_' . $prefix, true );
    if ( empty( $date ) ) {
        return '';
    }

    $date = substr( $date, 0, 10 ); // Some entries contain "Y-m-d 00:00:00"
    $time = get_post_meta( $post_id, 'godzina_' . $prefix, true );

    return preg_match( '/^\d{1,2}:\d{2}/', (string) $time ) ? $date . ' ' . substr( $time, 0, 5 ) . ':00' : $date;
}


// 1b. ONE-TIME DATA MIGRATIONS

add_action( 'init', 'maraton_run_event_migrations', 20 );

function maraton_run_event_migrations() {
    $version = (int) get_option( 'maraton_events_migration', 0 );

    // 1: the "visible on map" flag was introduced after events had been created. Events without
    // the flag were shown on the map before, so they keep that behaviour (explicit '0' is left alone).
    if ( $version < 1 ) {
        global $wpdb;
        $ids = $wpdb->get_col(
            "SELECT p.ID FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'widoczne_na_mapie'
             WHERE p.post_type = 'wydarzenie' AND m.meta_id IS NULL"
        );
        foreach ( $ids as $id ) {
            update_post_meta( (int) $id, 'widoczne_na_mapie', 1 );
        }
        delete_transient( 'maraton_events_map_data' );
        update_option( 'maraton_events_migration', 1 );
    }
}


// 2. SECURITY & ADMIN BAR TWEAKS

add_action('admin_init', 'block_wp_admin_access');

function block_wp_admin_access() {
    if ( defined('DOING_AJAX') && DOING_AJAX ) {
        return;
    }

    $current_user = wp_get_current_user();

    if ( isset($current_user->roles[0]) && $current_user->roles[0] == 'subscriber' ) {
        wp_redirect( home_url('/panel/') );
        exit;
    }
}

add_action('after_setup_theme', 'hide_admin_bar');

function hide_admin_bar() {
    if ( ! current_user_can('manage_options') ) {
        show_admin_bar(false);
    }
}