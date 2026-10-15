<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) exit; // Only via run.php (WP-CLI)
// Suites 1-7: settings, edition counters, sign-all, registration, material orders, Elementor widget, log.
// Loaded by run.php (helpers t(), begin(), rollback() and $admins are defined there).
global $wpdb;
$table = $wpdb->prefix . 'amnesty_signatures';

// ---------------------------------------------------------------- 1. Settings: edition dates save
echo "\n== 1. Ustawienia: zapis dat edycji ==\n";
do_action( 'admin_init' );
begin();
update_option( 'amnesty_edition_start', '2026-09-01' );
update_option( 'amnesty_edition_end', '2027-02-15' );
t( 'zapis poprawnych dat', get_option( 'amnesty_edition_start' ) === '2026-09-01' && get_option( 'amnesty_edition_end' ) === '2027-02-15' );
update_option( 'amnesty_edition_start', '01.09.2026' );
t( 'nieprawidłowy format jest czyszczony', get_option( 'amnesty_edition_start' ) === '' );
rollback();

wp_set_current_user( $admins[0] );
ob_start(); amnesty_render_global_settings_page(); $html = ob_get_clean();
preg_match_all( '#<input type="url" name="([a-z_]+)" value="([^"]*)"#', $html, $m, PREG_SET_ORDER );
$bad = array();
foreach ( $m as $f ) { if ( $f[2] !== '' && ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $f[2] ) ) $bad[] = $f[1]; }
t( 'formularz: brak pól URL blokujących zapis w przeglądarce', empty( $bad ), implode( ',', $bad ) );
t( 'formularz: pola dat edycji w zakładce „Edycja”', strpos( $html, 'name="amnesty_edition_start"' ) && strpos( $html, 'id="tab-edition"' ) );
wp_set_current_user( 0 );

// ---------------------------------------------------------------- 2. Edition counters vs direct SQL
echo "\n== 2. Liczniki w zakresie edycji ==\n";
$range_start = '2026-01-01'; $range_end = '2026-09-30';
begin();
update_option( 'amnesty_edition_start', $range_start );
update_option( 'amnesty_edition_end', $range_end );
$exp_events = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='wydarzenie' AND post_status='publish' AND post_date >= %s AND post_date <= %s", "$range_start 00:00:00", "$range_end 23:59:59" ) );
t( 'licznik wydarzeń = SQL', amnesty_count_edition_events() === $exp_events, "got " . amnesty_count_edition_events() . ", sql $exp_events" );
$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='amnesty_petition' AND post_status='publish' AND post_date >= %s AND post_date <= %s", "$range_start 00:00:00", "$range_end 23:59:59" ) );
$exp_letters = 0;
if ( $ids ) {
    $exp_letters = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE petition_id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ")" );
    foreach ( $ids as $id ) $exp_letters += (int) get_post_meta( $id, '_amnesty_petition_extra_letters', true );
}
t( 'licznik listów = SQL', amnesty_count_edition_letters() === $exp_letters, "got " . amnesty_count_edition_letters() . ", sql $exp_letters, petycji " . count( $ids ) );
t( 'petycje edycji = SQL', count( amnesty_get_edition_petition_ids() ) === count( $ids ) );
update_option( 'amnesty_edition_start', '2030-01-01' ); update_option( 'amnesty_edition_end', '2030-12-31' );
t( 'pusty zakres → 0 (bez liczb zastępczych)', amnesty_count_edition_events() === 0 && amnesty_count_edition_letters() === 0 );
$sc = do_shortcode( '[letters_counter]' );
t( '[letters_counter] renderuje 0 przy pustym zakresie', strpos( $sc, 'data-target="0"' ) !== false );
rollback();

// ---------------------------------------------------------------- 3. Sign all limited to edition
echo "\n== 3. „Podpisz wszystkie” tylko petycje edycji ==\n";
$all_pub = get_posts( array( 'post_type' => 'amnesty_petition', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'ASC' ) );
if ( count( $all_pub ) < 2 ) {
    skip( '„Podpisz wszystkie”', 'potrzebne min. 2 opublikowane petycje, jest ' . count( $all_pub ) );
} else {
    begin();
    // Range covering only the newest petition's day, then sign the oldest one with sign_all
    $newest = end( $all_pub ); $oldest = reset( $all_pub );
    $day = substr( get_post_field( 'post_date', $newest ), 0, 10 );
    update_option( 'amnesty_edition_start', $day ); update_option( 'amnesty_edition_end', $day );
    $edition = array_map( 'intval', amnesty_get_edition_petition_ids() );
    $email = 'test-signall-' . wp_generate_password( 6, false ) . '@example.invalid';
    $_POST = array(
        'action' => 'submit_amnesty_petition', 'amnesty_sign_nonce' => wp_create_nonce( 'amnesty_sign_petition' ),
        'petition_id' => $oldest, 'first_name' => 'Test', 'last_name' => 'Testowy', 'email' => $email,
        'sign_all' => '1', 'letter_type' => 'appeal', 'custom_message' => 'test', 'rodo_consent' => '1', 'amnesty_elapsed' => '5',
    );
    $resp = '';
    add_filter( 'wp_doing_ajax', '__return_true' );
    ob_start();
    try { amnesty_petition_handle_submission(); } catch ( Test_Json $e ) {}
    $resp = ob_get_clean();
    remove_filter( 'wp_doing_ajax', '__return_true' );
    $signed = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT petition_id FROM $table WHERE email = %s", $email ) ) );
    sort( $signed ); $expected = array_values( array_unique( array_merge( array( (int) $oldest ), $edition ) ) ); sort( $expected );
    t( 'podpisano bieżącą + petycje edycji', $signed === $expected, 'podpisane: ' . implode( ',', $signed ) . ' / oczekiwane: ' . implode( ',', $expected ) . ' / wszystkich opublikowanych: ' . count( $all_pub ) );
    t( 'odpowiedź AJAX = sukces', strpos( $resp, '"success":true' ) !== false, substr( $resp, 0, 80 ) );
    t( 'synchronizacja Salesforce zaplanowana w tle (bez czekania na API)', (bool) wp_next_scheduled( 'amnesty_sf_sync_now' ) );
    $_POST = array();
    rollback();
    t( 'rollback usunął testowe podpisy', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE email = %s", $email ) ) );
}

// ---------------------------------------------------------------- 4. Registration, activation, login, reset link
echo "\n== 4. Rejestracja → aktywacja → logowanie ==\n";
begin();
$GLOBALS['test_mails'] = array();
$email = 'test-reg-' . wp_generate_password( 6, false ) . '@example.invalid';
$pass  = 'haslo1234';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'submit_registration' => '1', 'registration_nonce' => wp_create_nonce( 'register_action' ), 'user_email' => $email, 'user_pass' => $pass, 'consent_marathon' => '1', 'maraton_elapsed' => '5' );
$login_page = get_page_by_path( 'zaloguj-sie' );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $login_page ? $login_page->ID : 0 ) );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$redirect = '';
try { handle_user_registration(); } catch ( Test_Redirect $e ) { $redirect = $e->getMessage(); }
$user = get_user_by( 'email', $email );
t( 'konto utworzone', (bool) $user );
t( 'przekierowanie z ?registered=1', strpos( $redirect, 'registered=1' ) !== false, $redirect );
if ( $user ) {
    t( 'login wygenerowany z e-maila', strpos( $user->user_login, 'test-reg-' ) === 0, $user->user_login );
    t( 'konto oczekuje na aktywację', (bool) get_user_meta( $user->ID, 'maraton_account_pending', true ) );
    t( 'zgoda Maratonu z datą', get_user_meta( $user->ID, 'consent_marathon', true ) == 1 && get_user_meta( $user->ID, 'consent_marathon_date', true ) !== '' );
    t( 'zgoda marketingowa opcjonalna (brak = 0, bez daty)', get_user_meta( $user->ID, 'consent_amnesty', true ) == 0 && get_user_meta( $user->ID, 'consent_amnesty_date', true ) === '' );
    t( 'wysłano 1 e-mail aktywacyjny', count( $GLOBALS['test_mails'] ) === 1 && $GLOBALS['test_mails'][0]['to'] === $email );
    $auth = wp_authenticate( $email, $pass );
    t( 'logowanie przed aktywacją zablokowane', is_wp_error( $auth ) && $auth->get_error_code() === 'maraton_pending' );
    preg_match( '#maraton_activate=(\d+)&key=([A-Za-z0-9]+)#', $GLOBALS['test_mails'][0]['message'] ?? '', $mm );
    t( 'link aktywacyjny w treści maila', ! empty( $mm ) );
    // Wrong key
    $_GET = array( 'maraton_activate' => $user->ID, 'key' => 'zly' ); $loc = '';
    try { handle_account_activation(); } catch ( Test_Redirect $e ) { $loc = $e->getMessage(); }
    t( 'zły klucz → activated=0, konto nadal nieaktywne', strpos( $loc, 'activated=0' ) !== false && get_user_meta( $user->ID, 'maraton_account_pending', true ) );
    // Correct key
    $_GET = array( 'maraton_activate' => $mm[1] ?? 0, 'key' => $mm[2] ?? '' ); $loc = '';
    try { handle_account_activation(); } catch ( Test_Redirect $e ) { $loc = $e->getMessage(); }
    t( 'poprawny klucz → activated=1', strpos( $loc, 'activated=1' ) !== false, $loc );
    t( 'po aktywacji logowanie działa', wp_authenticate( $email, $pass ) instanceof WP_User );
    t( 'klucz usunięty po aktywacji', get_user_meta( $user->ID, 'maraton_activation_key', true ) === '' );
}
// Validation: short password, duplicate e-mail, missing consent
foreach ( array(
    array( 'krótkie hasło odrzucone', array( 'user_email' => 'x-' . wp_generate_password( 5, false ) . '@example.invalid', 'user_pass' => 'abc', 'consent_marathon' => '1' ) ),
    array( 'brak wymaganej zgody odrzucony', array( 'user_email' => 'y-' . wp_generate_password( 5, false ) . '@example.invalid', 'user_pass' => 'haslo1234' ) ),
    array( 'zajęty e-mail odrzucony', array( 'user_email' => $email, 'user_pass' => 'haslo1234', 'consent_marathon' => '1' ) ),
) as $case ) {
    global $registration_error; $registration_error = '';
    $_POST = array_merge( array( 'submit_registration' => '1', 'registration_nonce' => wp_create_nonce( 'register_action' ), 'maraton_elapsed' => '5' ), $case[1] );
    $redir = '';
    try { handle_user_registration(); } catch ( Test_Redirect $e ) { $redir = $e->getMessage(); }
    t( $case[0], $redir === '' && $registration_error !== '', $registration_error );
}
$_POST = array(); $_GET = array();
rollback();
t( 'rollback usunął konto testowe', ! get_user_by( 'email', $email ) );
$form = do_shortcode( '[event_login]' );
t( 'formularz logowania: link „Nie pamiętasz hasła?”', strpos( $form, 'action=lostpassword' ) !== false );
$reg = do_shortcode( '[event_registration]' );
t( 'formularz rejestracji: brak pola nazwy użytkownika i powtórzenia e-maila', strpos( $reg, 'name="user_login"' ) === false && strpos( $reg, 'user_email_confirm' ) === false );
t( 'formularz rejestracji: zgoda marketingowa bez required', (bool) preg_match( '#name="consent_amnesty"[^>]*>#', $reg, $cm ) && strpos( $cm[0], 'required' ) === false );

// ---------------------------------------------------------------- 5. Material order meta box
echo "\n== 5. Metabox zamówienia materiałów ==\n";
require_once ABSPATH . 'wp-admin/includes/template.php';
global $wp_meta_boxes;
$order = get_posts( array( 'post_type' => 'material_order', 'post_status' => 'any', 'numberposts' => 1 ) );
do_action( 'add_meta_boxes', 'material_order', $order ? $order[0] : null );
t( 'metabox material_order_details zarejestrowany', isset( $wp_meta_boxes['material_order']['normal']['high']['material_order_details'] ) );

// ---------------------------------------------------------------- 6. Elementor counter widget
echo "\n== 6. Widget licznika (Elementor) ==\n";
if ( class_exists( '\Elementor\Plugin' ) ) {
    $types = \Elementor\Plugin::instance()->widgets_manager->get_widget_types();
    t( 'widget block_counter zarejestrowany', isset( $types['block_counter'] ) );
    t( 'klasa z pluginu (nie z motywu)', isset( $types['block_counter'] ) && strpos( ( new ReflectionClass( $types['block_counter'] ) )->getFileName(), 'amnesty-petitions-mk' ) !== false );
} else {
    t( 'Elementor niezaładowany w CLI', false );
}

// ---------------------------------------------------------------- 7. Plugin log location
echo "\n== 7. Log pluginu ==\n";
// run.php points AMNESTY_PETITIONS_LOG_DIR to the temp dir, so the real _wpeprivate log stays untouched
$log_file = trailingslashit( AMNESTY_PETITIONS_LOG_DIR ) . 'amnesty-debug.log';
$before   = file_exists( $log_file ) ? filesize( $log_file ) : 0;
amnesty_custom_log( 'integration-test entry (can be deleted)' );
clearstatcache();
t( 'wpis trafia do katalogu z AMNESTY_PETITIONS_LOG_DIR', file_exists( $log_file ) && filesize( $log_file ) > $before, $log_file );
t( 'brak logu w katalogu pluginu', ! file_exists( AMNESTY_PETITIONS_PATH . 'amnesty-debug.log' ) );
t( 'domyślny katalog logu to _wpeprivate (gdy stała nie jest ustawiona)', false !== strpos( file_get_contents( AMNESTY_PETITIONS_PATH . 'includes/ajax-salesforce.php' ), "ABSPATH . '_wpeprivate'" ) );
