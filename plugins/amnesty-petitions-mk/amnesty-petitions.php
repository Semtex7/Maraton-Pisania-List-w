<?php
/**
 * Plugin Name: Amnesty Petitions
 * Description: Independent petition management and signature collection system, integrated with Salesforce.
 * Version: 2.0.0
 * Author: Your Team
 * Text Domain: amnesty-petitions
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin global constants
define( 'AMNESTY_PETITIONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'AMNESTY_PETITIONS_URL', plugin_dir_url( __FILE__ ) );
define( 'AMNESTY_PETITIONS_VERSION', '2.0.8' );

// Include module files
require_once AMNESTY_PETITIONS_PATH . 'includes/database.php';
require_once AMNESTY_PETITIONS_PATH . 'includes/cpt-meta.php';
require_once AMNESTY_PETITIONS_PATH . 'includes/settings.php';
require_once AMNESTY_PETITIONS_PATH . 'includes/shortcodes.php';
require_once AMNESTY_PETITIONS_PATH . 'includes/ajax-salesforce.php';
// require_once AMNESTY_PETITIONS_PATH . 'includes/ajax-map.php';
require_once AMNESTY_PETITIONS_PATH . 'includes/admin-signatures.php';
require_once AMNESTY_PETITIONS_PATH . 'includes/elementor-widgets.php';


require_once AMNESTY_PETITIONS_PATH . 'templates/template-tags.php';

/* ====================================================================
 * PLUGIN TEMPLATE LOADER
 * ==================================================================== */

add_filter( 'single_template', 'amnesty_petition_single_template' );

function amnesty_petition_single_template( $single_template ) {
    global $post;

    if ( 'amnesty_petition' === $post->post_type ) {
        
        $theme_file = locate_template( array( 'single-amnesty_petition.php' ) );
        
        if ( $theme_file ) {
            return $theme_file;
        } else {
            return AMNESTY_PETITIONS_PATH . 'templates/single-amnesty_petition.php';
        }
    }

    return $single_template;
}

add_action( 'wp_enqueue_scripts', 'amnesty_petitions_enqueue_assets' );

function amnesty_petitions_enqueue_assets() {
    if ( is_singular( 'amnesty_petition' ) ) {
        $css_relative_path = 'assets/css/petitions-frontend.css';
        $css_absolute_path = AMNESTY_PETITIONS_PATH . $css_relative_path;
        $css_version = file_exists( $css_absolute_path ) ? filemtime( $css_absolute_path ) : AMNESTY_PETITIONS_VERSION;

        wp_enqueue_style( 
            'amnesty-petitions-style', 
            AMNESTY_PETITIONS_URL . $css_relative_path, 
            array(), 
            $css_version 
        );

        $js_relative_path = 'assets/js/amnesty-frontend.js'; 
        $js_absolute_path = AMNESTY_PETITIONS_PATH . $js_relative_path;
        $js_version = file_exists( $js_absolute_path ) ? filemtime( $js_absolute_path ) : AMNESTY_PETITIONS_VERSION;

        wp_enqueue_script( 
            'amnesty-petitions-script', 
            AMNESTY_PETITIONS_URL . $js_relative_path, 
            array(), 
            $js_version, 
            true 
        );
        wp_localize_script( 'amnesty-petitions-script', 'amnestyAjax', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'amnesty_sign_petition' ),
            'errorMsg' => __( 'Błąd wysyłania', 'amnesty-petitions' ),
            'serverErrorMsg' => __( 'Wystąpił błąd serwera. Spróbuj ponownie później.', 'amnesty-petitions' )
        ) );
    }
}