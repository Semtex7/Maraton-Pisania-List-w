<?php

// 1. ADMIN: SHIPPING API SETTINGS PAGE

add_action('admin_menu', 'register_shipping_api_settings_page');

function register_shipping_api_settings_page() {
    add_submenu_page(
        'edit.php?post_type=material_order', // Parent menu slug (Material Orders CPT)
        __('Ustawienia API Kurierów', 'amnestympl'), // Page title
        __('Ustawienia API', 'amnestympl'),          // Menu title
        'manage_options',                            // Capability
        'shipping_api_settings',                     // Menu slug
        'render_shipping_api_settings_page'          // Callback function
    );
}

function register_shipping_api_settings() {
    // InPost Credentials
    register_setting('shipping_api_settings_group', 'inpost_api_token');
    register_setting('shipping_api_settings_group', 'inpost_org_id');
    register_setting('shipping_api_settings_group', 'inpost_environment'); // Sandbox or Production
    
    // Sender Details (Required for generating labels)
    register_setting('shipping_api_settings_group', 'shipping_sender_company');
    register_setting('shipping_api_settings_group', 'shipping_sender_email');
    register_setting('shipping_api_settings_group', 'shipping_sender_phone');
    register_setting('shipping_api_settings_group', 'shipping_sender_street');
    register_setting('shipping_api_settings_group', 'shipping_sender_building');
    register_setting('shipping_api_settings_group', 'shipping_sender_city');
    register_setting('shipping_api_settings_group', 'shipping_sender_postcode');
}
add_action('admin_init', 'register_shipping_api_settings');

function render_shipping_api_settings_page() {
    ?>
    <div class="wrap shipping-api-settings">
        <h1><?php esc_html_e('Ustawienia Integracji Kurierskich (InPost / DPD)', 'amnestympl'); ?></h1>
        <p><?php esc_html_e('Wprowadź klucze API oraz dane nadawcy, aby automatycznie generować etykiety przewozowe.', 'amnestympl'); ?></p>
        
        <form method="post" action="options.php">
            <?php settings_fields('shipping_api_settings_group'); ?>
            <?php do_settings_sections('shipping_api_settings_group'); ?>
            
            <h2 class="settings-section-title"><?php esc_html_e('Klucze API - InPost (ShipX)', 'amnestympl'); ?></h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Środowisko', 'amnestympl'); ?></th>
                    <td>
                        <select name="inpost_environment">
                            <option value="sandbox" <?php selected(get_option('inpost_environment'), 'sandbox'); ?>><?php esc_html_e('Sandbox (Testowe)', 'amnestympl'); ?></option>
                            <option value="production" <?php selected(get_option('inpost_environment'), 'production'); ?>><?php esc_html_e('Produkcja (Prawdziwe paczki)', 'amnestympl'); ?></option>
                        </select>
                        <p class="description settings-description"><?php esc_html_e('Testowe: https://sandbox-api-shipx-pl.easypack24.net | Produkcja: https://api-shipx-pl.easypack24.net', 'amnestympl'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Access Token', 'amnestympl'); ?></th>
                    <td><input type="password" name="inpost_api_token" value="<?php echo esc_attr(get_option('inpost_api_token')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Organization ID', 'amnestympl'); ?></th>
                    <td><input type="text" name="inpost_org_id" value="<?php echo esc_attr(get_option('inpost_org_id')); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <h2 class="settings-section-title"><?php esc_html_e('Dane Nadawcy (Na etykiecie)', 'amnestympl'); ?></h2>
            <table class="form-table">
                <tr valign="top"><th scope="row"><?php esc_html_e('Nazwa organizacji', 'amnestympl'); ?></th><td><input type="text" name="shipping_sender_company" value="<?php echo esc_attr(get_option('shipping_sender_company')); ?>" class="regular-text" /></td></tr>
                <tr valign="top"><th scope="row"><?php esc_html_e('E-mail', 'amnestympl'); ?></th><td><input type="email" name="shipping_sender_email" value="<?php echo esc_attr(get_option('shipping_sender_email')); ?>" class="regular-text" /></td></tr>
                <tr valign="top"><th scope="row"><?php esc_html_e('Telefon (bez +48)', 'amnestympl'); ?></th><td><input type="text" name="shipping_sender_phone" value="<?php echo esc_attr(get_option('shipping_sender_phone')); ?>" class="regular-text" /></td></tr>
                <tr valign="top"><th scope="row"><?php esc_html_e('Ulica', 'amnestympl'); ?></th><td><input type="text" name="shipping_sender_street" value="<?php echo esc_attr(get_option('shipping_sender_street')); ?>" class="regular-text" /></td></tr>
                <tr valign="top"><th scope="row"><?php esc_html_e('Nr budynku/lokalu', 'amnestympl'); ?></th><td><input type="text" name="shipping_sender_building" value="<?php echo esc_attr(get_option('shipping_sender_building')); ?>" class="regular-text" /></td></tr>
                <tr valign="top"><th scope="row"><?php esc_html_e('Miasto', 'amnestympl'); ?></th><td><input type="text" name="shipping_sender_city" value="<?php echo esc_attr(get_option('shipping_sender_city')); ?>" class="regular-text" /></td></tr>
                <tr valign="top"><th scope="row"><?php esc_html_e('Kod pocztowy (00-000)', 'amnestympl'); ?></th><td><input type="text" name="shipping_sender_postcode" value="<?php echo esc_attr(get_option('shipping_sender_postcode')); ?>" class="regular-text" /></td></tr>
            </table>

            <?php submit_button(__('Zapisz Ustawienia', 'amnestympl')); ?>
        </form>
    </div>
    <?php
}

// 2. AJAX: GENERATE INPOST SHIPMENT

add_action('wp_ajax_generate_inpost_label', 'ajax_generate_inpost_label');

function ajax_generate_inpost_label() {
    if ( ! current_user_can('manage_options') || ! isset($_POST['order_id']) ) {
        wp_send_json_error(__('Brak uprawnień.', 'amnestympl'));
    }

    $order_id = intval($_POST['order_id']);
    
    $token  = get_option('inpost_api_token');
    $org_id = get_option('inpost_org_id');
    $env    = get_option('inpost_environment') === 'sandbox' ? 'sandbox-api-shipx-pl.easypack24.net' : 'api-shipx-pl.easypack24.net';

    if ( empty($token) || empty($org_id) ) {
        wp_send_json_error(__('Brak kluczy API InPost w ustawieniach!', 'amnestympl'));
    }

    // Map order details
    $package_size = get_post_meta($order_id, 'rozmiar_paczki', true);
    $locker_id    = get_post_meta($order_id, 'punkt_odbioru_id', true);
    $contact      = get_post_meta($order_id, 'osoba_kontaktowa', true);
    $phone        = get_post_meta($order_id, 'telefon', true);
    $email        = get_post_meta($order_id, 'email', true);

    // Map to InPost dimensions
    $template = 'template_a'; // Mała
    if ( $package_size === 'Średnia' ) $template = 'template_b';
    if ( $package_size === 'Duża' ) $template = 'template_c';

    // Prepare JSON Payload
    $payload = array(
        'receiver' => array(
            'company_name'  => $contact,
            'email'         => $email,
            'phone'         => str_replace(array(' ', '-'), '', $phone),
        ),
        'sender' => array(
            'company_name'  => get_option('shipping_sender_company'),
            'email'         => get_option('shipping_sender_email'),
            'phone'         => str_replace(array(' ', '-'), '', get_option('shipping_sender_phone')),
            'address' => array(
                'street'          => get_option('shipping_sender_street'),
                'building_number' => get_option('shipping_sender_building'),
                'city'            => get_option('shipping_sender_city'),
                'post_code'       => get_option('shipping_sender_postcode'),
                'country_code'    => 'PL'
            )
        ),
        'parcels' => array(
            array('template' => $template)
        ),
        'custom_attributes' => array(
            'target_point' => $locker_id,
        ),
        'service' => 'inpost_locker_standard'
    );

    $api_url = "https://{$env}/v1/organizations/{$org_id}/shipments";

    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ),
        'body'    => wp_json_encode($payload),
        'timeout' => 20
    ));

    if ( is_wp_error($response) ) {
        wp_send_json_error(__('Błąd łączenia z serwerem InPost: ', 'amnestympl') . $response->get_error_message());
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body_json   = json_decode(wp_remote_retrieve_body($response));

    if ( $status_code == 201 && isset($body_json->id) ) {
        // Success! Save tracking and shipment ID
        update_post_meta($order_id, 'inpost_shipment_id', sanitize_text_field($body_json->id));
        update_post_meta($order_id, 'inpost_tracking_number', sanitize_text_field($body_json->tracking_number));
        update_post_meta($order_id, 'status_zamowienia', 'w_realizacji'); // Auto-update status!
        
        wp_send_json_success(__('Etykieta została pomyślnie wygenerowana! Status zaktualizowany na "W realizacji".', 'amnestympl'));
    } else {
        // Error from InPost
        $error_details = isset($body_json->message) ? $body_json->message : __('Nieznany błąd', 'amnestympl');
        wp_send_json_error(__('Odrzucono przez InPost (Kod ', 'amnestympl') . $status_code . '): ' . $error_details);
    }
}

// 3. ADMIN POST: DOWNLOAD PDF LABEL

add_action('admin_post_download_inpost_label', 'handle_download_inpost_label');

function handle_download_inpost_label() {
    if ( ! current_user_can('manage_options') || ! isset($_GET['order_id']) ) {
        wp_die(__('Brak uprawnień.', 'amnestympl'));
    }

    $order_id    = intval($_GET['order_id']);
    $shipment_id = get_post_meta($order_id, 'inpost_shipment_id', true);

    if ( empty($shipment_id) ) {
        wp_die(__('Brak wygenerowanej przesyłki dla tego zamówienia.', 'amnestympl'));
    }

    $token = get_option('inpost_api_token');
    $env   = get_option('inpost_environment') === 'sandbox' ? 'sandbox-api-shipx-pl.easypack24.net' : 'api-shipx-pl.easypack24.net';
    
    $api_url = "https://{$env}/v1/shipments/{$shipment_id}/label?type=pdf";

    $response = wp_remote_get($api_url, array(
        'headers' => array('Authorization' => 'Bearer ' . $token),
        'timeout' => 20
    ));

    if ( is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200 ) {
        wp_die(__('Błąd podczas pobierania etykiety. Spróbuj ponownie później.', 'amnestympl'));
    }

    // Serve the PDF directly to the browser
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="inpost_etykieta_' . $shipment_id . '.pdf"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    echo wp_remote_retrieve_body($response);
    exit;
}