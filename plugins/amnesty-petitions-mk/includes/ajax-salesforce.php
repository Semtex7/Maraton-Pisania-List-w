<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// --- NIESTANDARDOWA FUNKCJA LOGUJĄCA ---
function amnesty_custom_log( $message ) {
    // Tworzy ścieżkę do pliku amnesty-debug.log w głównym folderze wtyczki
    $log_file = AMNESTY_PETITIONS_PATH . 'amnesty-debug.log';
    $time = current_time( 'mysql' );
    // Zapisuje wiadomość do pliku, dodając nową linię na końcu (FILE_APPEND zapobiega nadpisywaniu)
    file_put_contents( $log_file, "[$time] $message\n", FILE_APPEND );
}

// Handle Petition Submission
add_action( 'wp_ajax_submit_amnesty_petition', 'amnesty_petition_handle_submission' );
add_action( 'wp_ajax_nopriv_submit_amnesty_petition', 'amnesty_petition_handle_submission' );

function amnesty_petition_handle_submission() {
    // Verify nonce for security
    if ( ! isset( $_POST['amnesty_sign_nonce'] ) || ! wp_verify_nonce( $_POST['amnesty_sign_nonce'], 'amnesty_sign_petition' ) ) {
        wp_send_json_error( 'Błąd bezpieczeństwa formularza.' );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'amnesty_signatures';

    // Sanitize standard inputs
    $main_petition_id   = intval( $_POST['petition_id'] );
    $first_name         = sanitize_text_field( wp_unslash( $_POST['first_name'] ) );
    $last_name          = sanitize_text_field( wp_unslash( $_POST['last_name'] ) );
    $email              = sanitize_email( wp_unslash( $_POST['email'] ) );
    $phone              = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $city               = isset( $_POST['city'] ) ? sanitize_text_field( mb_convert_case(trim(wp_unslash( $_POST['city'] )), MB_CASE_TITLE, "UTF-8") ) : '';
    $campaign_consent   = isset( $_POST['campaign_consent'] ) ? 1 : 0;
    $newsletter_consent = isset( $_POST['newsletter_consent'] ) ? 1 : 0;
    
    // Check if user wants to sign all petitions
    $sign_all           = isset( $_POST['sign_all'] ) ? 1 : 0;
    $letter_type        = isset( $_POST['letter_type'] ) ? sanitize_text_field( wp_unslash( $_POST['letter_type'] ) ) : 'appeal';
    $custom_message     = isset( $_POST['custom_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_message'] ) ) : '';

    if ( empty( $first_name ) || empty( $last_name ) || empty( $email ) ) {
        wp_send_json_error( 'Wypełnij wymagane pola.' );
    }
    if ( ! is_email( $email ) ) {
        wp_send_json_error( 'Popraw adres e-mail.' );
    }

    // Determine which petitions to process
    $petitions_to_process = array( $main_petition_id );
    
    if ( $sign_all ) {
        // Fetch all published petitions
        $all_petitions = get_posts( array(
            'post_type'      => 'amnesty_petition',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids'
        ) );
        if ( ! empty( $all_petitions ) ) {
            $petitions_to_process = $all_petitions;
        }
    }

    $success_count = 0;
    $already_signed_main = false;

    // Loop through target petitions
    foreach ( $petitions_to_process as $petition_id ) {
        // Check for duplicates isolated to the current petition in the loop
        $already_signed = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE petition_id = %d AND email = %s", $petition_id, $email ) );
        
        if ( $already_signed ) {
            if ( $petition_id === $main_petition_id ) {
                $already_signed_main = true;
            }
            continue; // Skip this petition and move to the next one
        }

        // Insert signature
        $inserted = $wpdb->insert( 
            $table_name, 
            array( 
                'petition_id'        => $petition_id, 
                'first_name'         => $first_name, 
                'last_name'          => $last_name, 
                'email'              => $email, 
                'phone'              => $phone, 
                'city'               => $city, 
                'letter_type'        => $letter_type, 
                'custom_message'     => $custom_message, 
                'campaign_consent'   => $campaign_consent, 
                'newsletter_consent' => $newsletter_consent 
            ) 
        );

        if ( $inserted ) {
            $success_count++;
            $record_id = $wpdb->insert_id; 
            $topic_name = get_post_meta( $petition_id, '_amnesty_petition_topic_name', true );
            
            // Push to Salesforce if topic exists
            if ( ! empty( $topic_name ) ) {
                $sf_success = amnesty_sf_push_signature_to_topic( $first_name, $last_name, $email, $phone, $city, $topic_name );
                if ( $sf_success ) {
                    $wpdb->update( $table_name, array( 'salesforce_synced' => 1 ), array( 'id' => $record_id ) );
                } else {
                    amnesty_custom_log( "SF Sync Failed for email: $email on topic: $topic_name" );
                }
            }
        }
    }

    if ( $already_signed_main && $success_count === 0 && ! $sign_all ) {
        wp_send_json_error( 'Głos został już oddany z tego adresu e-mail.' );
    } elseif ( $success_count > 0 ) {
        wp_send_json_success( 'Sukces!' );
    } else {
        wp_send_json_error( 'Już wsparłeś wszystkie wybrane petycje z tego adresu e-mail.' );
    }
}

// --- Salesforce Logic Handlers ---

function amnesty_sf_run_query( $query, $instance_url, $access_token ) {
    $response = wp_remote_get( 
        $instance_url . '/services/data/v58.0/query/?q=' . urlencode( $query ), 
        array( 'headers' => array( 'Authorization' => 'Bearer ' . $access_token ), 'timeout' => 15 ) 
    );
    
    // Check for standard WordPress HTTP errors
    if ( is_wp_error( $response ) ) {
        amnesty_custom_log( 'Salesforce Query WP Error: ' . $response->get_error_message() );
        return false;
    }
    
    $body = json_decode( wp_remote_retrieve_body( $response ) );
    
    // Check if Salesforce returned an array of error objects (e.g., [{"errorCode": "NOT_FOUND"}])
    if ( is_array( $body ) && isset( $body[0]->errorCode ) ) {
        amnesty_custom_log( 'Salesforce Query API Array Error: ' . wp_remote_retrieve_body( $response ) );
        return false;
    }
    
    // Check if Salesforce returned a single error object (e.g., {"error": "invalid_grant"})
    if ( is_object( $body ) && isset( $body->error ) ) {
        amnesty_custom_log( 'Salesforce Query API Object Error: ' . wp_remote_retrieve_body( $response ) );
        return false;
    }
    
    // Safely verify if it is an object and contains the 'records' array before accessing it
    return ( is_object( $body ) && isset( $body->records ) && is_array( $body->records ) && count( $body->records ) > 0 ) ? $body->records[0] : false;
}

function amnesty_sf_create_record( $object_name, $data, $instance_url, $access_token ) {
    $response = wp_remote_post( 
        $instance_url . '/services/data/v58.0/sobjects/' . $object_name . '/', 
        array( 
            'headers' => array( 'Authorization' => 'Bearer ' . $access_token, 'Content-Type'  => 'application/json' ), 
            'body'    => wp_json_encode( $data ),
            'timeout' => 15
        ) 
    );
    
    // Check for standard WordPress HTTP errors
    if ( is_wp_error( $response ) ) {
        amnesty_custom_log( 'Salesforce Create WP Error (' . $object_name . '): ' . $response->get_error_message() );
        return false;
    }
    
    $body = json_decode( wp_remote_retrieve_body( $response ) );
    
    // Check if Salesforce returned an array of error objects
    if ( is_array( $body ) && isset( $body[0]->errorCode ) ) {
        amnesty_custom_log( 'Salesforce Create API Array Error (' . $object_name . '): ' . wp_remote_retrieve_body( $response ) );
        return false;
    }

    // Check if Salesforce returned a single error object
    if ( is_object( $body ) && isset( $body->error ) ) {
        amnesty_custom_log( 'Salesforce Create API Object Error (' . $object_name . '): ' . wp_remote_retrieve_body( $response ) );
        return false;
    }
    
    // Safely return the ID if it exists in the object
    if ( is_object( $body ) && isset( $body->id ) ) {
        return $body->id;
    }
    
    // Fallback log if the response format is entirely unexpected
    amnesty_custom_log( 'Salesforce Create Unknown Error (' . $object_name . '): ' . wp_remote_retrieve_body( $response ) );
    return false;
}

function amnesty_sf_push_signature_to_topic( $first_name, $last_name, $email, $phone, $city, $topic_name ) {
    $instance_url = rtrim( get_option( 'amnesty_sf_instance_url' ), '/' );
    $client_id    = get_option( 'amnesty_sf_client_id' );
    $secret       = get_option( 'amnesty_sf_client_secret' );
    
    // Check if API credentials exist
    if ( empty( $instance_url ) || empty( $client_id ) || empty( $secret ) ) {
        amnesty_custom_log( 'Salesforce Auth Error: Missing API credentials.' );
        return false;
    }

    // Authenticate and get the access token
    $token_res = wp_remote_post( 
        $instance_url . "/services/oauth2/token", 
        array( 'body' => array( 'grant_type' => 'client_credentials', 'client_id' => $client_id, 'client_secret' => $secret ) ) 
    );
    
    if ( is_wp_error( $token_res ) ) {
        amnesty_custom_log( 'Salesforce Token WP Error: ' . $token_res->get_error_message() );
        return false;
    }
    
    $token_body = json_decode( wp_remote_retrieve_body( $token_res ) );
    
    if ( ! isset( $token_body->access_token ) ) {
        amnesty_custom_log( 'Salesforce Token API Error: ' . wp_remote_retrieve_body( $token_res ) );
        return false;
    }
    
    $access_token = $token_body->access_token;

    // 1. Check if the Topic exists in Salesforce
    $topic = amnesty_sf_run_query( "SELECT Id FROM Topic WHERE Name = '" . esc_sql( $topic_name ) . "' LIMIT 1", $instance_url, $access_token );
    
    $topic_id = false;
    
    if ( $topic ) {
        // Topic exists, grab its ID
        $topic_id = $topic->Id;
    } else {
        // Topic does not exist, create it dynamically
        $topic_id = amnesty_sf_create_record( 
            'Topic', 
            array( 'Name' => $topic_name ), 
            $instance_url, 
            $access_token 
        );
        
        if ( ! $topic_id ) {
            amnesty_custom_log( 'Salesforce Error: Failed to create a new Topic dynamically: ' . $topic_name );
            return false;
        }
    }

    // 2. Query for Contact, create if missing
    $contact = amnesty_sf_run_query( "SELECT Id FROM Contact WHERE Email = '" . esc_sql( $email ) . "' LIMIT 1", $instance_url, $access_token );
    
    if ( $contact ) {
        $contact_id = $contact->Id;
    } else {
        $contact_id = amnesty_sf_create_record( 
            'Contact', 
            array( 'FirstName' => $first_name, 'LastName' => $last_name, 'Email' => $email, 'Phone' => $phone, 'MailingCity' => $city ), 
            $instance_url, 
            $access_token 
        );
    }
    
    if ( ! $contact_id ) {
        amnesty_custom_log( 'Salesforce Error: Failed to find or create Contact for email: ' . $email );
        return false;
    }

    // 3. Assign Contact to the Topic using the ID (either found or newly created)
    $assignment = amnesty_sf_create_record( 
        'TopicAssignment', 
        array( 'TopicId' => $topic_id, 'EntityId' => $contact_id ), 
        $instance_url, 
        $access_token 
    );
    
    if ( ! $assignment ) {
         amnesty_custom_log( 'Salesforce Error: Failed to create TopicAssignment for email: ' . $email );
         return false;
    }
    
    return true;
}