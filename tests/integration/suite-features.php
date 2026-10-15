<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) exit; // Only via run.php (WP-CLI)
// Suites 8-11: event dates, panel edit, shortcodes, Salesforce Topic search, removed items. Loaded by run.php.
global $wpdb;

// ---------------------------------------------------------------- 8. Event dates and map flag migration
echo "\n== 8. Daty wydarzeń i migracja flagi mapy ==\n";
t( 'migracja wykonana (wersja 1)', 1 === (int) get_option( 'maraton_events_migration' ) );
$no_flag = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='widoczne_na_mapie' WHERE p.post_type='wydarzenie' AND m.meta_id IS NULL" );
t( 'każde wydarzenie ma flagę widoczne_na_mapie', 0 === $no_flag, "bez flagi: $no_flag" );

begin();
$ev = wp_insert_post( array( 'post_type' => 'wydarzenie', 'post_status' => 'publish', 'post_title' => 'Test daty' ) );
update_post_meta( $ev, 'data_rozpoczecia', '2026-12-05 00:00:00' );
update_post_meta( $ev, 'godzina_rozpoczecia', '17:30' );
t( 'data z formularza (data + godzina)', maraton_get_event_datetime( $ev, 'start' ) === '2026-12-05 17:30:00', maraton_get_event_datetime( $ev, 'start' ) );
t( 'brak daty końca → pusto', maraton_get_event_datetime( $ev, 'end' ) === '' );
update_post_meta( $ev, 'data_czas_rozpoczecia', '2026-12-06 10:00:00' );
t( 'data_czas_* ma pierwszeństwo', maraton_get_event_datetime( $ev, 'start' ) === '2026-12-06 10:00:00' );
t( 'format listy: jeden dzień z godzinami', maraton_format_event_dates( '2026-12-06 10:00:00', '2026-12-06 14:00:00' ) === '06.12.2026, 10:00 – 14:00' );
t( 'format listy: sama data', maraton_format_event_dates( '2026-12-06', '' ) === '06.12.2026' );
$dm = maraton_event_date_meta( '2026-12-05T17:30', '2026-12-06T09:15' );
t( 'formularz: datetime-local → oba formaty dat', ! is_wp_error( $dm ) && $dm['data_czas_rozpoczecia'] === '2026-12-05 17:30:00'
    && $dm['data_rozpoczecia'] === '2026-12-05' && $dm['godzina_rozpoczecia'] === '17:30' && $dm['godzina_zakonczenia'] === '09:15' );
t( 'formularz: brak końca → puste pola końca', ! is_wp_error( $dm = maraton_event_date_meta( '2026-12-05T17:30', '' ) ) && '' === $dm['data_czas_zakonczenia'] && '' === $dm['data_zakonczenia'] );
t( 'formularz: brak rozpoczęcia → błąd', is_wp_error( maraton_event_date_meta( '', '' ) ) );
t( 'formularz: koniec przed startem → błąd', is_wp_error( maraton_event_date_meta( '2026-12-05T17:30', '2026-12-05T10:00' ) ) );
rollback();

// Panel edit: prefill, map checkbox, save in both formats
$event = get_posts( array( 'post_type' => 'wydarzenie', 'post_status' => 'publish', 'numberposts' => 1 ) );
if ( $event ) {
    $event = $event[0];
    begin();
    wp_set_current_user( $event->post_author );
    add_filter( 'wp_doing_ajax', '__return_true' );
    $_POST = array( 'post_id' => $event->ID );
    $_REQUEST = $_POST; ob_start(); try { ajax_get_event_form(); } catch ( Test_Json $e ) {} $json = json_decode( ob_get_clean(), true );
    $html = $json['data'] ?? '';
    t( 'edycja: formularz z checkboxem „widoczne na mapie”', strpos( $html, 'name="show_on_map"' ) !== false );
    $expected_start = maraton_get_event_datetime( $event->ID, 'start' );
    t( 'edycja: data rozpoczęcia wypełniona', $expected_start === '' || preg_match( '#name="start_datetime"[^>]*value="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}"#', $html ), $expected_start );
    $_POST = array(
        'post_id_to_edit' => $event->ID, 'ajax_edit_nonce' => wp_create_nonce( 'ajax_edit_action' ),
        'organizer_name' => $event->post_title, 'start_datetime' => '2026-12-07T18:00', 'end_datetime' => '2026-12-07T21:00',
        'venue_name' => 'X', 'street' => get_post_meta( $event->ID, 'ulica', true ) ?: 'Test 1', 'city' => get_post_meta( $event->ID, 'miejscowosc', true ) ?: 'Warszawa', 'zip_code' => '',
    ); // show_on_map unchecked
    $_REQUEST = $_POST; ob_start(); try { ajax_save_event_form(); } catch ( Test_Json $e ) {} $save_resp = ob_get_clean(); echo '     odpowiedź zapisu: ' . $save_resp . "\n";
    t( 'zapis: odznaczenie mapy → 0', '0' === (string) get_post_meta( $event->ID, 'widoczne_na_mapie', true ) );
    t( 'zapis: data_czas_* i data/godzina zgodne', get_post_meta( $event->ID, 'data_czas_rozpoczecia', true ) === '2026-12-07 18:00:00'
        && substr( get_post_meta( $event->ID, 'data_rozpoczecia', true ), 0, 10 ) === '2026-12-07' && get_post_meta( $event->ID, 'godzina_zakonczenia', true ) === '21:00' );
    remove_filter( 'wp_doing_ajax', '__return_true' );
    $_POST = array();
    wp_set_current_user( 0 );
    rollback();
}

// ---------------------------------------------------------------- 9. Shortcodes: CTA, map link, sign-all link
echo "\n== 9. Shortcode'y i linki ==\n";
$cta = do_shortcode( '[maraton_cta]' );
t( '[maraton_cta] gość → rejestracja', strpos( $cta, '/zaloguj-sie/' ) !== false && strpos( $cta, 'Zarejestruj' ) !== false );
wp_set_current_user( $event ? $event->post_author : 1 );
$cta = do_shortcode( '[maraton_cta]' );
t( '[maraton_cta] zalogowany → dodaj wydarzenie', strpos( $cta, '/panel/#zakladka-dodaj-wydarzenie' ) !== false );
wp_set_current_user( 0 );
t( '[events_map] z przyciskiem „Sprawdź wszystkie” → /wydarzenie/', (bool) preg_match( '#class="events-map-all-link" href="[^"]*/wydarzenie/"#', do_shortcode( '[events_map]' ) ) );
t( '[events_map show_all="0"] bez przycisku', strpos( do_shortcode( '[events_map show_all="0"]' ), 'events-map-all-link' ) === false );
t( 'link „zobacz wszystkie” domyślnie → archiwum petycji', amnesty_get_all_petitions_url() === get_post_type_archive_link( 'amnesty_petition' ) );

// ---------------------------------------------------------------- 10. Salesforce topic search (HTTP mocked)
echo "\n== 10. Wyszukiwarka Salesforce Topic (odpowiedzi symulowane) ==\n";
t( 'escapowanie LIKE', amnesty_sf_escape_like( "50% O'Brien_x\\y" ) === "50\\% O\\'Brien\\_x\\\\y" );
$GLOBALS['sf_query_url'] = '';
$mock = function ( $pre, $args, $url ) {
    if ( strpos( $url, '/oauth2/token' ) !== false ) return array( 'body' => '{"access_token":"T"}', 'response' => array( 'code' => 200 ), 'headers' => array(), 'cookies' => array() );
    if ( strpos( $url, '/query/' ) !== false ) { $GLOBALS['sf_query_url'] = $url; return array( 'body' => '{"records":[{"Name":"PETYCJA: Test A"},{"Name":"PETYCJA: Test B"}]}', 'response' => array( 'code' => 200 ), 'headers' => array(), 'cookies' => array() ); }
    return $pre;
};
add_filter( 'pre_http_request', $mock, 5, 3 );
begin();
$names = amnesty_sf_search_topics( "Test'" );
t( 'wynik: lista nazw', $names === array( 'PETYCJA: Test A', 'PETYCJA: Test B' ), is_wp_error( $names ) ? $names->get_error_message() : '' );
t( 'zapytanie SOQL z escapowanym LIKE', strpos( rawurldecode( $GLOBALS['sf_query_url'] ), "WHERE Name LIKE '%Test\\'%'" ) !== false, rawurldecode( substr( $GLOBALS['sf_query_url'], strpos( $GLOBALS['sf_query_url'], '?q=' ) ) ) );
add_filter( 'wp_doing_ajax', '__return_true' );
$sub = get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( $sub ? $sub[0] : 0 );
$_POST = array( 'nonce' => wp_create_nonce( 'amnesty_sf_topics' ), 'term' => 'x' );
$_REQUEST = $_POST; ob_start(); try { amnesty_sf_ajax_search_topics(); } catch ( Test_Json $e ) {} $r = json_decode( ob_get_clean(), true );
t( 'AJAX: subskrybent odrzucony', isset( $r['success'] ) && false === $r['success'] );
wp_set_current_user( $admins[0] );
$_POST = array( 'nonce' => wp_create_nonce( 'amnesty_sf_topics' ), 'term' => 'Test' );
$_REQUEST = $_POST; ob_start(); try { amnesty_sf_ajax_search_topics(); } catch ( Test_Json $e ) {} $r = json_decode( ob_get_clean(), true );
t( 'AJAX: admin dostaje listę', ! empty( $r['success'] ) && count( $r['data'] ) === 2 );
$_POST = array(); wp_set_current_user( 0 );
remove_filter( 'wp_doing_ajax', '__return_true' );
rollback();
remove_filter( 'pre_http_request', $mock, 5 );

// ---------------------------------------------------------------- 11. Removed / fixed items
echo "\n== 11. Usunięte i poprawione elementy ==\n";
t( 'moduł InPost niezaładowany', ! has_action( 'wp_ajax_generate_inpost_label' ) && ! function_exists( 'ajax_generate_inpost_label' ) );
t( 'nowy moduł listy wydarzeń załadowany', function_exists( 'maraton_get_events_list_items' ) );
wp_set_current_user( $admins[0] );
$petition = get_posts( array( 'post_type' => 'amnesty_petition', 'numberposts' => 1 ) );
ob_start(); amnesty_petition_render_meta_box( $petition[0] ); $box = ob_get_clean();
t( 'metabox petycji: przycisk „+ Dodaj materiał” ma etykietę', strpos( $box, '+ Dodaj materiał' ) !== false );
t( 'metabox petycji: pole Topic z podpowiedziami', strpos( $box, 'list="amnesty-sf-topic-list"' ) !== false );
ob_start(); amnesty_render_global_settings_page(); $html = ob_get_clean();
t( 'ustawienia: pole linku „zobacz wszystkie” i tekstu zgody', strpos( $html, 'amnesty_link_all_petitions' ) && strpos( $html, 'name="amnesty_text_consent_sign_all"' ) );
wp_set_current_user( 0 );
