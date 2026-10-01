<?php
/**
 * Enqueue scripts and styles for WordPress Frontend View.
 */
function maraton_wp_enqueue_front()
{
    // WP ENQUEUE CSS
    wp_enqueue_style(
        'maraton-css-front',
        get_stylesheet_directory_uri() . '/dist/front.min.css',
        [],
        MARATON_VERSION
    );
    wp_enqueue_style('maraton-style', get_stylesheet_directory_uri() . '/style.css', [], MARATON_VERSION);

    // WP ENQUEUE JS
    wp_enqueue_script('jquery');  // Ensure WordPress loads jQuery before your script

    wp_enqueue_script(
        'maraton-js-front',
        get_stylesheet_directory_uri() . '/dist/para.min.js',
        ['jquery'],
        MARATON_VERSION,
        true
    );

    // Localize ajaxurl for frontend
    wp_localize_script('maraton-js-front', 'kkAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
}
add_action('wp_enqueue_scripts', 'maraton_wp_enqueue_front', 999);

/**
 * Enqueue scripts and styles for the WordPress Admin Area.
 */
function maraton_wp_enqueue_admin()
{
    assert(is_admin());

    /* == WP ENQUEUE CSS == */
    wp_enqueue_style(
        'maraton-css-admin',
        get_stylesheet_directory_uri() . '/dist/admin.min.css',
        [],
        MARATON_VERSION
    );

    /* == WP ENQUEUE JS == */
    wp_enqueue_script(
        'maraton-js-admin',
        get_stylesheet_directory_uri() . '/dist/paraadmin.min.js',
        ['jquery'],
        MARATON_VERSION,
        false
    );
}
// add_action('admin_enqueue_scripts', 'maraton_wp_enqueue_admin', 999);

/**
 * Load the same CSS inside the Elementor preview iframe (frontend side).
 */
function maraton_load_elementor_preview_styles() {
    if ( isset( $_GET['elementor-preview'] ) ) {
        $post_id = (int) $_GET['elementor-preview'];
        $target_posts = [2358, 4289, 6329, 6475];

        if ( in_array( $post_id, $target_posts, true ) ) {
            wp_enqueue_style(
                'maraton-elementor-preview-fixes',
                get_stylesheet_directory_uri() . '/assets/css/elementor-editor-fixes.css',
                [],
                '1.0'
            );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'maraton_load_elementor_preview_styles', 999 );

