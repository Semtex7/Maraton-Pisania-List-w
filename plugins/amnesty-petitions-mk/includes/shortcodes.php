<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ====================================================================
 * 1. FRONTEND FORM RENDER (SHORTCODE)
 * ==================================================================== */

add_shortcode( 'amnesty_petition', 'amnesty_petition_render_shortcode' );

function amnesty_petition_render_shortcode( $atts ) {
    global $post;
    
    $atts = shortcode_atts( array( 
        'id' => isset( $post->ID ) ? $post->ID : 0      ),$atts, 'amnesty_petition' );
    
    $petition_id = intval($atts['id'] );
    
    if ( ! $petition_id || get_post_type($petition_id ) !== 'amnesty_petition' ) {
        return '<p>' . esc_html__( 'Błąd: Nie znaleziono petycji.', 'amnesty-petitions' ) . '</p>';
    }

    // Fetch options for visual settings and links dynamically from the admin panel
    $rodo_intro       = get_option('amnesty_text_rodo_intro', esc_html__('Klikając "Wysyłam!", przekazujesz swój głos...', 'amnesty-petitions') );
    $rodo_full        = get_option('amnesty_text_rodo_full', esc_html__('Twoje dane będą przetwarzane...', 'amnesty-petitions') );
    $consent_camp     = get_option('amnesty_text_consent_campaign', esc_html__('Chcę otrzymywać informacje na temat innych akcji...', 'amnesty-petitions') );
    
    // Add dynamic option for signing all petitions (you can add this to settings.php later)
    $consent_sign_all = get_option('amnesty_text_consent_sign_all', esc_html__('Chcę automatycznie podpisać akcje w sprawach wszystkich bohaterów tegorocznego Maratonu.', 'amnesty-petitions') );
    
    $link_privacy     = get_option('amnesty_link_privacy', '#' );

    $thankyou_title          = get_option('amnesty_thankyou_title', esc_html__('List napisany!', 'amnesty-petitions') );$thankyou_subtitle       = get_option('amnesty_thankyou_subtitle', esc_html__('Wkrótce przekażemy go na odpowiedni adres.', 'amnesty-petitions') );
    $thankyou_count_intro    = get_option('amnesty_thankyou_count_intro', esc_html__('Dzięki Tobie mamy już', 'amnesty-petitions') );
    $thankyou_count_suffix   = get_option('amnesty_thankyou_count_suffix', esc_html__('listów w tej akcji!', 'amnesty-petitions') );
    $thankyou_actions_title  = get_option('amnesty_thankyou_actions_title', esc_html__('Sprawdź, co możesz jeszcze zrobić:', 'amnesty-petitions') );
    $thankyou_events_label   = get_option('amnesty_thankyou_button_events_label', esc_html__('ZNAJDŹ WYDARZENIE W TWOJEJ OKOLICY', 'amnesty-petitions') );
    $thankyou_donate_label   = get_option('amnesty_thankyou_button_donate_label', esc_html__('KUP ZNACZEK', 'amnesty-petitions') );$thankyou_more_label     = get_option('amnesty_thankyou_button_more_label', esc_html__('SPRAWDŹ, CO JESZCZE ROBIMY', 'amnesty-petitions') );
    $thankyou_events_link    = get_option('amnesty_thankyou_button_events_link', get_option('amnesty_link_campaign', '#') );
    $thankyou_donate_link    = get_option('amnesty_thankyou_button_donate_link', get_option('amnesty_link_donate', '#') );
    $thankyou_more_link      = get_option('amnesty_thankyou_button_more_link', '#' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'amnesty_signatures';$db_count = (int) $wpdb->get_var($wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE petition_id = %d", $petition_id ) );$extra_letters = (int) get_post_meta( $petition_id, '_amnesty_petition_extra_letters', true );$total_letters = $db_count +$extra_letters;

    // Fetch petition specific letter contents
    $appeal_text      = get_post_meta($petition_id, '_amnesty_petition_appeal', true );
    $solidarity_text  = get_post_meta($petition_id, '_amnesty_petition_solidarity_letter', true );

    ob_start();
    ?>
    <div class="amnesty-petition-wrapper">
        <!-- Tabs moved outside the main form background -->
        <div class="amnesty-letter-type-toggle">
            <button type="button" class="amnesty-toggle-btn active" data-type="appeal"><?php esc_html_e( 'List do władz', 'amnesty-petitions' ); ?></button>
            <button type="button" class="amnesty-toggle-btn" data-type="solidarity"><?php esc_html_e( 'List solidarnościowy', 'amnesty-petitions' ); ?></button>
        </div>

        <!-- Added method="POST" as a fallback security measure -->
        <form id="amnesty-petition-form-<?php echo esc_attr( $petition_id ); ?>" class="amnesty-petition-form" method="POST">
            <?php wp_nonce_field( 'amnesty_sign_petition', 'amnesty_sign_nonce' ); ?>
            <input type="hidden" name="petition_id" value="<?php echo esc_attr( $petition_id ); ?>">
            <?php amnesty_form_protection_fields(); ?>
            <input type="hidden" name="action" value="submit_amnesty_petition">
            <input type="hidden" name="letter_type" id="amnesty-letter-type-<?php echo esc_attr( $petition_id ); ?>" value="appeal">

            <div class="amnesty-form-grid">
                <!-- Left Column: Message -->
                <div class="amnesty-col-left">
                    <label class="amnesty-section-label"><?php esc_html_e( 'TREŚĆ', 'amnesty-petitions' ); ?></label>
                    <textarea name="custom_message" id="amnesty-message-<?php echo esc_attr($petition_id); ?>"><?php echo esc_textarea($appeal_text); ?></textarea>
                </div>

                <!-- Right Column: User Data -->
                <div class="amnesty-col-right">
                    <label class="amnesty-section-label"><?php esc_html_e( 'TWOJE DANE', 'amnesty-petitions' ); ?></label>
                    <input type="text" name="first_name" placeholder="<?php esc_html_e( 'Imię*', 'amnesty-petitions' ); ?>" required>
                    <input type="text" name="last_name" placeholder="<?php esc_html_e( 'Nazwisko*', 'amnesty-petitions' ); ?>" required>
                    <input type="email" name="email" placeholder="<?php esc_html_e( 'E-mail*', 'amnesty-petitions' ); ?>" required>
                    <input type="tel" name="phone" placeholder="<?php esc_html_e( 'Telefon', 'amnesty-petitions' ); ?>">
                    <input type="text" name="city" placeholder="<?php esc_html_e( 'Miejscowość', 'amnesty-petitions' ); ?>">
                    
                    <div class="amnesty-petition-rodo"">
                        <p><?php echo esc_html($rodo_intro); ?></p>
                        <!-- Mandatory RODO consent -->
                        <label class="amnesty-checkbox-label">
                            <input type="checkbox" name="rodo_consent" value="1" required class="amnesty-checkbox-input">
                            <span>*<?php echo esc_html($rodo_full); ?> <a href="<?php echo esc_url($link_privacy); ?>" target="_blank"><?php esc_html_e( 'Polityka Prywatności', 'amnesty-petitions' ); ?></a>.</span>
                        </label>
                    </div>

                    <!-- Optional consents -->
                    <label class="amnesty-petition-checkbox-group amnesty-checkbox-label">
                        <input type="checkbox" name="campaign_consent" value="1" class="amnesty-checkbox-input">
                        <span><?php echo esc_html($consent_camp); ?></span>
                    </label>

                    <div class="amnesty-sign-all-wrapper">
                        <label class="amnesty-sign-all-label">
                            <!-- Checkbox input for backend logic -->
                            <input type="checkbox" checked name="sign_all" value="1" />
                            <span><?php echo esc_html($consent_sign_all); ?> (<a href="<?php echo esc_url( amnesty_get_all_petitions_url() ); ?>" target="_blank"><?php esc_html_e( 'zobacz wszystkie', 'amnesty-petitions' ); ?></a>)</span>
                        </label>
                    </div>
                    
                    <button type="submit" class="amnesty-petition-submit-btn amnesty-submit-btn"><?php esc_html_e( 'WYSYŁAM!', 'amnesty-petitions' ); ?></button>
                </div>
            </div>
            <div class="amnesty-form-messages amnesty-msg-container"></div>
        </form>
    </div>

    <!-- Thank you message container (hidden by default) -->
    <div id="amnesty-thank-you-container-<?php echo esc_attr( $petition_id ); ?>" class="amnesty-thank-you-container">
        <h3 class="amnesty-thank-you-title"><?php echo esc_html( $thankyou_title ); ?></h3>
        <p class="amnesty-thank-you-subtitle"><?php echo esc_html( $thankyou_subtitle ); ?></p>
        <p class="amnesty-thank-you-count-intro"><?php echo esc_html( $thankyou_count_intro ); ?></p>
        <p class="amnesty-thank-you-count"><?php echo esc_html( number_format_i18n( $total_letters ) ); ?></p>
        <p class="amnesty-thank-you-count-suffix"><?php echo esc_html( $thankyou_count_suffix ); ?></p>
        <p class="amnesty-thank-you-actions-title"><?php echo esc_html( $thankyou_actions_title ); ?></p>

        <div class="amnesty-thank-you-actions">
            <a href="<?php echo esc_url( $thankyou_events_link ); ?>" class="amnesty-btn-popup" target="_blank" rel="noopener"><?php echo esc_html( $thankyou_events_label ); ?></a>
            <a href="<?php echo esc_url( $thankyou_donate_link ); ?>" class="amnesty-btn-popup" target="_blank" rel="noopener"><?php echo esc_html( $thankyou_donate_label ); ?></a>
            <a href="<?php echo esc_url( $thankyou_more_link ); ?>" class="amnesty-btn-popup" target="_blank" rel="noopener"><?php echo esc_html( $thankyou_more_label ); ?></a>
        </div>
    </div>

    <!-- JSON for letter content storage -->
    <script type="application/json" id="amnesty-data-<?php echo esc_attr($petition_id); ?>">
    {
        "appeal": <?php echo wp_json_encode($appeal_text); ?>,
        "solidarity": <?php echo wp_json_encode($solidarity_text); ?>
    }
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const petitionId = '<?php echo esc_js( $petition_id ); ?>';
        const formWrapper = document.querySelector('.amnesty-petition-wrapper');
        const thankYouContainer = document.getElementById('amnesty-thank-you-container-' + petitionId);
        
        // Define exact AJAX URL inside the script to avoid dependency issues
        const ajaxUrl = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';

        // 1. Check if we are already in the "Thank You" state
        if (window.location.hash === '#thankyou') {
            if (formWrapper) formWrapper.style.display = 'none';
            if (thankYouContainer) thankYouContainer.style.display = 'block';
            
            // Clean the URL so it looks nice
            history.replaceState(null, null, ' ');
            return; // Stop running form logic
        }

        const form = document.getElementById('amnesty-petition-form-' + petitionId);
        if (!form) return;

        // 2. Handle letter type switching
        const toggleBtns = formWrapper.querySelectorAll('.amnesty-toggle-btn');
        const messageArea = document.getElementById('amnesty-message-' + petitionId);
        const letterTypeInput = document.getElementById('amnesty-letter-type-' + petitionId);
        const letterDataElement = document.getElementById('amnesty-data-' + petitionId);
        const letterData = letterDataElement ? JSON.parse(letterDataElement.textContent) : { appeal: '', solidarity: '' };

        toggleBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                toggleBtns.forEach(b => b.classList.remove('active'));
                e.currentTarget.classList.add('active');
                
                const type = e.currentTarget.getAttribute('data-type');
                messageArea.value = letterData[type];
                letterTypeInput.value = type;
            });
        });

        // 3. Robust AJAX submission handling
        form.addEventListener('submit', (e) => {
            // Prevent the default GET/POST page reload
            e.preventDefault(); 
            
            const submitBtn = form.querySelector('.amnesty-petition-submit-btn');
            const msgDiv = form.querySelector('.amnesty-form-messages');
            
            // Visual feedback
            submitBtn.disabled = true;
            submitBtn.textContent = '<?php esc_html_e("Wysyłanie...", "amnesty-petitions") ?>';
            msgDiv.style.display = 'none';
            
            // Seconds since page load, checked server-side (bot protection, includes/form-protection.php)
            const elapsedField = form.querySelector('[name="amnesty_elapsed"]');
            if (elapsedField) {
                elapsedField.value = (performance.now() / 1000).toFixed(1);
            }

            // Gather all form fields securely
            const formData = new FormData(form);

            // Perform the AJAX request
            fetch(ajaxUrl, { 
                method: 'POST', 
                body: formData 
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    window.dataLayer = window.dataLayer || [];
            
                    // Push custom success event to Google Tag Manager
                    window.dataLayer.push({
                        'event': 'form_submission_success',
                        'form_type': 'amnesty_petition',
                        'petition_id': petitionId
                    });
                    window.location.hash = 'thankyou';
                    window.location.reload();
                } else {
                    // Server returned an error (e.g. duplicate email)
                    msgDiv.style.display = 'block'; 
                    msgDiv.classList.add('error-msg');
                    msgDiv.innerText = res.data; 
                    
                    submitBtn.disabled = false;
                    submitBtn.textContent = '<?php esc_html_e("WYSYŁAM!", "amnesty-petitions") ?>';
                }
            })
            .catch(err => { 
                // Network or catastrophic failure
                console.error('AJAX Error:', err);
                msgDiv.style.display = 'block'; 
                msgDiv.classList.add('error-msg');
                msgDiv.innerText = '<?php esc_html_e("Błąd serwera. Sprawdź połączenie i spróbuj ponownie.", "amnesty-petitions") ?>'; 
                
                submitBtn.disabled = false; 
                submitBtn.textContent = '<?php esc_html_e("WYSYŁAM!", "amnesty-petitions") ?>';
            });
        });
    });
    </script>
    <?php 
    return ob_get_clean();
}

add_shortcode('letters_counter', 'maraton_render_letters_counter_shortcode');

function maraton_render_letters_counter_shortcode() {
    // Letters from petitions published within the edition range (see includes/edition.php)
    $total_signatures = amnesty_count_edition_letters();

    $total_str = (string)$total_signatures;
    $digits_count = strlen($total_str);

    ob_start();
    ?>
    <div class="maraton-counter-container">
        <h3 class="counter-heading"><?php esc_html_e('Napisaliśmy już listów w tym roku:', 'amnesty-petitions'); ?></h3>
        
        <div class="custom-block-counter-wrapper" data-target="<?php echo esc_attr($total_signatures); ?>" data-duration="2500">
            <?php 
            for ( $i = 0; $i < $digits_count; $i++ ) {
                echo '<span class="counter-digit">0</span>';
            }
            ?>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}

add_filter( 'body_class', 'amnesty_remove_elementor_kit_class', 999 );

function amnesty_remove_elementor_kit_class( $classes ) {
    
    // Check if we are currently viewing a single petition page
    if ( is_singular( 'amnesty_petition' ) ) {
        
        // Loop through all classes assigned to the body tag
        foreach ( $classes as $key => $class ) {
            
            // If the class name starts with 'elementor-kit-', remove it from the array
            if ( strpos( $class, 'elementor-kit-' ) === 0 ) {
                unset( $classes[ $key ] );
            }
        }
    }
    
    // Return the modified array of classes
    return $classes;
}

/* 
 * FRONTEND MAP RENDER (SHORTCODE)

add_shortcode( 'amnesty_petition_map', 'amnesty_petition_map_render' );

function amnesty_petition_map_render( $atts ) {
    global $post;
    
    $atts = shortcode_atts( array( 
        'id' => isset( $post->ID ) ? $post->ID : 0      ),$atts, 'amnesty_petition_map' );
    
    $petition_id = intval($atts['id'] );
    
    if ( ! $petition_id ) {
        return '';
    }

    $marker_bg   = get_option( 'amnesty_color_marker_bg', '#1a1a1a' );
    $marker_text = get_option( 'amnesty_color_marker_text', '#eebf9c' );

    ob_start();
    ?>
    <!-- Ładowanie bibliotek Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    
    <style>
        .amnesty-map-wrapper { position: relative; margin-bottom: 20px; font-family: sans-serif; }
        .amnesty-map-search { 
            width: 100%; padding: 12px; margin-bottom: 15px; 
            border: 2px solid #000; border-radius: 4px; font-size: 16px; font-weight: bold;
            box-sizing: border-box;
        }
        .amnesty-map-container { width: 100%; height: 400px; border-radius: 8px; z-index: 1; }
        .amnesty-marker-label { 
            background: <?php echo esc_attr($marker_bg); ?>; 
            color: <?php echo esc_attr($marker_text); ?>; 
            border-radius: 50%; padding: 5px; width: 30px; height: 30px; 
            display: flex; align-items: center; justify-content: center; 
            font-weight: bold; border: 2px solid #fff; box-shadow: 0 0 10px rgba(0,0,0,0.5); font-size: 12px; 
        }
    </style>
    
    <div class="amnesty-map-wrapper">
        <input type="text" id="amnesty-map-search-<?php echo esc_attr($petition_id); ?>" class="amnesty-map-search" placeholder="<?php esc_attr_e('Szukaj miejscowości...', 'amnesty-petitions'); ?>" />
        
        <!-- Kontener mapy -->
        <div id="amnesty-map-<?php echo esc_attr($petition_id); ?>" class="amnesty-map-container"></div>
    </div>
    
    <script>
    (function() {
        function initAmnestyMap() {
            const mapElement = document.getElementById('amnesty-map-<?php echo esc_js($petition_id); ?>');
            const searchInput = document.getElementById('amnesty-map-search-<?php echo esc_js($petition_id); ?>');
            
            if (!mapElement || mapElement.dataset.initialized) return;
            
            if (typeof L === 'undefined') {
                setTimeout(initAmnestyMap, 100);
                return;
            }

            mapElement.dataset.initialized = "true";

            const map = L.map(mapElement.id).fitBounds([[49.002, 14.122], [54.836, 24.146]]);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png').addTo(map);

            let allMarkers = [];
            let mapData = []; 

            const renderMarkers = (filterText = '') => {
                allMarkers.forEach(m => map.removeLayer(m));
                allMarkers = [];

                const lowerFilter = filterText.toLowerCase();

                mapData.forEach(item => {
                    if (item.lat && item.lon) {
                        if (filterText === '' || item.city.toLowerCase().includes(lowerFilter)) {
                            const customIcon = L.divIcon({ 
                                className: 'custom-div-icon', 
                                html: `<div class="amnesty-marker-label">${item.count}</div>`, 
                                iconSize: [30, 30], 
                                iconAnchor: [15, 15] 
                            });
                            const marker = L.marker([item.lat, item.lon], {icon: customIcon})
                                .bindPopup(`<b>${item.city}</b>`);
                            
                            marker.addTo(map);
                            allMarkers.push(marker); 
                        }
                    }
                });
            };

            // Pobieranie danych przez AJAX
            fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>?action=get_amnesty_map_data&petition_id=<?php echo esc_js($petition_id); ?>')
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data.length > 0) {
                    mapData = res.data; 
                    renderMarkers();
                }
            });

            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    renderMarkers(e.target.value);
                });
            }
        }

        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(initAmnestyMap, 1);
        } else {
            document.addEventListener('DOMContentLoaded', initAmnestyMap);
        }
    })();
    </script>
    
    <?php 
    return ob_get_clean();
}
    
 */