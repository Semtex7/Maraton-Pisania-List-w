<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register AJAX hooks for map data (both logged in and guest users)
add_action( 'wp_ajax_get_amnesty_map_data', 'amnesty_get_map_data' );
add_action( 'wp_ajax_nopriv_get_amnesty_map_data', 'amnesty_get_map_data' );

function amnesty_get_map_data() {
    global $wpdb;
    
    // Safely retrieve petition ID from the GET request
    $petition_id = isset( $_GET['petition_id'] ) ? intval( $_GET['petition_id'] ) : 0;
    
    if ( ! $petition_id ) {
        wp_send_json_error( 'Brak ID petycji' );
    }

    $table_name = $wpdb->prefix . 'amnesty_signatures';
    
    // Group and count signatures by city for the specific petition
    $results = $wpdb->get_results( $wpdb->prepare( 
        "SELECT city, COUNT(id) as count FROM $table_name WHERE petition_id = %d AND city != '' GROUP BY city", 
        $petition_id 
    ));

    // Retrieve cached geo data to minimize OpenStreetMap API calls
    $geo_cache = get_option( 'amnesty_global_geo_cache', array() );
    $final_data = array();
    $updated_cache = false;

    foreach ( $results as $row ) {
        // Clean up the city string for better cache matching (e.g., remove "m.", "gmina")
        $city_cache_key = mb_strtolower( preg_replace( '/\b(m\.|gm\.|gmina|miasto)\s+|\d+/', '', $row->city ), 'UTF-8' ); 
        
        if ( isset( $geo_cache[ $city_cache_key ] ) ) {
            // Use cached coordinates if they exist
            $final_data[] = array( 
                'city'  => ucwords( $row->city ), 
                'count' => $row->count, 
                'lat'   => $geo_cache[ $city_cache_key ]['lat'], 
                'lon'   => $geo_cache[ $city_cache_key ]['lon'] 
            );
        } else {
            // Fetch coordinates from Nominatim API if not in cache
            $response = wp_remote_get( 'https://nominatim.openstreetmap.org/search?format=json&country=Poland&city=' . urlencode( $city_cache_key ), array( 'user-agent' => 'AmnestyPlugin/1.0' ) );
            
            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                
                if ( ! empty( $body ) && isset( $body[0]['lat'] ) ) {
                    // Save new coordinates to cache array
                    $geo_cache[ $city_cache_key ] = array( 
                        'lat' => $body[0]['lat'], 
                        'lon' => $body[0]['lon'] 
                    );
                    
                    // Add point to the final response
                    $final_data[] = array( 
                        'city'  => ucwords( $row->city ), 
                        'count' => $row->count, 
                        'lat'   => $body[0]['lat'], 
                        'lon'   => $body[0]['lon'] 
                    );
                    
                    $updated_cache = true;
                }
            }
        }
    }
    
    // Update the database cache option if new cities were added
    if ( $updated_cache ) {
        update_option( 'amnesty_global_geo_cache', $geo_cache );
    }
    
    // Send the final JSON payload back to the frontend
    wp_send_json_success( $final_data );
}