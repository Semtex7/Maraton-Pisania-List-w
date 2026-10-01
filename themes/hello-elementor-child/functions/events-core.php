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