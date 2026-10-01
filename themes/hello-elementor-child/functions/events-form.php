<?php
// Global variable to store form errors
global $event_form_error;
$event_form_error = '';

// 1. HANDLE EVENT SUBMISSION (Fires before page load to prevent F5 resubmission)

add_action('template_redirect', 'handle_event_submission');

function handle_event_submission() {
    global $event_form_error;

    // Check if the form was submitted
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_event']) ) {
        
        // Security check (nonce validation)
        if ( ! isset($_POST['event_nonce']) || ! wp_verify_nonce($_POST['event_nonce'], 'save_event_action') ) {
            $event_form_error = __('Błąd bezpieczeństwa formularza. Odśwież stronę i spróbuj ponownie.', 'amnestympl');
            return;
        }

        // Sanitize incoming fields
        $title      = sanitize_text_field($_POST['organizer_name']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $street     = sanitize_text_field($_POST['street']);
        $city       = sanitize_text_field($_POST['city']);

        if ( empty($title) || empty($start_date) || empty($street) || empty($city) ) {
            $event_form_error = __('Proszę wypełnić wszystkie wymagane pola (w tym ulicę i miejscowość).', 'amnestympl');
            return;
        }

        // --- SMART MAP VALIDATION (Geocoding) ---
        $addresses_to_check = array();
        
        // Remove common street prefixes (ul., ulica, al., plac) for better API matching
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
            $event_form_error = __('System map nie mógł zlokalizować podanej miejscowości. Sprawdź, czy nie ma literówek.', 'amnestympl');
            return; // Stop saving, show error on the page
        }
        
        // --- SAVE TO DATABASE ---
        $post_id = wp_insert_post(array(
            'post_title'    => $title,
            'post_type'     => 'wydarzenie', // Keep Polish DB key
            'post_status'   => 'publish'
        ));

        if ( $post_id ) {
            // Array of meta fields (Keys remain Polish for DB compatibility, values are variables)
            $fields_to_save = array(
                'data_rozpoczecia'     => $start_date,
                'godzina_rozpoczecia'  => sanitize_text_field($_POST['start_time']),
                'data_zakonczenia'     => sanitize_text_field($_POST['end_date']),
                'godzina_zakonczenia'  => sanitize_text_field($_POST['end_time']),
                'nazwa_lokalu'         => sanitize_text_field($_POST['venue_name']),
                'ulica'                => $street,
                'miejscowosc'          => $city,
                'kod_pocztowy'         => sanitize_text_field($_POST['zip_code']),
                'wydarzenie_publiczne' => isset($_POST['is_public_event']) ? 1 : 0,
                'dostepnosc'           => isset($_POST['accessibility']) ? 1 : 0,
                'geo_lat'              => $lat,
                'geo_lng'              => $lng
            );

            // Save via ACF if active, otherwise standard post meta
            foreach ($fields_to_save as $meta_key => $meta_value) {
                if ( function_exists('update_field') ) {
                    update_field($meta_key, $meta_value, $post_id);
                } else {
                    update_post_meta($post_id, $meta_key, $meta_value);
                }
            }

            // --- HARD PHP REDIRECT (Prevents F5 duplication) ---
            $return_url = wp_get_referer() ? wp_get_referer() : get_permalink();
            wp_safe_redirect(add_query_arg('added', '1', $return_url));
            exit; // Stops script execution
        }
    }
}

// 2. SHORTCODE: ADD EVENT FORM

add_shortcode('event_form', 'render_event_form');

function render_event_form() {
    global $event_form_error;

    if ( ! is_user_logged_in() ) {
        return '<div class="alert-message alert-warning">' . esc_html__('Musisz być zalogowany, aby zarejestrować wydarzenie.', 'amnestympl') . '</div>';
    }

    ob_start();

    // Show success message after redirect
    if ( isset($_GET['added']) && $_GET['added'] == '1' ) {
        echo '<div class="alert-message alert-success">🎉 ' . esc_html__('Dziękujemy! Twoje wydarzenie zostało pomyślnie dodane i opublikowane na mapie.', 'amnestympl') . '</div>';
    }

    // Show validation errors
    if ( ! empty($event_form_error) ) {
        echo '<div class="alert-message alert-danger"><strong>' . esc_html__('Błąd walidacji:', 'amnestympl') . '</strong> ' . esc_html($event_form_error) . '</div>';
    }

    // Remember field values if an error occurred
    $val_org    = isset($_POST['organizer_name']) ? esc_attr($_POST['organizer_name']) : '';
    $val_street = isset($_POST['street']) ? esc_attr($_POST['street']) : '';
    $val_city   = isset($_POST['city']) ? esc_attr($_POST['city']) : '';

    ?>
    <form action="" method="post" class="new-event-form">
        <?php wp_nonce_field('save_event_action', 'event_nonce'); ?>

        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Nazwa organizatora / instytucji / grupy', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
            <input type="text" name="organizer_name" class="form-input" placeholder="<?php esc_attr_e('np. Liceum Ogólnokształcące nr 1', 'amnestympl'); ?>" value="<?php echo $val_org; ?>" required>
        </div>

        <h3 class="form-section-title"><?php esc_html_e('Data i godzina maratonu', 'amnestympl'); ?></h3>
        <div class="form-section split-row">
            <div>
                <label class="form-label"><?php esc_html_e('Data rozpoczęcia', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
                <input type="date" name="start_date" class="form-input" required>
            </div>
            <div>
                <label class="form-label"><?php esc_html_e('Godzina', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
                <input type="time" name="start_time" class="form-input" required>
            </div>
        </div>
        
        <div class="form-section split-row">
            <div>
                <label class="form-label"><?php esc_html_e('Data zakończenia', 'amnestympl'); ?></label>
                <input type="date" name="end_date" class="form-input">
            </div>
            <div>
                <label class="form-label"><?php esc_html_e('Godzina', 'amnestympl'); ?></label>
                <input type="time" name="end_time" class="form-input">
            </div>
        </div>

        <h3 class="form-section-title"><?php esc_html_e('Gdzie odbędzie się wydarzenie', 'amnestympl'); ?></h3>
        
        <div class="form-section checkbox-option">
            <label>
                <input type="checkbox" name="is_public_event" value="1">
                <strong><?php esc_html_e('Wydarzenie publiczne (otwarte dla osób z zewnątrz)', 'amnestympl'); ?></strong>
                <span class="field-description"><?php esc_html_e('Zaznacz, aby wydarzenie pojawiło się na mapie.', 'amnestympl'); ?></span>
            </label>
        </div>

        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Nazwa lokalu:', 'amnestympl'); ?></label>
            <input type="text" name="venue_name" class="form-input" placeholder="<?php esc_attr_e('np. Sala gimnastyczna', 'amnestympl'); ?>">
        </div>
        
        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Ulica i nr lokalu:', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
            <input type="text" name="street" class="form-input" placeholder="<?php esc_attr_e('np. ul. Zofii Nałkowskiej 21', 'amnestympl'); ?>" value="<?php echo $val_street; ?>" required>
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