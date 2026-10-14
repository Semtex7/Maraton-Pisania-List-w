<?php
add_action('wp_ajax_get_events_map_data', 'maraton_get_events_map_data');
add_action('wp_ajax_nopriv_get_events_map_data', 'maraton_get_events_map_data');

function maraton_get_events_map_data() {
    // Check for cached data
    $cached_data = get_transient('maraton_events_map_data');
    if ( $cached_data !== false ) {
        wp_send_json_success( $cached_data );
    }

    // Prepare query arguments
    $args = array(
        'post_type'      => 'wydarzenie',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    );
    
    $events_query = new WP_Query($args);
    $map_points = array();

    if ( $events_query->have_posts() ) {
        while ( $events_query->have_posts() ) {
            $events_query->the_post();
            $post_id = get_the_ID();
            
            // Validate map visibility toggle
            $show_on_map = get_post_meta($post_id, 'widoczne_na_mapie', true);
            if ( $show_on_map !== '1' ) {
                continue; 
            }

            // Determine if event is open to the public
            $is_public_meta = get_post_meta($post_id, 'wydarzenie_publiczne', true);
            $is_public = ( $is_public_meta == '1' || $is_public_meta === 'tak' ) ? true : false;
            
            // Retrieve coordinates
            $lat = get_post_meta($post_id, 'geo_lat', true);
            $lng = get_post_meta($post_id, 'geo_lng', true);
            if ( empty($lng) ) {
                $lng = get_post_meta($post_id, 'geo_ing', true); 
            }

            if ( ! empty($lat) && ! empty($lng) ) {
                $map_points[] = array(
                    'title'     => get_the_title(),
                    'address'   => get_post_meta($post_id, 'ulica', true) . ', ' . get_post_meta($post_id, 'miejscowosc', true),
                    'lat'       => $lat,
                    'lng'       => $lng,
                    'is_public' => $is_public 
                );
            }
        }
        wp_reset_postdata();
    }

    // Save transient for 1 hour
    set_transient('maraton_events_map_data', $map_points, HOUR_IN_SECONDS);

    wp_send_json_success( $map_points );
}

// 2. AUTOMATYCZNE CZYSZCZENIE CACHE'U

add_action('save_post_wydarzenie', 'maraton_clear_events_map_cache');
function maraton_clear_events_map_cache() {
    delete_transient('maraton_events_map_data');
}

// 3. SHORTCODE: FRONTEND MAPY

add_shortcode('events_map', 'render_events_map');

// [events_map show_all="1" show_all_label="Sprawdź wszystkie"] — the button links to the events list (/wydarzenie/)
function render_events_map( $atts = array() ) {
    $atts = shortcode_atts( array(
        'show_all'       => '1',
        'show_all_label' => __('Sprawdź wszystkie', 'amnestympl'),
    ), $atts, 'events_map' );

    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);

    // Zwracamy tylko czysty szkielet HTML, bez wstrzykiwania JSONa
    $html = '<div class="event-map-container" style="max-width: 100%; margin-bottom: 20px;">';
    $html .= '<input type="text" id="map-search-bar" placeholder="Wyszukaj miasto, ulicę lub nazwę organizatora..." style="width: 100%; padding: 12px; margin-bottom: 15px; border: 2px solid #0073aa; border-radius: 5px; font-size: 16px; box-sizing: border-box;">';
    $html .= '<div id="events-map-canvas" style="height: 500px; width: 100%; z-index: 1; border: 1px solid #ccc; border-radius: 5px;"></div>';

    if ( '1' === (string) $atts['show_all'] ) {
        $html .= '<div class="events-map-all"><a class="events-map-all-link" href="' . esc_url( get_post_type_archive_link('wydarzenie') ) . '">' . esc_html( $atts['show_all_label'] ) . '</a></div>';
    }

    $html .= '</div>';

    return $html;
}