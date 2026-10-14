<?php

function restrict_panel_access() {
    
    if ( isset( $_GET['elementor-preview'] ) || ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' ) ) {
        return; 
    }

    if ( is_page( 'panel' ) && ! is_user_logged_in() ) {

        // Site login page with [event_login]; after login the user is sent back to /panel/
        wp_safe_redirect( home_url( '/zaloguj-sie/' ) );
        exit;
    }
}

add_action( 'template_redirect', 'restrict_panel_access' );

// Sender name of e-mails sent by WordPress (activation, password reset). Only the core default
// "WordPress" is replaced, so a name set by an SMTP plugin still takes precedence.
// The sender address is not changed here: it has to match a domain authorised to send
// (SPF/DKIM), which is configured in the SMTP plugin — see docs/deployment.md.
add_filter( 'wp_mail_from_name', 'maraton_mail_from_name' );

function maraton_mail_from_name( $name ) {
    return ( 'WordPress' === $name ) ? 'Maraton Pisania Listów – Amnesty International' : $name;
}