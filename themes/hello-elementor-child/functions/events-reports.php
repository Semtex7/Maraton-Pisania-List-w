<?php

add_action('wp_ajax_get_report_form', 'ajax_get_report_form');

function ajax_get_report_form() {
    if ( ! is_user_logged_in() || ! isset($_POST['post_id']) ) {
        wp_send_json_error( __('Brak autoryzacji.', 'amnestympl') );
    }

    $event_id = intval($_POST['post_id']);
    $event_post = get_post($event_id);

    if ( !$event_post || $event_post->post_author != get_current_user_id() ) {
        wp_send_json_error( __('Nie masz uprawnień do tego wydarzenia.', 'amnestympl') );
    }

    ob_start();
    ?>
    <form id="ajax-report-form" method="post" enctype="multipart/form-data" class="new-event-form">
        <input type="hidden" name="action" value="save_event_report">
        <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
        <?php wp_nonce_field('save_report_action', 'report_nonce'); ?>

        <h3 class="form-title">
            <?php esc_html_e('Składanie raportu z wydarzenia:', 'amnestympl'); ?> <br>
            <span class="event-title-highlight"><?php echo esc_html(get_the_title($event_id)); ?></span>
        </h3>
        
        <p class="form-description"><?php esc_html_e('Po wysłaniu raportu zostanie on na stałe przypisany do tego wydarzenia.', 'amnestympl'); ?></p>

        <div class="form-section">
            <label class="form-label">
                <?php esc_html_e('Treść raportu / Podsumowanie', 'amnestympl'); ?> <span class="required-asterisk">*</span>
            </label>
            <textarea name="report_content" class="form-textarea" required rows="6" placeholder="<?php esc_attr_e('Opisz krótko jak przebiegło wydarzenie, ile osób wzięło udział, jakie były reakcje...', 'amnestympl'); ?>"></textarea>
        </div>

        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Link do zdjęć (np. Google Drive, Dropbox)', 'amnestympl'); ?></label>
            <input type="url" name="photos_url" class="form-input" placeholder="https://...">
        </div>

        <div class="form-section form-section-last">
            <label class="form-label"><?php esc_html_e('Załącznik ZIP (Archiwum ze zdjęciami/dokumentami - Max 50MB)', 'amnestympl'); ?></label>
            <input type="file" name="report_zip" class="form-file-input" accept=".zip">
        </div>

        <div class="form-actions">
            <button type="submit" id="ajax-report-submit-button" class="save-button"><?php esc_html_e('Wyślij raport', 'amnestympl'); ?></button>
            <button type="button" id="cancel-ajax-report" class="cancel-button"><?php esc_html_e('Anuluj', 'amnestympl'); ?></button>
        </div>
        
        <div id="report-status-message" class="status-message"></div>
    </form>
    <?php
    $html = ob_get_clean();
    wp_send_json_success($html);
}

// 2. AJAX: SAVE REPORT AS EVENT META & HANDLE ZIP UPLOAD

add_action('wp_ajax_save_event_report', 'ajax_save_event_report');

function ajax_save_event_report() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( __('Brak autoryzacji.', 'amnestympl') );
    }

    if ( ! isset($_POST['report_nonce']) || ! wp_verify_nonce($_POST['report_nonce'], 'save_report_action') ) {
        wp_send_json_error( __('Błąd bezpieczeństwa tokenu Nonce.', 'amnestympl') );
    }

    $event_id       = intval($_POST['event_id']);
    $report_content = sanitize_textarea_field($_POST['report_content']);
    $photos_url     = esc_url_raw($_POST['photos_url']);

    if ( empty($report_content) ) {
        wp_send_json_error( __('Treść raportu jest wymagana.', 'amnestympl') );
    }

    $zip_url = '';
    
    // Process ZIP file upload if present
    if ( ! empty($_FILES['report_zip']['name']) ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
        }

        $uploaded_file = $_FILES['report_zip'];
        
        $file_type = wp_check_filetype($uploaded_file['name']);
        if ( $file_type['ext'] !== 'zip' ) {
            wp_send_json_error( __('Dozwolone są wyłącznie pliki z rozszerzeniem .zip', 'amnestympl') );
        }

        $upload_overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $uploaded_file, $upload_overrides );

        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $zip_url = $movefile['url']; 
        } else {
            wp_send_json_error( __('Błąd podczas wgrywania pliku ZIP: ', 'amnestympl') . $movefile['error'] );
        }
    }

    // Attach data directly to the Event Post Meta
    update_post_meta($event_id, 'raport_zlozony', '1');
    update_post_meta($event_id, 'tresc_raportu', $report_content);
    
    if ( ! empty($photos_url) ) {
        update_post_meta($event_id, 'link_do_zdjec', $photos_url);
    }
    if ( ! empty($zip_url) ) {
        update_post_meta($event_id, 'plik_zip_url', $zip_url);
    }

    wp_send_json_success( __('Raport został pomyślnie dopisany do wydarzenia! Dziękujemy.', 'amnestympl') );
}

// 3. ADMIN: META BOX TO VIEW THE REPORT IN THE EVENT EDITOR

add_action('add_meta_boxes', 'add_event_report_view_meta_box');

function add_event_report_view_meta_box() {
    add_meta_box(
        'event_report_details',           // ID
        __('Raport z Wydarzenia', 'amnestympl'), // Title
        'render_event_report_meta_box',   // Callback function
        'wydarzenie',                     // Post Type
        'normal',                         // Context
        'high'                            // Priority
    );
}

function render_event_report_meta_box($post) {
    $is_submitted = get_post_meta($post->ID, 'raport_zlozony', true);
    
    if ( $is_submitted !== '1' ) {
        echo '<p>' . esc_html__('Organizator nie złożył jeszcze raportu z tego wydarzenia.', 'amnestympl') . '</p>';
        return;
    }

    $content = get_post_meta($post->ID, 'tresc_raportu', true);
    $link    = get_post_meta($post->ID, 'link_do_zdjec', true);
    $zip     = get_post_meta($post->ID, 'plik_zip_url', true);
    
    ?>
    <table class="form-table report-meta-table">
        <tr>
            <th scope="row" class="report-table-th"><?php esc_html_e('Treść raportu:', 'amnestympl'); ?></th>
            <td class="report-table-td">
                <div class="report-content-box"><?php echo esc_html($content); ?></div>
            </td>
        </tr>
        <tr>
            <th scope="row" class="report-table-th"><?php esc_html_e('Zewnętrzny link do zdjęć:', 'amnestympl'); ?></th>
            <td class="report-table-td">
                <?php if ( ! empty($link) ) : ?>
                    <a href="<?php echo esc_url($link); ?>" target="_blank" class="button"><?php esc_html_e('Otwórz folder ze zdjęciami &rarr;', 'amnestympl'); ?></a>
                <?php else : ?>
                    <em><?php esc_html_e('Brak podanego linku.', 'amnestympl'); ?></em>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th scope="row" class="report-table-th"><?php esc_html_e('Wgrany plik ZIP:', 'amnestympl'); ?></th>
            <td class="report-table-td">
                <?php if ( ! empty($zip) ) : ?>
                    <a href="<?php echo esc_url($zip); ?>" class="button button-primary" download><?php esc_html_e('Pobierz paczkę ZIP &darr;', 'amnestympl'); ?></a>
                <?php else : ?>
                    <em><?php esc_html_e('Brak wgranego pliku ZIP.', 'amnestympl'); ?></em>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}