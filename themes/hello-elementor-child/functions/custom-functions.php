<?php

function restrict_panel_access() {
    
    if ( isset( $_GET['elementor-preview'] ) || ( isset( $_GET['action'] ) && $_GET['action'] === 'elementor' ) ) {
        return; 
    }

    if ( is_page( 'panel' ) && ! is_user_logged_in() ) {
        
        $login_url = wp_login_url();
        wp_safe_redirect( $login_url );
        exit;
    }
}

add_action( 'template_redirect', 'restrict_panel_access' );