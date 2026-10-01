<?php

// 1. REGISTER CUSTOM POST TYPE: MATERIAL ORDERS

add_action( 'init', 'register_materials_order_cpt' );

function register_materials_order_cpt() {
    $labels = array(
        'name'                  => __('Zamówienia (Amnesty)', 'amnestympl'),
        'singular_name'         => __('Zamówienie', 'amnestympl'),
        'menu_name'             => __('Zamówienia', 'amnestympl'),
        'add_new'               => __('Dodaj nowe', 'amnestympl'),
        'add_new_item'          => __('Dodaj zamówienie', 'amnestympl'),
        'edit_item'             => __('Szczegóły zamówienia', 'amnestympl'),
        'all_items'             => __('Wszystkie zamówienia', 'amnestympl'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false, 
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 21, 
        'menu_icon'          => 'dashicons-cart',
        'supports'           => array( 'title', 'custom-fields' ),
    );

    register_post_type( 'material_order', $args );
}

global $order_form_error;
$order_form_error = '';

// 2. HANDLE ORDER SUBMISSION

add_action('template_redirect', 'handle_materials_order_submission');

function handle_materials_order_submission() {
    global $order_form_error;

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order']) ) {
        
        if ( ! isset($_POST['order_nonce']) || ! wp_verify_nonce($_POST['order_nonce'], 'save_order_action') ) {
            $order_form_error = __('Błąd bezpieczeństwa formularza. Odśwież stronę i spróbuj ponownie.', 'amnestympl');
            return;
        }

        $event_id     = intval($_POST['event_id']);
        $package_size = sanitize_text_field($_POST['package_size']);
        $carrier      = sanitize_text_field($_POST['carrier']);
        $contact_name = sanitize_text_field($_POST['contact_name']);
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $email        = sanitize_email($_POST['email']);

        // Determine which locker ID to save based on the carrier
        $locker_id = '';
        if ( $carrier === 'InPost' ) {
            $locker_id = sanitize_text_field($_POST['locker_id_inpost']);
        } elseif ( $carrier === 'DPD' ) {
            $locker_id = sanitize_text_field($_POST['locker_id_dpd']);
        }

        // Validation
        if ( empty($event_id) || empty($package_size) || empty($carrier) || empty($locker_id) || empty($contact_name) || empty($phone_number) ) {
            $order_form_error = __('Wypełnij wszystkie wymagane pola i upewnij się, że wybrano punkt odbioru.', 'amnestympl');
            return;
        }

        // --- SAVE TO DATABASE ---
        $event_title = get_the_title($event_id);
        
        $post_id = wp_insert_post(array(
            'post_title'    => __('Zamówienie: ', 'amnestympl') . $contact_name . ' (' . $event_title . ')',
            'post_type'     => 'material_order',
            'post_status'   => 'publish'
        ));

        if ( $post_id ) {
            update_post_meta($post_id, 'powiazane_wydarzenie_id', $event_id);
            update_post_meta($post_id, 'rozmiar_paczki', $package_size);
            update_post_meta($post_id, 'przewoznik', $carrier);
            update_post_meta($post_id, 'punkt_odbioru_id', $locker_id);
            update_post_meta($post_id, 'osoba_kontaktowa', $contact_name);
            update_post_meta($post_id, 'telefon', $phone_number);
            update_post_meta($post_id, 'email', $email);

            $return_url = wp_get_referer() ? wp_get_referer() : get_permalink();
            wp_safe_redirect(add_query_arg('order_added', '1', $return_url));
            exit; 
        }
    }
}

// 3. SHORTCODE: ORDER FORM WITH INLINE INPOST & DPD LOGIC

add_shortcode('materials_order', 'render_materials_order_form');

function render_materials_order_form() {
    global $order_form_error;

    if ( ! is_user_logged_in() ) {
        return '<div class="alert-message alert-default">' . esc_html__('Musisz być zalogowany, aby zamówić materiały.', 'amnestympl') . '</div>';
    }

    ob_start();

    // 1. RENDER DIGITAL MATERIALS (GLOBAL)
    $global_materials = array();
    for ( $i = 1; $i <= 3; $i++ ) {
        $title = get_option('global_mat_' . $i . '_title');
        $url   = get_option('global_mat_' . $i . '_url');
        if ( ! empty($url) ) {
            $global_materials[] = array( 
                'title' => $title ? $title : __('Pobierz plik', 'amnestympl'), 
                'url'   => $url 
            );
        }
    }

    if ( ! empty($global_materials) ) {
        echo '<div class="global-materials-panel">';
        echo '<h4 class="global-materials-title">📂 ' . esc_html__('Materiały cyfrowe do pobrania:', 'amnestympl') . '</h4>';
        echo '<div class="global-materials-list">';
        foreach ( $global_materials as $mat ) {
            echo '<a href="' . esc_url($mat['url']) . '" target="_blank" class="btn-material-download">';
            echo esc_html($mat['title']) . ' &darr;';
            echo '</a>';
        }
        echo '</div>';
        echo '</div>';
    }

    // 2. CHECK EVENTS FOR PHYSICAL ORDER FORM
    $current_user_id = get_current_user_id();
    $events_args = array(
        'post_type'      => 'wydarzenie',
        'author'         => $current_user_id,
        'posts_per_page' => -1,
    );
    $user_events = new WP_Query($events_args);

    if ( ! $user_events->have_posts() ) {
        echo '<div class="alert-message alert-warning">' . esc_html__('Najpierw musisz dodać wydarzenie, aby móc zamówić paczkę fizyczną.', 'amnestympl') . '</div>';
        return ob_get_clean();
    }

    // Messages
    if ( isset($_GET['order_added']) && $_GET['order_added'] == '1' ) {
        echo '<div class="alert-message alert-success">📦 ' . esc_html__('Dziękujemy! Twoje zamówienie na materiały z Amnesty International zostało przyjęte.', 'amnestympl') . '</div>';
    }

    if ( ! empty($order_form_error) ) {
        echo '<div class="alert-message alert-danger"><strong>' . esc_html__('Błąd:', 'amnestympl') . '</strong> ' . esc_html($order_form_error) . '</div>';
    }

    ?>
    <script src="https://geowidget.easypack24.net/js/sdk-for-javascript.js"></script>
    <link rel="stylesheet" href="https://geowidget.easypack24.net/css/easypack.css"/>

    <form action="" method="post" class="new-event-form order-materials-form">
        <?php wp_nonce_field('save_order_action', 'order_nonce'); ?>

        <div class="form-section">
            <label class="form-label uppercase-title"><?php esc_html_e('Wydarzenie', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
            <select name="event_id" class="form-input form-input-bold" required>
                <option value=""><?php esc_html_e('-- Wybierz wydarzenie --', 'amnestympl'); ?></option>
                <?php
                while ( $user_events->have_posts() ) {
                    $user_events->the_post();
                    echo '<option value="' . esc_attr(get_the_ID()) . '">' . esc_html(get_the_title()) . '</option>';
                }
                wp_reset_postdata();
                ?>
            </select>
        </div>

        <h3 class="form-section-title uppercase-title"><?php esc_html_e('Rozmiar pakietu', 'amnestympl'); ?></h3>
        <div class="form-section custom-radio-list">
            <label class="custom-radio-item">
                <input type="radio" name="package_size" value="Duża" required>
                <span class="radio-custom-circle"></span>
                <div class="radio-text">
                    <span class="radio-main">DUŻY</span>
                    <span class="radio-sub">5 plakatów, 4 karty postaci, wzory listów</span>
                </div>
            </label>
            <label class="custom-radio-item">
                <input type="radio" name="package_size" value="Średnia">
                <span class="radio-custom-circle"></span>
                <div class="radio-text">
                    <span class="radio-main">ŚREDNI</span>
                    <span class="radio-sub">5 plakatów, 4 karty postaci, wzory listów</span>
                </div>
            </label>
            <label class="custom-radio-item">
                <input type="radio" name="package_size" value="Mała">
                <span class="radio-custom-circle"></span>
                <div class="radio-text">
                    <span class="radio-main">MAŁY</span>
                    <span class="radio-sub">5 plakatów, 4 karty postaci, wzory listów</span>
                </div>
            </label>
        </div>

        <div class="form-section split-row">
            <div>
                <input type="text" name="contact_name" class="form-input form-input-bold" placeholder="<?php esc_attr_e('Imię i nazwisko *', 'amnestympl'); ?>" required>
            </div>
            <div>
                <input type="text" name="phone_number" class="form-input form-input-bold" placeholder="<?php esc_attr_e('Telefon (np. 123456789) *', 'amnestympl'); ?>" required>
            </div>
        </div>

        <div class="form-section">
            <input type="email" name="email" class="form-input form-input-bold" placeholder="<?php esc_attr_e('Adres e-mail *', 'amnestympl'); ?>" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" required>
        </div>
        
        <h3 class="form-section-title uppercase-title"><?php esc_html_e('Sposób dostawy', 'amnestympl'); ?></h3>
        <div class="form-section custom-radio-list horizontal">
            <label class="custom-radio-item">
                <input type="radio" name="carrier" id="carrier_dpd" value="DPD" required>
                <span class="radio-custom-circle"></span>
                <div class="radio-text">
                    <span class="radio-main">KURIER DPD</span>
                </div>
            </label>
            <label class="custom-radio-item">
                <input type="radio" name="carrier" id="carrier_inpost" value="InPost">
                <span class="radio-custom-circle"></span>
                <div class="radio-text">
                    <span class="radio-main">INPOST PACZKOMAT</span>
                </div>
            </label>
        </div>

        <div id="section_inpost" class="carrier-box" style="display: none;">
            <label class="form-label"><?php esc_html_e('Wybór Paczkomatu InPost', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
            <input type="hidden" id="locker_id_input_inpost" name="locker_id_inpost">
            
            <div class="carrier-actions-group">
                <div>
                    <button type="button" id="btn_open_inpost" class="btn-inpost">
                        <?php esc_html_e('Otwórz mapę Paczkomatów', 'amnestympl'); ?>
                    </button>
                    <span id="locker_display_inpost" class="inpost-selection-status"><?php esc_html_e('Brak wybranego paczkomatu', 'amnestympl'); ?></span>
                </div>
                
                <div id="inpost-inline-map" class="inpost-map-container" style="display: none;"></div>
            </div>
        </div>

        <div id="section_dpd" class="carrier-box" style="display: none;">
            <label class="form-label"><?php esc_html_e('Wybór Punktu DPD Pickup', 'amnestympl'); ?> <span class="required-asterisk">*</span></label>
            <p class="dpd-instruction">
                <?php echo wp_kses_post(__('Znajdź najbliższy punkt na oficjalnej mapie DPD, a następnie skopiuj jego <strong>kod punktu</strong> (np. PL12345).', 'amnestympl')); ?>
            </p>
            <a href="https://www.dpd.com/pl/pl/dpd-pickup/znajdz-punkt-pickup/" target="_blank" class="btn-dpd">
                &rarr; <?php esc_html_e('Otwórz mapę DPD w nowym oknie', 'amnestympl'); ?>
            </a>
            
            <input type="text" name="locker_id_dpd" id="locker_id_input_dpd" class="form-input form-input-bold" placeholder="<?php esc_attr_e('Wpisz kod punktu (np. PL12345)', 'amnestympl'); ?>">
        </div>

        <div class="form-section form-section-last">
            <button type="submit" name="submit_order" class="btn-yellow-block"><?php esc_html_e('ZŁÓŻ ZAMÓWIENIE', 'amnestympl'); ?></button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const radioInPost  = document.getElementById('carrier_inpost');
            const radioDPD     = document.getElementById('carrier_dpd');
            const secInPost    = document.getElementById('section_inpost');
            const secDPD       = document.getElementById('section_dpd');
            const inputInPost  = document.getElementById('locker_id_input_inpost');
            const inputDPD     = document.getElementById('locker_id_input_dpd');
            
            const txtOpenMap = "<?php esc_attr_e('Otwórz mapę Paczkomatów', 'amnestympl'); ?>";
            const txtCloseMap = "<?php esc_attr_e('Zamknij mapę', 'amnestympl'); ?>";
            const txtSelected = "<?php esc_attr_e('Wybrano: ', 'amnestympl'); ?>";
            
            function toggleCarrier() {
                if (radioInPost.checked) {
                    secInPost.style.display = 'block';
                    secDPD.style.display    = 'none';
                    inputDPD.removeAttribute('required');
                    inputInPost.setAttribute('required', 'required');
                } else if (radioDPD.checked) {
                    secInPost.style.display = 'none';
                    secDPD.style.display    = 'block';
                    inputInPost.removeAttribute('required');
                    inputDPD.setAttribute('required', 'required');
                }
            }

            radioInPost.addEventListener('change', toggleCarrier);
            radioDPD.addEventListener('change', toggleCarrier);

            window.easyPackAsyncInit = function () {
                easyPack.init({});
            };

            let inpostMapLoaded = false;
            
            document.getElementById('btn_open_inpost').addEventListener('click', function() {
                const mapContainer = document.getElementById('inpost-inline-map');
                
                if (mapContainer.style.display === 'none') {
                    mapContainer.style.display = 'block';
                    this.innerText = txtCloseMap;
                    
                    if (!inpostMapLoaded) {
                        easyPack.mapWidget('inpost-inline-map', function(point) {
                            inputInPost.value = point.name;
                            document.getElementById('locker_display_inpost').innerText = txtSelected + point.name;
                            mapContainer.style.display = 'none';
                            document.getElementById('btn_open_inpost').innerText = txtOpenMap;
                        });
                        inpostMapLoaded = true;
                    }
                } else {
                    mapContainer.style.display = 'none';
                    this.innerText = txtOpenMap;
                }
            });
        });
    </script>
    <?php

    return ob_get_clean();
}

// 4. ADMIN: META BOX FOR ORDER DETAILS & STATUS

add_action('add_meta_boxes', 'add_material_order_meta_box');

function add_material_order_meta_box() {
    add_meta_box(
        'material_order_details',             // ID
        __('Szczegóły Zamówienia i Status', 'amnestympl'), // Title
        'render_material_order_meta_box',     // Callback function
        'material_order',                     // Post Type
        'normal',                             // Context
        'high'                                // Priority
    );
}

function render_material_order_meta_box($post) {
    // Add nonce for security
    wp_nonce_field('save_order_status', 'order_status_nonce');

    // Fetch existing data from post meta
    $event_id     = get_post_meta($post->ID, 'powiazane_wydarzenie_id', true);
    $package_size = get_post_meta($post->ID, 'rozmiar_paczki', true);
    $carrier      = get_post_meta($post->ID, 'przewoznik', true);
    $locker_id    = get_post_meta($post->ID, 'punkt_odbioru_id', true);
    $contact      = get_post_meta($post->ID, 'osoba_kontaktowa', true);
    $phone        = get_post_meta($post->ID, 'telefon', true);
    $email        = get_post_meta($post->ID, 'email', true);
    
    // Fetch current status, default to 'oczekujace' if empty
    $status       = get_post_meta($post->ID, 'status_zamowienia', true);
    if ( empty($status) ) {
        $status = 'oczekujace';
    }

    $event_title = $event_id ? get_the_title($event_id) : __('Brak przypisanego wydarzenia', 'amnestympl');
    $event_edit_link = $event_id ? get_edit_post_link($event_id) : '#';

    ?>
    <table class="order-meta-table">
        <tr>
            <th><?php esc_html_e('Wydarzenie:', 'amnestympl'); ?></th>
            <td><a href="<?php echo esc_url($event_edit_link); ?>" target="_blank"><?php echo esc_html($event_title); ?> &rarr;</a></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Osoba kontaktowa:', 'amnestympl'); ?></th>
            <td><?php echo esc_html($contact); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('E-mail:', 'amnestympl'); ?></th>
            <td><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Telefon:', 'amnestympl'); ?></th>
            <td><?php echo esc_html($phone); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Rozmiar paczki:', 'amnestympl'); ?></th>
            <td><strong class="package-size-value"><?php echo esc_html($package_size); ?></strong></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Przewoźnik:', 'amnestympl'); ?></th>
            <td><strong><?php echo esc_html($carrier); ?></strong></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Punkt odbioru (Kod):', 'amnestympl'); ?></th>
            <td><strong class="locker-id-value"><?php echo esc_html($locker_id); ?></strong></td>
        </tr>
    </table>

    <h4 style="margin-bottom: 10px; font-size: 15px;"><?php esc_html_e('Zarządzaj statusem zamówienia', 'amnestympl'); ?></h4>
    <select name="order_status" class="status-select">
        <option value="oczekujace" <?php selected($status, 'oczekujace'); ?>>🟠 <?php esc_html_e('Oczekujące (Nowe)', 'amnestympl'); ?></option>
        <option value="w_realizacji" <?php selected($status, 'w_realizacji'); ?>>🔵 <?php esc_html_e('W realizacji (Pakowanie)', 'amnestympl'); ?></option>
        <option value="wyslane" <?php selected($status, 'wyslane'); ?>>🟢 <?php esc_html_e('Wysłane (W drodze)', 'amnestympl'); ?></option>
        <option value="anulowane" <?php selected($status, 'anulowane'); ?>>🔴 <?php esc_html_e('Anulowane', 'amnestympl'); ?></option>
    </select>
    <p class="description"><?php esc_html_e('Wybierz odpowiedni status, a następnie kliknij przycisk "Zaktualizuj" po prawej stronie ekranu.', 'amnestympl'); ?></p>
    <?php
}

add_action('save_post', 'save_material_order_status');

function save_material_order_status($post_id) {
    // Basic security checks
    if ( ! isset($_POST['order_status_nonce']) || ! wp_verify_nonce($_POST['order_status_nonce'], 'save_order_status') ) {
        return;
    }
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can('edit_post', $post_id) ) {
        return;
    }

    // Save the selected status
    if ( isset($_POST['order_status']) ) {
        update_post_meta($post_id, 'status_zamowienia', sanitize_text_field($_POST['order_status']));
    }
}

// 5. ADMIN: CUSTOM COLUMNS FOR ORDERS LIST

add_filter('manage_material_order_posts_columns', 'set_custom_material_order_columns');

function set_custom_material_order_columns($columns) {
    // Rearrange columns for a cleaner look
    $new_columns = array();
    $new_columns['cb']      = $columns['cb'];
    $new_columns['title']   = __('Nazwa zamówienia', 'amnestympl');
    $new_columns['carrier'] = __('Przewoźnik', 'amnestympl');
    $new_columns['locker']  = __('Punkt odbioru', 'amnestympl');
    $new_columns['status']  = __('Status', 'amnestympl');
    $new_columns['date']    = $columns['date'];
    
    return $new_columns;
}

add_action('manage_material_order_posts_custom_column', 'custom_material_order_column', 10, 2);

function custom_material_order_column($column, $post_id) {
    switch ($column) {
        case 'carrier':
            echo esc_html(get_post_meta($post_id, 'przewoznik', true));
            break;
        case 'locker':
            echo '<strong>' . esc_html(get_post_meta($post_id, 'punkt_odbioru_id', true)) . '</strong>';
            break;
        case 'status':
            $status = get_post_meta($post_id, 'status_zamowienia', true);
            if ( $status === 'wyslane' ) {
                echo '<span class="status-badge-admin status-sent">🟢 ' . esc_html__('Wysłane', 'amnestympl') . '</span>';
            } elseif ( $status === 'w_realizacji' ) {
                echo '<span class="status-badge-admin status-processing">🔵 ' . esc_html__('W realizacji', 'amnestympl') . '</span>';
            } elseif ( $status === 'anulowane' ) {
                echo '<span class="status-badge-admin status-cancelled">🔴 ' . esc_html__('Anulowane', 'amnestympl') . '</span>';
            } else {
                echo '<span class="status-badge-admin status-pending">🟠 ' . esc_html__('Oczekujące', 'amnestympl') . '</span>';
            }
            break;
    }
}

add_action('admin_menu', 'register_global_materials_page');

function register_global_materials_page() {
    $page_hook = add_submenu_page(
        'edit.php?post_type=wydarzenie',     // Parent menu slug
        __('Materiały dla Organizatorów', 'amnestympl'), // Page title
        __('Materiały do pobrania', 'amnestympl'),       // Menu title
        'manage_options',                    // Capability
        'global_materials_settings',         // Menu slug
        'render_global_materials_page'       // Callback function
    );

    // Load WordPress Media Uploader scripts ONLY on this specific settings page
    add_action("admin_print_scripts-{$page_hook}", 'enqueue_media_uploader_scripts');
}

function enqueue_media_uploader_scripts() {
    wp_enqueue_media();
}

function register_global_materials_settings() {
    // Register 3 slots for files (can be easily expanded later)
    for ( $i = 1; $i <= 3; $i++ ) {
        register_setting('global_materials_group', 'global_mat_' . $i . '_title');
        register_setting('global_materials_group', 'global_mat_' . $i . '_url');
    }
}

// 6. ADMIN: GLOBAL MATERIALS SETTINGS PAGE

add_action('admin_init', 'register_global_materials_settings');

function render_global_materials_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Globalne Materiały do Pobrania', 'amnestympl'); ?></h1>
        <p><?php esc_html_e('Wgraj pliki (np. plakaty, petycje), które będą automatycznie dostępne dla wszystkich organizatorów w ich panelach.', 'amnestympl'); ?></p>
        
        <form method="post" action="options.php">
            <?php settings_fields('global_materials_group'); ?>
            
            <table class="form-table admin-materials-table">
                <?php for ( $i = 1; $i <= 3; $i++ ) : 
                    $title = get_option('global_mat_' . $i . '_title');
                    $url   = get_option('global_mat_' . $i . '_url');
                ?>
                <tr valign="top" style="border-bottom: 1px solid #ddd;">
                    <th scope="row" class="material-row-th"><?php printf(esc_html__('Plik #%d', 'amnestympl'), $i); ?></th>
                    <td class="material-row-td">
                        <label class="material-field-label"><strong><?php esc_html_e('Nazwa wyświetlana (np. Plakat PDF):', 'amnestympl'); ?></strong></label>
                        <input type="text" name="global_mat_<?php echo $i; ?>_title" value="<?php echo esc_attr($title); ?>" class="regular-text" style="margin-bottom: 15px;" />
                        
                        <label class="material-field-label"><strong><?php esc_html_e('Plik:', 'amnestympl'); ?></strong></label>
                        <div class="material-input-group">
                            <input type="text" id="mat_url_<?php echo $i; ?>" name="global_mat_<?php echo $i; ?>_url" value="<?php echo esc_attr($url); ?>" class="large-text" />
                            <button type="button" class="button upload-media-btn" data-target="mat_url_<?php echo $i; ?>"><?php esc_html_e('Wybierz z biblioteki', 'amnestympl'); ?></button>
                            <button type="button" class="button btn-remove-media clear-media-btn" data-target="mat_url_<?php echo $i; ?>"><?php esc_html_e('Usuń', 'amnestympl'); ?></button>
                        </div>
                    </td>
                </tr>
                <?php endfor; ?>
            </table>

            <?php submit_button(__('Zapisz Materiały', 'amnestympl')); ?>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($){
        var customUploader;
        
        // Setup localized strings for media uploader title and button
        var txtUploaderTitle = "<?php esc_attr_e('Wybierz lub wgraj plik', 'amnestympl'); ?>";
        var txtUploaderBtn = "<?php esc_attr_e('Użyj tego pliku', 'amnestympl'); ?>";
        
        $('.upload-media-btn').click(function(e) {
            e.preventDefault();
            var targetInput = $('#' + $(this).data('target'));
            
            if (customUploader) { customUploader.open(); return; }
            
            customUploader = wp.media.frames.file_frame = wp.media({
                title: txtUploaderTitle,
                button: { text: txtUploaderBtn },
                multiple: false
            });
            
            customUploader.on('select', function() {
                var attachment = customUploader.state().get('selection').first().toJSON();
                targetInput.val(attachment.url);
            });
            
            customUploader.open();
        });

        $('.clear-media-btn').click(function(e) {
            e.preventDefault();
            $('#' + $(this).data('target')).val('');
        });
    });
    </script>
    <?php
}