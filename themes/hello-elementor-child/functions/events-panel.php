<?php
// 1. AJAX: FETCH EVENT FORM FOR EDITING

add_action('wp_ajax_get_event_form', 'ajax_get_event_form');

function ajax_get_event_form() {
    if ( ! is_user_logged_in() || ! isset($_POST['post_id']) ) {
        wp_send_json_error( __('Brak uprawnień.', 'amnestympl') );
    }

    $post_id = intval($_POST['post_id']);
    $post = get_post($post_id);

    // Security check
    if ( ! $post || $post->post_type != 'wydarzenie' || $post->post_author != get_current_user_id() ) {
        wp_send_json_error( __('Brak dostępu lub wydarzenie nie istnieje.', 'amnestympl') );
    }

    // Fetch data
    $val_org    = $post->post_title;
    $val_start  = maraton_get_event_datetime($post_id, 'start');
    $val_end    = maraton_get_event_datetime($post_id, 'end');
    $val_venue  = function_exists('get_field') ? get_field('nazwa_lokalu', $post_id) : get_post_meta($post_id, 'nazwa_lokalu', true);
    $val_street = function_exists('get_field') ? get_field('ulica', $post_id) : get_post_meta($post_id, 'ulica', true);
    $val_city   = function_exists('get_field') ? get_field('miejscowosc', $post_id) : get_post_meta($post_id, 'miejscowosc', true);
    $val_zip    = function_exists('get_field') ? get_field('kod_pocztowy', $post_id) : get_post_meta($post_id, 'kod_pocztowy', true);
    $val_public = function_exists('get_field') ? get_field('wydarzenie_publiczne', $post_id) : get_post_meta($post_id, 'wydarzenie_publiczne', true);
    $val_access = function_exists('get_field') ? get_field('dostepnosc', $post_id) : get_post_meta($post_id, 'dostepnosc', true);

    if ($val_start) $val_start = date('Y-m-d\TH:i', strtotime($val_start));
    if ($val_end) $val_end = date('Y-m-d\TH:i', strtotime($val_end));
    
    $check_public = ($val_public == '1' || $val_public === 'tak' || $val_public === true) ? 'checked' : '';
    $check_map    = ( '1' === (string) get_post_meta($post_id, 'widoczne_na_mapie', true) ) ? 'checked' : '';
    $check_access = ($val_access == '1' || $val_access === 'tak' || $val_access === true) ? 'checked' : '';

    ob_start();
    ?>
    <div class="form-header-actions">
        <button id="cancel-ajax-edit" class="btn-back">&larr; <?php esc_html_e('Wróć do listy wydarzeń', 'amnestympl'); ?></button>
        <h3 class="form-title-edit"><?php esc_html_e('Edytujesz:', 'amnestympl'); ?> <?php echo esc_html($val_org); ?></h3>
    </div>
    
    <form id="ajax-edit-form" class="new-event-form">
        <?php wp_nonce_field('ajax_edit_action', 'ajax_edit_nonce'); ?>
        <input type="hidden" name="post_id_to_edit" value="<?php echo esc_attr($post_id); ?>">
        <input type="hidden" name="action" value="save_event_form">
        
        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Nazwa organizatora / instytucji / grupy', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
            <input type="text" name="organizer_name" class="form-input" value="<?php echo esc_attr($val_org); ?>" required>
        </div>

        <h3 class="form-section-title"><?php esc_html_e('Data i godzina maratonu', 'amnestympl'); ?></h3>
        <div class="form-section split-row event-dates">
            <div>
                <label class="form-label"><?php esc_html_e('Rozpoczęcie', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
                <input type="datetime-local" name="start_datetime" class="form-input" value="<?php echo esc_attr($val_start); ?>" required>
            </div>
            <div>
                <label class="form-label"><?php esc_html_e('Zakończenie', 'amnestympl'); ?></label>
                <input type="datetime-local" name="end_datetime" class="form-input" value="<?php echo esc_attr($val_end); ?>" min="<?php echo esc_attr($val_start); ?>">
            </div>
        </div>
        
        <h3 class="form-section-title"><?php esc_html_e('Gdzie odbędzie się wydarzenie', 'amnestympl'); ?></h3>
        <div class="form-section checkbox-option">
            <label>
                <input type="checkbox" name="is_public_event" value="1" <?php echo $check_public; ?>> 
                <strong><?php esc_html_e('Wydarzenie publiczne (otwarte dla osób z zewnątrz).', 'amnestympl'); ?></strong>
            </label>
        </div>
        <div class="form-section checkbox-option">
            <label>
                <input type="checkbox" name="show_on_map" value="1" <?php echo $check_map; ?>>
                <strong><?php esc_html_e('Zaznacz, by wydarzenie było widoczne na mapie.', 'amnestympl'); ?></strong>
            </label>
        </div>
        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Nazwa lokalu:', 'amnestympl'); ?></label>
            <input type="text" name="venue_name" class="form-input" value="<?php echo esc_attr($val_venue); ?>">
        </div>
        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Ulica i nr lokalu:', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
            <input type="text" name="street" class="form-input" value="<?php echo esc_attr($val_street); ?>" required>
            <span class="field-description field-description-inline"><?php esc_html_e('Staraj się podawać samą ulicę i numer budynku.', 'amnestympl'); ?></span>
        </div>
        <div class="form-section split-row">
            <div>
                <label class="form-label"><?php esc_html_e('Miejscowość:', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
                <input type="text" name="city" class="form-input" value="<?php echo esc_attr($val_city); ?>" required>
            </div>
            <div>
                <label class="form-label"><?php esc_html_e('Kod pocztowy:', 'amnestympl'); ?></label>
                <input type="text" name="zip_code" class="form-input" value="<?php echo esc_attr($val_zip); ?>" pattern="^[0-9]{2}-[0-9]{3}$">
            </div>
        </div>
        <div class="form-section checkbox-option">
            <label>
                <input type="checkbox" name="accessibility" value="1" <?php echo $check_access; ?>> 
                <?php esc_html_e('Miejsce łatwo dostępne dla osób z niepełnosprawnościami.', 'amnestympl'); ?>
            </label>
        </div>
        
        <div class="form-actions-inline">
            <button type="submit" id="ajax-save-button" class="save-button amnesty-card-button"><?php esc_html_e('Zapisz zmiany', 'amnestympl'); ?></button>
            <span id="edit-status-message" class="status-message"></span>
        </div>
    </form>
    <?php
    wp_send_json_success(ob_get_clean()); 
}

// 2. AJAX: SAVE EVENT FORM CHANGES IN BACKGROUND

add_action('wp_ajax_save_event_form', 'ajax_save_event_form');

function ajax_save_event_form() {
    if ( ! is_user_logged_in() || ! isset($_POST['post_id_to_edit']) ) {
        wp_send_json_error( __('Brak uprawnień.', 'amnestympl') );
    }
    
    if ( ! check_ajax_referer('ajax_edit_action', 'ajax_edit_nonce', false) ) {
        wp_send_json_error( __('Błąd bezpieczeństwa formularza.', 'amnestympl') );
    }

    $post_id = intval($_POST['post_id_to_edit']);
    $post = get_post($post_id);
    
    if ( ! $post || $post->post_type != 'wydarzenie' || $post->post_author != get_current_user_id() ) {
        wp_send_json_error( __('Brak dostępu.', 'amnestympl') );
    }

    $date_fields = maraton_event_date_meta(
        sanitize_text_field($_POST['start_datetime'] ?? ''),
        sanitize_text_field($_POST['end_datetime'] ?? '')
    );
    if ( is_wp_error($date_fields) ) {
        wp_send_json_error( $date_fields->get_error_message() );
    }

    wp_update_post(array('ID' => $post_id, 'post_title' => sanitize_text_field($_POST['organizer_name'])));

    $street = sanitize_text_field($_POST['street']);
    $city   = sanitize_text_field($_POST['city']);

    // Dates are stored in both formats used by the theme (see maraton_get_event_datetime())
    $fields_to_save = $date_fields + array(
        'nazwa_lokalu'          => sanitize_text_field($_POST['venue_name']),
        'ulica'                 => $street,
        'miejscowosc'           => $city,
        'kod_pocztowy'          => sanitize_text_field($_POST['zip_code']),
        'wydarzenie_publiczne'  => isset($_POST['is_public_event']) ? 1 : 0,
        'widoczne_na_mapie'     => isset($_POST['show_on_map']) ? 1 : 0,
        'dostepnosc'            => isset($_POST['accessibility']) ? 1 : 0,
    );

    // Geocoding
    $addresses_to_check = array();
    $clean_street = trim(preg_replace('/^(ul\.?\s*|ulica\s+|al\.?\s*|aleja\s+|pl\.?\s*|plac\s+)/i', '', $street));
    $addresses_to_check[] = $clean_street . ', ' . $city;
    $street_without_apt = trim(explode('/', $clean_street)[0]);
    $addresses_to_check[] = $street_without_apt . ', ' . $city;
    $street_only = trim(preg_replace('/[0-9]+[a-zA-Z]?.*$/', '', $street_without_apt));
    if (!empty($street_only)) $addresses_to_check[] = $street_only . ', ' . $city; 
    $addresses_to_check[] = $city;

    foreach (array_unique($addresses_to_check) as $test_address) {
        $api_url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($test_address);
        $response = wp_remote_get($api_url, array('headers' => array('User-Agent' => 'MyEventApp/2.0 (WordPress)')));
        
        if ( ! is_wp_error($response) ) {
            $body = wp_remote_retrieve_body($response);
            $map_data = json_decode($body);
            if ( ! empty($map_data) ) {
                $fields_to_save['geo_lat'] = $map_data[0]->lat;
                $fields_to_save['geo_lng'] = $map_data[0]->lon;
                break; 
            }
        }
    }

    foreach ($fields_to_save as $meta_key => $meta_value) {
        if ( function_exists('update_field') ) { 
            update_field($meta_key, $meta_value, $post_id); 
        } else { 
            update_post_meta($post_id, $meta_key, $meta_value); 
        }
    }

    wp_send_json_success( __('Wydarzenie zostało zaktualizowane pomyślnie!', 'amnestympl') );
}

// 3. SHORTCODE: DISPLAY USER EVENTS 
add_shortcode('my_events', 'render_my_events_shortcode');

function render_my_events_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__('Zaloguj się, aby zobaczyć swoje wydarzenia.', 'amnestympl') . '</p>';
    }

    ob_start();
    ?>
    <div id="ajax-main-messages">
        <?php if ( isset($_GET['added']) && '1' === $_GET['added'] ) : ?>
            <div class="alert-message alert-success">🎉 <?php esc_html_e('Dziękujemy! Twoje wydarzenie zostało pomyślnie dodane.', 'amnestympl'); ?></div>
        <?php endif; ?>
    </div>

    <div id="ajax-table-container">
        <?php
        $args = array(
            'post_type'      => 'wydarzenie', 
            'author'         => get_current_user_id(), 
            'posts_per_page' => -1, 
            'post_status'    => array('publish', 'pending', 'draft')
        );
        $query = new WP_Query($args);

        if ( $query->have_posts() ) {
            
            // HEADER ROW (Floats above the bordered grid)
            echo '<div class="events-grid-header">';
            echo '<div class="header-cell">' . esc_html__('DATA', 'amnestympl') . '</div>';
            echo '<div class="header-cell">' . esc_html__('WYDARZENIE', 'amnestympl') . '</div>';
            echo '<div class="header-cell">' . esc_html__('MIEJSCOWOŚĆ', 'amnestympl') . '</div>';
            echo '<div class="header-cell">' . esc_html__('AKCJE', 'amnestympl') . '</div>';
            echo '</div>';
            
            // MAIN GRID CONTAINER (Houses the bordered rows)
            echo '<div class="events-grid-body">';
            
            $current_timestamp = current_time('timestamp');

            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Fetch fields
                $city = function_exists('get_field') ? get_field('miejscowosc', $post_id) : get_post_meta($post_id, 'miejscowosc', true);
                
                // Fetch and format start date
                $raw_start_date = maraton_get_event_datetime($post_id, 'start');
                $formatted_date = !empty($raw_start_date) ? date('d/m/Y', strtotime($raw_start_date)) : '-';
                
                // DATA ROW
                echo '<div class="events-grid-row">';
                
                echo '<div class="grid-cell date-cell">' . esc_html($formatted_date) . '</div>';
                echo '<div class="grid-cell name-cell">' . get_the_title() . '</div>';
                echo '<div class="grid-cell city-cell">' . esc_html($city) . '</div>';
                
                // ACTIONS CELL
                echo '<div class="grid-cell actions-cell">';
                
                // ====================================================
                // NEW: CHECK FOR EXISTING MATERIAL ORDERS (SAFE METHOD)
                // ====================================================
                
                // Używamy get_posts(), aby nie zaburzać działania głównej pętli while
                $order_posts = get_posts(array(
                    'post_type'      => 'material_order',
                    'meta_query'     => array(
                        array(
                            'key'   => 'powiazane_wydarzenie_id',
                            'value' => $post_id,
                        )
                    ),
                    'posts_per_page' => 1,
                    'fields'         => 'ids' // Pobieramy tylko ID w celach optymalizacyjnych
                ));
                
                if ( ! empty($order_posts) ) {
                    $order_id = $order_posts[0];
                    $carrier = get_post_meta($order_id, 'przewoznik', true);
                    
                    // Retrieve delivery details based on carrier type
                    $delivery_info = '';
                    if ($carrier === 'DPD Pickup') {
                        $locker_id = get_post_meta($order_id, 'punkt_odbioru_id', true);
                        $delivery_info = sprintf(esc_html__('Punkt: %s', 'amnestympl'), $locker_id);
                    } elseif ($carrier === 'Kurier DPD') {
                        $street = get_post_meta($order_id, 'adres_ulica', true);
                        $postcode = get_post_meta($order_id, 'adres_kod', true);
                        $order_city = get_post_meta($order_id, 'adres_miasto', true);
                        $delivery_info = sprintf(esc_html__('Kurier: %s, %s %s', 'amnestympl'), $street, $postcode, $order_city);
                    }

                    // Display the "Materials Ordered" status badge
                    echo '<span class="btn-action status-ordered" style="background-color: #f1c40f; title="' . esc_attr($delivery_info) . '">';
                    echo esc_html__('Materiały zamówione', 'amnestympl');
                    
                    // Append small text showing the delivery address/point underneath the main badge text
                    if (!empty($delivery_info)) {
                        echo '<span style="display: block; font-size: 10px; line-height: 1.1; margin-top: 4px; font-weight: normal;">' . esc_html($delivery_info) . '</span>';
                    }
                    echo '</span>';
                }

                // ----------------------------------------------------

                echo '<button class="btn-action btn-edit open-ajax-edit" data-id="' . esc_attr($post_id) . '">' . esc_html__('Edytuj', 'amnestympl') . '</button>';
                
                // 7-DAY TIME LOCK LOGIC FOR REPORTS
                $is_report_submitted = get_post_meta($post_id, 'raport_zlozony', true);
                
                $event_date = maraton_get_event_datetime($post_id, 'end');
                if ( empty($event_date) ) {
                    $event_date = $raw_start_date;
                }

                if ( ! empty($event_date) ) {
                    $event_timestamp = strtotime($event_date);
                    $target_timestamp = $event_timestamp + (7 * DAY_IN_SECONDS); 

                    if ( $current_timestamp >= $target_timestamp ) {
                        if ( $is_report_submitted === '1' ) {
                            echo '<span class="btn-action status-success">' . esc_html__('Raport wysłany', 'amnestympl') . '</span>';
                        } else {
                            echo '<button class="btn-action btn-submit open-ajax-report" data-id="' . esc_attr($post_id) . '">' . esc_html__('Wyślij raport', 'amnestympl') . '</button>';
                        }
                    }
                }
                
                echo '</div>'; // End actions cell
                echo '</div>'; // End data row
            }
            echo '</div>'; // End main grid container
            
            wp_reset_postdata();
        } else {
            echo '<div class="empty-state-message">' . esc_html__('Nie masz jeszcze żadnych zgłoszonych wydarzeń.', 'amnestympl') . '</div>';
        }
        ?>
    </div>
    <div id="ajax-form-container" class="ajax-form-wrapper"></div>
    <?php
    return ob_get_clean();
}