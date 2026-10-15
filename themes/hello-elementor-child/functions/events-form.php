<?php
// Global variable to store form errors
global $event_form_error;
$event_form_error = '';

// 1. HANDLE EVENT SUBMISSION

add_action('template_redirect', 'handle_event_submission');

function handle_event_submission() {
    global $event_form_error;

    // Check if the form was submitted
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_event']) ) {
        
        // Security check
        if ( ! isset($_POST['event_nonce']) || ! wp_verify_nonce($_POST['event_nonce'], 'save_event_action') ) {
            $event_form_error = esc_html__('Błąd bezpieczeństwa formularza. Odśwież stronę i spróbuj ponownie.', 'amnestympl');
            return;
        }

        // Sanitize incoming fields
        $title      = sanitize_text_field($_POST['organizer_name']);
        $start      = sanitize_text_field($_POST['start_datetime'] ?? '');
        $end        = sanitize_text_field($_POST['end_datetime'] ?? '');
        $street     = sanitize_text_field($_POST['street']);
        $city       = sanitize_text_field($_POST['city']);

        // Validate required fields
        if ( empty($title) || empty($start) || empty($street) || empty($city) ) {
            $event_form_error = esc_html__('Proszę wypełnić wszystkie wymagane pola (w tym ulicę i miejscowość).', 'amnestympl');
            return;
        }

        $date_fields = maraton_event_date_meta($start, $end);
        if ( is_wp_error($date_fields) ) {
            $event_form_error = $date_fields->get_error_message();
            return;
        }

        // --- SMART MAP VALIDATION (Geocoding) ---
        $addresses_to_check = array();
        
        // Remove common street prefixes for better API matching
        $clean_street = trim(preg_replace('/^(ul\.?\s*|ulica\s+|al\.?\s*|aleja\s+|pl\.?\s*|plac\s+)/i', '', $street));
        $addresses_to_check[] = $clean_street . ', ' . $city;
        
        // Try without apartment numbers
        $street_without_apt = trim(explode('/', $clean_street)[0]);
        $addresses_to_check[] = $street_without_apt . ', ' . $city;
        
        // Try removing the building number entirely
        $street_only = trim(preg_replace('/[0-9]+[a-zA-Z]?.*$/', '', $street_without_apt));
        if (!empty($street_only)) { 
            $addresses_to_check[] = $street_only . ', ' . $city; 
        }
        
        // Fallback to just the city
        $addresses_to_check[] = $city;

        $lat = ''; 
        $lng = ''; 
        $address_found = false;
        $addresses_to_check = array_unique($addresses_to_check);

        // Ping Nominatim API to get coordinates
        foreach ($addresses_to_check as $test_address) {
            $api_url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($test_address);
            $response = wp_remote_get($api_url, array('headers' => array('User-Agent' => 'MyEventApp/1.3 (WordPress)')));

            if ( ! is_wp_error($response) ) {
                $body = wp_remote_retrieve_body($response);
                $map_data = json_decode($body);

                if ( ! empty($map_data) ) {
                    $lat = $map_data[0]->lat; 
                    $lng = $map_data[0]->lon;
                    $address_found = true; 
                    break; 
                }
            }
        }

        if ( ! $address_found ) {
            $event_form_error = esc_html__('System map nie mógł zlokalizować podanej miejscowości. Sprawdź, czy nie ma literówek.', 'amnestympl');
            return; 
        }
        
        // --- SAVE TO DATABASE ---
        $post_id = wp_insert_post(array(
            'post_title'    => $title,
            'post_type'     => 'wydarzenie', 
            'post_status'   => 'publish'
        ));

        if ( $post_id ) {
            // Prepare meta fields array (dates in both formats, see maraton_event_date_meta())
            $fields_to_save = $date_fields + array(
                'nazwa_lokalu'         => sanitize_text_field($_POST['venue_name']),
                'ulica'                => $street,
                'miejscowosc'          => $city,
                'kod_pocztowy'         => sanitize_text_field($_POST['zip_code']),
                'wydarzenie_publiczne' => isset($_POST['is_public_event']) ? 1 : 0,
                'widoczne_na_mapie'    => isset($_POST['show_on_map']) ? 1 : 0,
                'dostepnosc'           => isset($_POST['accessibility']) ? 1 : 0,
                'geo_lat'              => $lat,
                'geo_lng'              => $lng
            );

            // Save meta fields
            foreach ($fields_to_save as $meta_key => $meta_value) {
                if ( function_exists('update_field') ) {
                    update_field($meta_key, $meta_value, $post_id);
                } else {
                    update_post_meta($post_id, $meta_key, $meta_value);
                }
            }

            // Redirect back to the panel
            $return_url = wp_get_referer() ? wp_get_referer() : get_permalink();

            // Clean URL from existing hashes before appending new ones
            $clean_url = explode('#', $return_url)[0];

            // The hash opens the "My events" tab (CSS ID of the Elementor tab, see KK.openTabFromHash);
            // [my_events] shows the confirmation for ?added=1
            $redirect_url = add_query_arg('added', '1', $clean_url) . '#zakladka-moje-wydarzenia';
            
            wp_safe_redirect($redirect_url);
            exit; 
        }
    }
}

// 2. SHORTCODE: ADD EVENT FORM

add_shortcode('event_form', 'render_event_form');

function render_event_form() {
    global $event_form_error;

    // Check user authentication
    if ( ! is_user_logged_in() ) {
        return '<div class="alert-message alert-warning">' . esc_html__('Musisz być zalogowany, aby zarejestrować wydarzenie.', 'amnestympl') . '</div>';
    }

    ob_start();

    // The success message (?added=1) is shown by [my_events]

    // Display validation errors
    if ( ! empty($event_form_error) ) {
        echo '<div class="alert-message alert-danger"><strong>' . esc_html__('Błąd walidacji:', 'amnestympl') . '</strong> ' . esc_html($event_form_error) . '</div>';
    }

    // Retain input values on error
    $val_org    = isset($_POST['organizer_name']) ? esc_attr($_POST['organizer_name']) : '';
    $val_street = isset($_POST['street']) ? esc_attr($_POST['street']) : '';
    $val_city   = isset($_POST['city']) ? esc_attr($_POST['city']) : '';
    $val_start  = isset($_POST['start_datetime']) ? esc_attr($_POST['start_datetime']) : '';
    $val_end    = isset($_POST['end_datetime']) ? esc_attr($_POST['end_datetime']) : '';

    ?>
    <form action="" method="post" class="new-event-form">
        <?php wp_nonce_field('save_event_action', 'event_nonce'); ?>

        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Nazwa organizatora / instytucji / grupy', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
            <input type="text" name="organizer_name" class="form-input" placeholder="<?php esc_attr_e('np. Liceum Ogólnokształcące nr 1', 'amnestympl'); ?>" value="<?php echo $val_org; ?>" required>
        </div>

        <h3 class="form-section-title"><?php esc_html_e('Data i godzina maratonu', 'amnestympl'); ?></h3>
        <div class="form-section split-row event-dates">
            <div>
                <label class="form-label"><?php esc_html_e('Rozpoczęcie', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
                <input type="datetime-local" name="start_datetime" class="form-input" value="<?php echo $val_start; ?>" required>
            </div>
            <div>
                <label class="form-label"><?php esc_html_e('Zakończenie', 'amnestympl'); ?></label>
                <input type="datetime-local" name="end_datetime" class="form-input" value="<?php echo $val_end; ?>">
            </div>
        </div>

        <h3 class="form-section-title"><?php esc_html_e('Gdzie odbędzie się wydarzenie', 'amnestympl'); ?></h3>
        
        <div class="form-section checkbox-option">
            <label>
                <!-- Removed 'checked' attribute and added dot in translation string -->
                <input type="checkbox" name="is_public_event">
                <strong><?php esc_html_e('Wydarzenie publiczne (otwarte dla osób z zewnątrz).', 'amnestympl'); ?></strong>
            </label>
        </div>

        <div class="form-section checkbox-option">
            <label>
                <!-- Removed 'checked' attribute -->
                <input type="checkbox" name="show_on_map">
                <strong><?php esc_html_e('Zaznacz, by wydarzenie było widoczne na mapie.', 'amnestympl'); ?></strong>
            </label>
        </div>

        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Nazwa lokalu:', 'amnestympl'); ?></label>
            <input type="text" name="venue_name" class="form-input" placeholder="<?php esc_attr_e('np. Sala gimnastyczna', 'amnestympl'); ?>">
        </div>
        
        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Ulica i nr lokalu:', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
            <input type="text" name="street" class="form-input" placeholder="<?php esc_attr_e('np. ul. Poznańska 10', 'amnestympl'); ?>" value="<?php echo $val_street; ?>" required>
            <span class="field-description field-description-inline"><?php esc_html_e('Staraj się podawać samą ulicę i numer budynku.', 'amnestympl'); ?></span>
        </div>
        
        <div class="form-section split-row">
            <div>
                <label class="form-label"><?php esc_html_e('Miejscowość:', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
                <input type="text" name="city" class="form-input" placeholder="<?php esc_attr_e('np. Gdynia', 'amnestympl'); ?>" value="<?php echo $val_city; ?>" required>
            </div>
            <div>
                <label class="form-label"><?php esc_html_e('Kod pocztowy:', 'amnestympl'); ?></label>
                <input type="text" name="zip_code" class="form-input" pattern="^[0-9]{2}-[0-9]{3}$" title="<?php esc_attr_e('Wymagany format: 00-000', 'amnestympl'); ?>" placeholder="<?php esc_attr_e('np. 00-000', 'amnestympl'); ?>">
            </div>
        </div>

        <div class="form-section checkbox-option">
            <label>
                <input type="checkbox" name="accessibility" value="1">
                <?php esc_html_e('Miejsce łatwo dostępne dla osób z niepełnosprawnościami.', 'amnestympl'); ?>
            </label>
        </div>

        <div class="form-section form-section-last">
            <button type="submit" name="submit_event" class="save-button amnesty-card-button"><?php esc_html_e('Dodaj wydarzenie', 'amnestympl'); ?></button>
        </div>
    </form>
    <?php

    return ob_get_clean();
}