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

        <div class="form-section split-row">
            <div>
                <label class="form-label"><?php esc_html_e('Liczba podpisanych listów', 'amnestympl'); ?></label>
                <input type="number" name="letters_signed" class="form-input" min="0" step="1" inputmode="numeric">
            </div>
            <div>
                <label class="form-label"><?php esc_html_e('Liczba uczestników', 'amnestympl'); ?></label>
                <input type="number" name="participants_count" class="form-input" min="0" step="1" inputmode="numeric">
            </div>
        </div>

        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Czy ktoś pobrał materiały na wydarzenie?', 'amnestympl'); ?></label>
            <select name="materials_taken" class="form-input">
                <option value="unknown"><?php esc_html_e('Nie wiem / nie dotyczy', 'amnestympl'); ?></option>
                <option value="yes"><?php esc_html_e('Tak', 'amnestympl'); ?></option>
                <option value="no"><?php esc_html_e('Nie', 'amnestympl'); ?></option>
            </select>
        </div>

        <div class="form-section">
            <label class="form-label"><?php esc_html_e('Dodatkowe informacje o materiałach', 'amnestympl'); ?></label>
            <textarea name="materials_notes" class="form-textarea" rows="3" placeholder="<?php esc_attr_e('Np. jakie materiały wykorzystano lub czego zabrakło.', 'amnestympl'); ?>"></textarea>
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
    $event_post     = get_post($event_id);

    if ( ! $event_post || 'wydarzenie' !== $event_post->post_type || (int) $event_post->post_author !== get_current_user_id() ) {
        wp_send_json_error( __('Nie masz uprawnień do tego wydarzenia.', 'amnestympl') );
    }

    if ( empty($report_content) ) {
        wp_send_json_error( __('Treść raportu jest wymagana.', 'amnestympl') );
    }

    $letters_signed    = isset( $_POST['letters_signed'] ) ? max( 0, intval( $_POST['letters_signed'] ) ) : 0;
    $participants_count = isset( $_POST['participants_count'] ) ? max( 0, intval( $_POST['participants_count'] ) ) : 0;
    $materials_taken   = isset( $_POST['materials_taken'] ) ? sanitize_key( $_POST['materials_taken'] ) : 'unknown';
    $materials_notes   = isset( $_POST['materials_notes'] ) ? sanitize_textarea_field( $_POST['materials_notes'] ) : '';

    if ( ! in_array( $materials_taken, array( 'yes', 'no', 'unknown' ), true ) ) {
        $materials_taken = 'unknown';
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
    update_post_meta($event_id, 'liczba_podpisanych_listow', $letters_signed);
    update_post_meta($event_id, 'liczba_uczestnikow', $participants_count);
    update_post_meta($event_id, 'materialy_pobrane', $materials_taken);
    update_post_meta($event_id, 'materialy_uwagi', $materials_notes);
    
    if ( ! empty($photos_url) ) {
        update_post_meta($event_id, 'link_do_zdjec', $photos_url);
    }
    if ( ! empty($zip_url) ) {
        update_post_meta($event_id, 'plik_zip_url', $zip_url);
    }

    wp_send_json_success( __('Raport został pomyślnie dopisany do wydarzenia! Dziękujemy.', 'amnestympl') );
}

// 3. FRONTEND: DOWNLOAD A PRELIMINARY PDF REPORT

add_action( 'template_redirect', 'amnesty_download_event_report_pdf' );

function amnesty_download_event_report_pdf() {
    if ( ! isset( $_GET['download_report'] ) || ! is_user_logged_in() ) {
        return;
    }

    $event_id = absint( $_GET['download_report'] );
    $event    = get_post( $event_id );

    if ( ! $event || 'wydarzenie' !== $event->post_type || (int) $event->post_author !== get_current_user_id() ) {
        wp_die( esc_html__( 'Nie masz dostępu do tego raportu.', 'amnestympl' ), 403 );
    }

    if ( '1' !== get_post_meta( $event_id, 'raport_zlozony', true ) ) {
        wp_die( esc_html__( 'Raport nie został jeszcze złożony.', 'amnestympl' ), 400 );
    }

    $field = static function ( $key ) use ( $event_id ) {
        return function_exists( 'get_field' ) ? get_field( $key, $event_id ) : get_post_meta( $event_id, $key, true );
    };

    $materials_taken = get_post_meta( $event_id, 'materialy_pobrane', true );
    $materials_label = array(
        'yes'     => 'Tak',
        'no'      => 'Nie',
        'unknown' => 'Nie wiem / nie dotyczy',
    );

    $lines = array(
        'RAPORT Z WYDARZENIA',
        '========================================',
        'Wydarzenie: ' . $event->post_title,
        'Organizator: ' . get_the_author_meta( 'display_name', $event->post_author ),
        'Rozpoczecie: ' . $field( 'data_czas_rozpoczecia' ),
        'Zakonczenie: ' . $field( 'data_czas_zakonczenia' ),
        'Miejsce: ' . $field( 'nazwa_lokalu' ),
        'Adres: ' . $field( 'ulica' ) . ', ' . $field( 'kod_pocztowy' ) . ' ' . $field( 'miejscowosc' ),
        'Wydarzenie publiczne: ' . ( $field( 'wydarzenie_publiczne' ) ? 'Tak' : 'Nie' ),
        'Dostepnosc: ' . ( $field( 'dostepnosc' ) ? 'Tak' : 'Nie' ),
        'Podpisane listy: ' . get_post_meta( $event_id, 'liczba_podpisanych_listow', true ),
        'Uczestnicy: ' . get_post_meta( $event_id, 'liczba_uczestnikow', true ),
        'Materialy pobrane: ' . ( $materials_label[ $materials_taken ] ?? $materials_label['unknown'] ),
        'Uwagi o materialach: ' . get_post_meta( $event_id, 'materialy_uwagi', true ),
        '',
        'PODSUMOWANIE',
        get_post_meta( $event_id, 'tresc_raportu', true ),
        '',
        'Link do zdjec: ' . get_post_meta( $event_id, 'link_do_zdjec', true ),
        'Wygenerowano: ' . current_time( 'Y-m-d H:i' ),
    );

    $pdf = amnesty_build_basic_pdf( $lines );
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="raport-wydarzenia-' . $event_id . '.pdf"' );
    echo $pdf;
    exit;
}

function amnesty_build_basic_pdf( $lines ) {
    $pdf_lines = array();

    foreach ( $lines as $line ) {
        $line = remove_accents( wp_strip_all_tags( (string) $line ) );
        $line = preg_replace( '/\s+/', ' ', $line );
        foreach ( explode( "\n", wordwrap( $line, 86, "\n", true ) ) as $wrapped_line ) {
            $pdf_lines[] = $wrapped_line;
        }
    }

    $content = "BT\n/F1 11 Tf\n50 790 Td\n";
    foreach ( array_slice( $pdf_lines, 0, 43 ) as $line ) {
        $line = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $line );
        $content .= '(' . $line . ') Tj\n0 -17 Td\n';
    }
    $content .= "ET";

    $objects = array(
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        '<< /Length ' . strlen( $content ) . " >>\nstream\n" . $content . "\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    );

    $pdf = "%PDF-1.4\n";
    $offsets = array( 0 );
    foreach ( $objects as $index => $object ) {
        $number = $index + 1;
        $offsets[] = strlen( $pdf );
        $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xref_offset = strlen( $pdf );
    $pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
    for ( $index = 1; $index <= count( $objects ); $index++ ) {
        $pdf .= sprintf( "%010d 00000 n \n", $offsets[ $index ] );
    }
    $pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref_offset . "\n%%EOF";

    return $pdf;
}

// 4. ADMIN: META BOX TO VIEW THE REPORT IN THE EVENT EDITOR

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
    $letters = get_post_meta($post->ID, 'liczba_podpisanych_listow', true);
    $participants = get_post_meta($post->ID, 'liczba_uczestnikow', true);
    $materials = get_post_meta($post->ID, 'materialy_pobrane', true);
    $materials_notes = get_post_meta($post->ID, 'materialy_uwagi', true);
    $materials_label = array(
        'yes' => 'Tak',
        'no' => 'Nie',
        'unknown' => 'Nie wiem / nie dotyczy',
    );
    
    ?>
    <table class="form-table report-meta-table">
        <tr>
            <th scope="row" class="report-table-th"><?php esc_html_e('Treść raportu:', 'amnestympl'); ?></th>
            <td class="report-table-td">
                <div class="report-content-box"><?php echo esc_html($content); ?></div>
            </td>
        </tr>
        <tr>
            <th scope="row" class="report-table-th"><?php esc_html_e('Podpisane listy:', 'amnestympl'); ?></th>
            <td class="report-table-td"><?php echo esc_html($letters !== '' ? $letters : '-'); ?></td>
        </tr>
        <tr>
            <th scope="row" class="report-table-th"><?php esc_html_e('Uczestnicy:', 'amnestympl'); ?></th>
            <td class="report-table-td"><?php echo esc_html($participants !== '' ? $participants : '-'); ?></td>
        </tr>
        <tr>
            <th scope="row" class="report-table-th"><?php esc_html_e('Materiały pobrane:', 'amnestympl'); ?></th>
            <td class="report-table-td"><?php echo esc_html($materials_label[$materials] ?? $materials_label['unknown']); ?></td>
        </tr>
        <tr>
            <th scope="row" class="report-table-th"><?php esc_html_e('Uwagi o materiałach:', 'amnestympl'); ?></th>
            <td class="report-table-td"><?php echo esc_html($materials_notes ?: '-'); ?></td>
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