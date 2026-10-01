<?php
define('MARATON_VERSION', '1.1');

// Include classes
require_once get_stylesheet_directory() . '/classes/elementor_controls.php';

$maraton_modules = array(
    'functions/enqueue-scripts.php',
    'functions/custom-functions.php',
    'functions/events-core.php',
    'functions/events-auth.php',
    'functions/events-form.php',
    'functions/events-map.php',
    'functions/elementor-widgets-manager.php',
    'functions/events-panel.php',
    'functions/events-materials.php',
    'functions/events-reports.php',
    'functions/events-shipping-api.php',
    'functions/shortcodes.php',
);

foreach ( $maraton_modules as $module ) {
    $filepath = get_stylesheet_directory() . '/' . $module;
    
    if ( file_exists( $filepath ) ) {
        require_once $filepath;
    } else {
        error_log( 'Nie udało się załadować modułu Maratonu: ' . $filepath );
    }
}

// Initialize Elementor extension class
new \Maraton\Maraton_Elementor_Extension();