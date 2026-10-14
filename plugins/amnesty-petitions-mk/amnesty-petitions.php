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
require_once AMNESTY_PETITIONS_PATH . 'includes/edition.php';
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

/* ====================================================================
 * PETITION ARCHIVE (/petycja/) = LIST OF CURRENT ACTIONS
 * ==================================================================== */

add_filter( 'archive_template', 'amnesty_petition_archive_template' );

function amnesty_petition_archive_template( $archive_template ) {
    if ( is_post_type_archive( 'amnesty_petition' ) ) {
        $theme_file = locate_template( array( 'archive-amnesty_petition.php' ) );
        return $theme_file ? $theme_file : AMNESTY_PETITIONS_PATH . 'templates/archive-amnesty_petition.php';
    }

    return $archive_template;
}

// Only petitions of the current edition, all on one page
add_action( 'pre_get_posts', 'amnesty_petition_archive_query' );

function amnesty_petition_archive_query( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'amnesty_petition' ) ) {
        return;
    }

    $query->set( 'date_query', amnesty_get_edition_date_query() );
    $query->set( 'posts_per_page', -1 );
}

// URL of the list of current actions ("zobacz wszystkie" next to "sign all")
function amnesty_get_all_petitions_url() {
    $url = get_option( 'amnesty_link_all_petitions', '' );
    return $url ? $url : get_post_type_archive_link( 'amnesty_petition' );
}

add_action( 'wp_enqueue_scripts', 'amnesty_petitions_enqueue_assets' );

function amnesty_petitions_enqueue_assets() {
    
    // Register the counter script globally so Elementor can request it when the widget is present
    $counter_js_path = 'assets/js/amnesty-counter.js';
    $counter_js_absolute = AMNESTY_PETITIONS_PATH . $counter_js_path;
    $counter_js_version = file_exists( $counter_js_absolute ) ? filemtime( $counter_js_absolute ) : AMNESTY_PETITIONS_VERSION;

    wp_register_script(
        'amnesty-counter-script',
        AMNESTY_PETITIONS_URL . $counter_js_path,
        array(), // No dependencies needed for IntersectionObserver
        $counter_js_version,
        true // Load in footer
    );

    // Petition styles are also needed on the petition archive (cards grid)
    if ( is_post_type_archive( 'amnesty_petition' ) ) {
        $css_relative_path = 'assets/css/petitions-frontend.css';
        $css_absolute_path = AMNESTY_PETITIONS_PATH . $css_relative_path;
        wp_enqueue_style(
            'amnesty-petitions-style',
            AMNESTY_PETITIONS_URL . $css_relative_path,
            array(),
            file_exists( $css_absolute_path ) ? filemtime( $css_absolute_path ) : AMNESTY_PETITIONS_VERSION
        );
    }

    // Enqueue the main petition styles and scripts ONLY on single petition pages
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
        
        // Pass necessary variables to frontend JS
        wp_localize_script( 'amnesty-petitions-script', 'amnestyAjax', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'amnesty_sign_petition' ),
            'errorMsg' => esc_html__( 'Błąd wysyłania', 'amnesty-petitions' ),
            'serverErrorMsg' => esc_html__( 'Wystąpił błąd serwera. Spróbuj ponownie później.', 'amnesty-petitions' )
        ) );
    }
}