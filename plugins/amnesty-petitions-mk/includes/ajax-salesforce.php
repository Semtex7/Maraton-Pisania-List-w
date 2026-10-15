<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// --- NIESTANDARDOWA FUNKCJA LOGUJĄCA ---
function amnesty_custom_log( $message ) {
    // Log zawiera dane osobowe, więc nie może trafiać do katalogu pluginu (publiczny i wersjonowany).
    // Domyślnie _wpeprivate (WP Engine blokuje do niego dostęp z sieci); można nadpisać stałą AMNESTY_PETITIONS_LOG_DIR.
    $log_dir = defined( 'AMNESTY_PETITIONS_LOG_DIR' ) ? AMNESTY_PETITIONS_LOG_DIR : ABSPATH . '_wpeprivate';

    if ( ! is_dir( $log_dir ) || ! wp_is_writable( $log_dir ) ) {
        error_log( '[amnesty-petitions] ' . $message );
        return;
    }

    $time = current_time( 'mysql' );
    // FILE_APPEND zapobiega nadpisywaniu
    file_put_contents( trailingslashit( $log_dir ) . 'amnesty-debug.log', "[$time] $message\n", FILE_APPEND | LOCK_EX );
}

// Handle Petition Submission
add_action( 'wp_ajax_submit_amnesty_petition', 'amnesty_petition_handle_submission' );
add_action( 'wp_ajax_nopriv_submit_amnesty_petition', 'amnesty_petition_handle_submission' );

function amnesty_petition_handle_submission() {
    // Verify nonce for security
    if ( ! isset( $_POST['amnesty_sign_nonce'] ) || ! wp_verify_nonce( $_POST['amnesty_sign_nonce'], 'amnesty_sign_petition' ) ) {
        wp_send_json_error( 'Błąd bezpieczeństwa formularza.' );
    }

    // Bot protection (includes/form-protection.php). Bots get a normal "success" so they learn nothing.
    $protection = amnesty_check_petition_submission();
    if ( 'bot' === $protection ) {
        wp_send_json_success( 'Sukces!' );
    } elseif ( '' !== $protection ) {
        wp_send_json_error( $protection );
    }

    // Required consent is also checked server-side (the HTML "required" can be bypassed)
    if ( empty( $_POST['rodo_consent'] ) ) {
        wp_send_json_error( 'Zaznacz wymaganą zgodę na przetwarzanie danych.' );
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
        // Sign all petitions of the current edition; the petition being signed is always included
        $edition_petitions    = array_map( 'intval', amnesty_get_edition_petition_ids() );
        $petitions_to_process = array_values( array_unique( array_merge( $petitions_to_process, $edition_petitions ) ) );
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
        }
    }

    // Salesforce sync runs in the background (includes/salesforce-sync.php), so the visitor does not
    // wait for several API calls per signed petition.
    if ( $success_count > 0 ) {
        amnesty_sf_schedule_sync_soon();
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

// $duplicate_is_success: a DUPLICATE_VALUE error counts as success (e.g. TopicAssignment that already
// exists after a retried sync) and returns the string 'DUPLICATE'.
function amnesty_sf_create_record( $object_name, $data, $instance_url, $access_token, $duplicate_is_success = false ) {
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
        if ( $duplicate_is_success && 'DUPLICATE_VALUE' === $body[0]->errorCode ) {
            return 'DUPLICATE';
        }
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

// OAuth client_credentials. Returns array( instance_url, access_token ) or false (errors are logged).
function amnesty_sf_get_access_token() {
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

    return array( $instance_url, $token_body->access_token );
}

// "j***@example.org" — log entries must not contain full e-mail addresses
function amnesty_mask_email( $email ) {
    $parts = explode( '@', (string) $email, 2 );
    return ( $parts[0] !== '' ? mb_substr( $parts[0], 0, 1 ) : '' ) . '***' . ( isset( $parts[1] ) ? '@' . $parts[1] : '' );
}

// Updates fields of an existing record (PATCH). Returns true on success (HTTP 204).
function amnesty_sf_update_record( $object_name, $record_id, $data, $instance_url, $access_token ) {
    $response = wp_remote_request(
        $instance_url . '/services/data/v58.0/sobjects/' . $object_name . '/' . rawurlencode( $record_id ),
        array(
            'method'  => 'PATCH',
            'headers' => array( 'Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $data ),
            'timeout' => 15,
        )
    );

    if ( is_wp_error( $response ) ) {
        amnesty_custom_log( 'Salesforce Update WP Error (' . $object_name . '): ' . $response->get_error_message() );
        return false;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    if ( $code < 200 || $code >= 300 ) {
        amnesty_custom_log( 'Salesforce Update API Error (' . $object_name . ', HTTP ' . $code . '): ' . wp_remote_retrieve_body( $response ) );
        return false;
    }

    return true;
}

// Contact field (checkbox) that receives the marketing consent from the petition form.
// Set in Petitions → Ustawienia → Salesforce API; empty = consent is not sent.
function amnesty_sf_consent_field() {
    $field = (string) get_option( 'amnesty_sf_consent_field', 'Newsletter_Subscribe__c' );
    return preg_match( '/^[A-Za-z][A-Za-z0-9_]*$/', $field ) ? $field : '';
}

// $auth: array( instance_url, access_token ) reused by the background sync; fetched when null.
// $consent: marketing consent given in the form (sets the consent field to true; never to false,
// because an unticked box is not a withdrawal of an earlier consent).
// Topic and Contact IDs are remembered for the rest of the PHP request (one batch).
function amnesty_sf_push_signature_to_topic( $first_name, $last_name, $email, $phone, $city, $topic_name, $auth = null, $consent = 0 ) {
    static $topic_ids = array(), $contact_ids = array(), $consent_sent = array();

    if ( null === $auth ) {
        $auth = amnesty_sf_get_access_token();
    }
    if ( ! $auth ) {
        return false;
    }
    list( $instance_url, $access_token ) = $auth;

    // 1. Check if the Topic exists in Salesforce
    $topic    = isset( $topic_ids[ $topic_name ] ) ? (object) array( 'Id' => $topic_ids[ $topic_name ] ) : amnesty_sf_run_query( "SELECT Id FROM Topic WHERE Name = '" . esc_sql( $topic_name ) . "' LIMIT 1", $instance_url, $access_token );
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

    $topic_ids[ $topic_name ] = $topic_id;

    // 2. Query for Contact, create if missing
    $email_key = strtolower( $email );
    $contact   = isset( $contact_ids[ $email_key ] ) ? (object) array( 'Id' => $contact_ids[ $email_key ] ) : amnesty_sf_run_query( "SELECT Id FROM Contact WHERE Email = '" . esc_sql( $email ) . "' LIMIT 1", $instance_url, $access_token );
    
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
        amnesty_custom_log( 'Salesforce Error: Failed to find or create Contact for email: ' . amnesty_mask_email( $email ) );
        return false;
    }
    $contact_ids[ $email_key ] = $contact_id;

    // 3. Assign Contact to the Topic using the ID (either found or newly created)
    $assignment = amnesty_sf_create_record(
        'TopicAssignment',
        array( 'TopicId' => $topic_id, 'EntityId' => $contact_id ),
        $instance_url,
        $access_token,
        true // Already assigned (retry) is fine
    );
    
    if ( ! $assignment ) {
         amnesty_custom_log( 'Salesforce Error: Failed to create TopicAssignment for email: ' . amnesty_mask_email( $email ) );
         return false;
    }

    // 4. Marketing consent: once per Contact per batch. A failure (e.g. wrong field name in the settings)
    // is logged but does not fail the signature sync.
    $consent_field = amnesty_sf_consent_field();
    if ( $consent && '' !== $consent_field && empty( $consent_sent[ $contact_id ] ) ) {
        if ( amnesty_sf_update_record( 'Contact', $contact_id, array( $consent_field => true ), $instance_url, $access_token ) ) {
            $consent_sent[ $contact_id ] = true;
        } else {
            amnesty_custom_log( 'Salesforce Error: consent field ' . $consent_field . ' not updated for Contact ' . $contact_id );
        }
    }

    return true;
}

/* ====================================================================
 * SALESFORCE TOPIC SEARCH (petition meta box: "Salesforce Topic Name")
 * ==================================================================== */

// Escapes a value for use inside a quoted SOQL LIKE pattern
function amnesty_sf_escape_like( $value ) {
    return strtr( $value, array( '\\' => '\\\\', "'" => "\\'", '%' => '\\%', '_' => '\\_' ) );
}

// Returns up to 20 Topic names containing $term, or WP_Error. Cached for 10 minutes.
function amnesty_sf_search_topics( $term ) {
    $term      = trim( $term );
    $cache_key = 'amnesty_sf_topics_' . md5( strtolower( $term ) );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $auth = amnesty_sf_get_access_token();
    if ( ! $auth ) {
        return new WP_Error( 'sf_auth', __( 'Brak połączenia z Salesforce (sprawdź dane API w ustawieniach).', 'amnesty-petitions' ) );
    }
    list( $instance_url, $access_token ) = $auth;

    $where = '' === $term ? '' : " WHERE Name LIKE '%" . amnesty_sf_escape_like( $term ) . "%'";
    $soql  = 'SELECT Name FROM Topic' . $where . ' ORDER BY Name LIMIT 20';

    $response = wp_remote_get(
        $instance_url . '/services/data/v58.0/query/?q=' . rawurlencode( $soql ),
        array( 'headers' => array( 'Authorization' => 'Bearer ' . $access_token ), 'timeout' => 15 )
    );
    if ( is_wp_error( $response ) ) {
        amnesty_custom_log( 'Salesforce Topic Search WP Error: ' . $response->get_error_message() );
        return new WP_Error( 'sf_http', __( 'Błąd połączenia z Salesforce.', 'amnesty-petitions' ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ) );
    if ( ! is_object( $body ) || ! isset( $body->records ) || ! is_array( $body->records ) ) {
        amnesty_custom_log( 'Salesforce Topic Search API Error: ' . wp_remote_retrieve_body( $response ) );
        return new WP_Error( 'sf_api', __( 'Salesforce zwrócił błąd.', 'amnesty-petitions' ) );
    }

    $names = array();
    foreach ( $body->records as $record ) {
        if ( isset( $record->Name ) ) {
            $names[] = (string) $record->Name;
        }
    }

    set_transient( $cache_key, $names, 10 * MINUTE_IN_SECONDS );
    return $names;
}

add_action( 'wp_ajax_amnesty_sf_search_topics', 'amnesty_sf_ajax_search_topics' );

function amnesty_sf_ajax_search_topics() {
    if ( ! current_user_can( 'edit_posts' ) || ! check_ajax_referer( 'amnesty_sf_topics', 'nonce', false ) ) {
        wp_send_json_error( __( 'Brak uprawnień.', 'amnesty-petitions' ) );
    }

    $term   = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
    $result = amnesty_sf_search_topics( $term );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    wp_send_json_success( $result );
}
