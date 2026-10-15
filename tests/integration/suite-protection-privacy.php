<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) exit; // Only via run.php (WP-CLI)
// Suites 13-16: bot protection, admin export/delete permissions, GDPR tools, Salesforce consent.
// Loaded by run.php. Everything runs inside rolled-back transactions; Salesforce is simulated.
global $wpdb;
$table = $wpdb->prefix . 'amnesty_signatures';

class Test_Die extends Exception {}
$die_handler = function () { return function ( $msg ) { throw new Test_Die( is_string( $msg ) ? $msg : '' ); }; };

$petition = get_posts( array( 'post_type' => 'amnesty_petition', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
$petition = $petition ? (int) $petition[0] : 0;

// Petition AJAX helper: returns decoded JSON response
$sign = function ( $fields ) use ( $petition ) {
    $_POST = $_REQUEST = array_merge( array(
        'action' => 'submit_amnesty_petition', 'amnesty_sign_nonce' => wp_create_nonce( 'amnesty_sign_petition' ),
        'petition_id' => $petition, 'first_name' => 'Ochrona', 'last_name' => 'Test', 'email' => 'protect-' . wp_generate_password( 6, false ) . '@example.invalid',
        'letter_type' => 'appeal', 'custom_message' => 'test', 'rodo_consent' => '1', 'amnesty_elapsed' => '5',
    ), $fields );
    add_filter( 'wp_doing_ajax', '__return_true' );
    ob_start();
    try { amnesty_petition_handle_submission(); } catch ( Test_Json $e ) {}
    $out = ob_get_clean();
    remove_filter( 'wp_doing_ajax', '__return_true' );
    return array( json_decode( $out, true ), $_POST['email'] );
};
$rows_for = function ( $email ) use ( $wpdb, $table ) { return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE email = %s", $email ) ); };

// ---------------------------------------------------------------- 13. Bot protection
echo "\n== 13. Ochrona formularzy przed botami ==\n";
if ( ! $petition ) {
    skip( 'ochrona petycji', 'brak opublikowanej petycji' );
} else {
    begin();
    list( $r, $email ) = $sign( array( 'amnesty_website' => 'http://spam.example' ) );
    t( 'petycja: pole-pułapka → „sukces” dla bota, nic nie zapisane', ! empty( $r['success'] ) && 0 === $rows_for( $email ) );
    list( $r, $email ) = $sign( array( 'amnesty_elapsed' => '0.4' ) );
    t( 'petycja: wysłanie po < 2 s → nic nie zapisane', ! empty( $r['success'] ) && 0 === $rows_for( $email ) );
    list( $r, $email ) = $sign( array( 'amnesty_elapsed' => '' ) );
    t( 'petycja: brak pomiaru czasu (bot bez JS) → nic nie zapisane', 0 === $rows_for( $email ) );
    list( $r, $email ) = $sign( array( 'rodo_consent' => '' ) );
    t( 'petycja: brak wymaganej zgody → błąd, nic nie zapisane', isset( $r['success'] ) && false === $r['success'] && 0 === $rows_for( $email ), $r['data'] ?? '' );
    list( $r, $email ) = $sign( array() );
    t( 'petycja: poprawny podpis zapisany', ! empty( $r['success'] ) && 1 === $rows_for( $email ) );

    $limit = function () { return array( 'count' => 2, 'window' => MINUTE_IN_SECONDS ); };
    add_filter( 'amnesty_petition_rate_limit', $limit );
    delete_transient( 'amnesty_rl_' . md5( 'petition|' . amnesty_client_ip() ) );
    $sign( array() ); $sign( array() );
    list( $r, $email ) = $sign( array() );
    t( 'petycja: limit na IP (test: 2) → 3. podpis odrzucony z komunikatem', isset( $r['success'] ) && false === $r['success'] && 0 === $rows_for( $email ), $r['data'] ?? '' );
    remove_filter( 'amnesty_petition_rate_limit', $limit );
    $_POST = $_REQUEST = array();
    rollback();
}

// Registration
begin();
$login_page = get_page_by_path( 'zaloguj-sie' );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $login_page ? $login_page->ID : 0 ) );
$register = function ( $fields ) {
    global $registration_error;
    $registration_error = '';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = array_merge( array( 'submit_registration' => '1', 'registration_nonce' => wp_create_nonce( 'register_action' ), 'user_email' => 'reg-' . wp_generate_password( 6, false ) . '@example.invalid', 'user_pass' => 'haslo12345', 'consent_marathon' => '1', 'maraton_elapsed' => '5' ), $fields );
    $redirect = '';
    try { handle_user_registration(); } catch ( Test_Redirect $e ) { $redirect = $e->getMessage(); }
    return array( $redirect, $registration_error, $_POST['user_email'] );
};
list( $redir, $err, $email ) = $register( array( 'maraton_website' => 'x' ) );
t( 'rejestracja: pole-pułapka → ekran sukcesu, konto nieutworzone', false !== strpos( $redir, 'registered=1' ) && ! get_user_by( 'email', $email ) );
list( $redir, $err, $email ) = $register( array( 'maraton_elapsed' => '0.5' ) );
t( 'rejestracja: wysłanie po < 2 s → konto nieutworzone', ! get_user_by( 'email', $email ) );
$reg_limit = function ( $l ) { $l['registration_per_ip'] = array( 'count' => 1, 'window' => MINUTE_IN_SECONDS ); return $l; };
add_filter( 'maraton_form_limits', $reg_limit );
delete_transient( maraton_counter_key( 'register', maraton_client_ip() ) );
$register( array() );
list( $redir, $err, $email ) = $register( array() );
t( 'rejestracja: limit na IP (test: 1) → 2. rejestracja odrzucona', '' === $redir && '' !== $err && ! get_user_by( 'email', $email ), $err );
remove_filter( 'maraton_form_limits', $reg_limit );
$_POST = array();
rollback();

// Login lockout
begin();
$uid = wp_insert_user( array( 'user_login' => 'lockout-test', 'user_email' => 'lockout-test@example.invalid', 'user_pass' => 'Poprawne123', 'role' => 'subscriber' ) );
$login_limit = function ( $l ) { $l['login_fail_per_user'] = array( 'count' => 3, 'window' => MINUTE_IN_SECONDS ); $l['login_fail_per_ip'] = array( 'count' => 100, 'window' => MINUTE_IN_SECONDS ); return $l; };
add_filter( 'maraton_form_limits', $login_limit );
delete_transient( maraton_counter_key( 'login_user', 'lockout-test@example.invalid' ) );
for ( $i = 0; $i < 3; $i++ ) { wp_authenticate( 'lockout-test@example.invalid', 'zle-haslo' ); }
$res = wp_authenticate( 'lockout-test@example.invalid', 'Poprawne123' );
t( 'logowanie: po 3 błędnych hasłach (test) konto zablokowane nawet z poprawnym hasłem', is_wp_error( $res ) && 'maraton_locked' === $res->get_error_code() );
delete_transient( maraton_counter_key( 'login_user', 'lockout-test@example.invalid' ) );
t( 'logowanie: po wygaśnięciu blokady poprawne hasło działa', wp_authenticate( 'lockout-test@example.invalid', 'Poprawne123' ) instanceof WP_User );
remove_filter( 'maraton_form_limits', $login_limit );
rollback();

// ---------------------------------------------------------------- 14. Export / delete permissions
echo "\n== 14. Eksport i usuwanie podpisów w kokpicie ==\n";
begin();
$wpdb->insert( $table, array( 'petition_id' => $petition, 'first_name' => 'A', 'last_name' => 'B', 'email' => 'perm-test@example.invalid', 'custom_message' => '' ) );
$sig_id = (int) $wpdb->insert_id;
add_filter( 'wp_die_handler', $die_handler );
$sub = wp_insert_user( array( 'user_login' => 'perm-sub', 'user_email' => 'perm-sub@example.invalid', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
wp_set_current_user( $sub );
$_REQUEST = array( 'page' => 'amnesty-signatures', 'action' => 'export_csv', 'signature_ids' => array( $sig_id ), '_wpnonce' => wp_create_nonce( 'bulk-signatures' ) );
$blocked = false;
ob_start(); try { amnesty_process_signatures_bulk_actions(); } catch ( Test_Die $e ) { $blocked = true; } $csv = ob_get_clean();
t( 'eksport CSV: subskrybent zablokowany (wcześniej luka)', $blocked && '' === $csv );
wp_set_current_user( $admins[0] );
$_REQUEST = array( 'page' => 'amnesty-signatures', 'action' => 'export_csv', 'signature_ids' => array( $sig_id ) );
$blocked = false;
try { amnesty_process_signatures_bulk_actions(); } catch ( Test_Die $e ) { $blocked = true; }
t( 'eksport CSV: administrator bez nonce zablokowany', $blocked );
$_REQUEST = array( 'page' => 'amnesty-signatures', 'action' => 'delete', 'signature_ids' => array( $sig_id ), '_wpnonce' => wp_create_nonce( 'bulk-signatures' ) );
$loc = '';
try { amnesty_process_signatures_bulk_actions(); } catch ( Test_Redirect $e ) { $loc = $e->getMessage(); }
t( 'usuwanie: administrator z nonce usuwa podpis', 0 === $rows_for( 'perm-test@example.invalid' ) && false !== strpos( $loc, 'deleted=1' ), $loc );
remove_filter( 'wp_die_handler', $die_handler );
$_REQUEST = array();
wp_set_current_user( 0 );
rollback();

// ---------------------------------------------------------------- 15. GDPR tools
echo "\n== 15. RODO: eksport i usuwanie danych osobowych ==\n";
begin();
$gdpr_email = 'gdpr-test@example.invalid';
$wpdb->insert( $table, array( 'petition_id' => $petition, 'first_name' => 'Jan', 'last_name' => 'Rodo', 'email' => $gdpr_email, 'custom_message' => 'treść', 'campaign_consent' => 1, 'salesforce_synced' => 1 ) );
$wpdb->insert( $table, array( 'petition_id' => $petition, 'first_name' => 'Jan', 'last_name' => 'Rodo', 'email' => $gdpr_email, 'custom_message' => '' ) );
$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$erasers   = apply_filters( 'wp_privacy_personal_data_erasers', array() );
t( 'narzędzia WordPress: eksporter i usuwacz podpisów zarejestrowane', isset( $exporters['amnesty-petitions'], $erasers['amnesty-petitions'], $exporters['maraton-organizer-consents'] ) );
$exp = call_user_func( $exporters['amnesty-petitions']['callback'], $gdpr_email, 1 );
t( 'eksport: 2 podpisy z danymi', 2 === count( $exp['data'] ) && $exp['done'] );
$er = call_user_func( $erasers['amnesty-petitions']['callback'], $gdpr_email, 1 );
t( 'usuwanie: podpisy usunięte + komunikat o Salesforce', $er['items_removed'] && 0 === $rows_for( $gdpr_email ) && ! empty( $er['messages'] ) );
$org = wp_insert_user( array( 'user_login' => 'gdpr-org', 'user_email' => 'gdpr-org@example.invalid', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
update_user_meta( $org, 'consent_marathon', 1 ); update_user_meta( $org, 'consent_marathon_date', '2026-10-14 10:00:00' ); update_user_meta( $org, 'consent_amnesty', 0 );
$exp = call_user_func( $exporters['maraton-organizer-consents']['callback'], 'gdpr-org@example.invalid', 1 );
t( 'eksport: zgody organizatora z datą', 1 === count( $exp['data'] ) && 2 === count( $exp['data'][0]['data'] ) && false !== strpos( $exp['data'][0]['data'][0]['value'], '2026-10-14' ) );
rollback();

// ---------------------------------------------------------------- 16. Marketing consent in Salesforce
echo "\n== 16. Zgoda marketingowa w Salesforce (symulacja) ==\n";
$GLOBALS['sf_patches'] = array();
$GLOBALS['sf_patch_fail'] = false;
$sf_mock = function ( $pre, $args, $url ) {
    if ( strpos( $url, 'oauth2' ) === false && strpos( $url, '/services/' ) === false ) return $pre;
    $reply = function ( $body, $code = 200 ) { return array( 'body' => $body, 'response' => array( 'code' => $code ), 'headers' => array(), 'cookies' => array() ); };
    if ( isset( $args['method'] ) && 'PATCH' === $args['method'] ) {
        $GLOBALS['sf_patches'][] = array( 'url' => $url, 'body' => json_decode( $args['body'], true ) );
        return $GLOBALS['sf_patch_fail'] ? $reply( '[{"errorCode":"INVALID_FIELD"}]', 400 ) : $reply( '', 204 );
    }
    if ( strpos( $url, 'oauth2' ) !== false ) return $reply( '{"access_token":"T"}' );
    if ( strpos( $url, '/query/' ) !== false ) return $reply( '{"records":[{"Id":"' . ( strpos( urldecode( $url ), 'consent-yes' ) !== false ? 'C_YES' : ( strpos( urldecode( $url ), 'consent-bad' ) !== false ? 'C_BAD' : 'X1' ) ) . '"}]}' );
    return $reply( '{"id":"X2"}' );
};
add_filter( 'pre_http_request', $sf_mock, 5, 3 );
begin();
update_post_meta( $petition, '_amnesty_petition_topic_name', 'TEST: zgoda' );
$wpdb->query( "UPDATE $table SET salesforce_attempts = " . AMNESTY_SF_MAX_ATTEMPTS . " WHERE salesforce_synced = 0" );
$wpdb->insert( $table, array( 'petition_id' => $petition, 'first_name' => 'Z', 'last_name' => 'T', 'email' => 'consent-yes@example.invalid', 'custom_message' => '', 'campaign_consent' => 1 ) );
$wpdb->insert( $table, array( 'petition_id' => $petition, 'first_name' => 'Z', 'last_name' => 'T', 'email' => 'consent-no@example.invalid', 'custom_message' => '', 'campaign_consent' => 0 ) );
delete_transient( 'amnesty_sf_sync_lock' );
amnesty_sf_process_queue();
$patch = $GLOBALS['sf_patches'];
t( 'zgoda = tak → Contact aktualizowany: ' . amnesty_sf_consent_field() . ' = true', 1 === count( $patch ) && false !== strpos( $patch[0]['url'], '/Contact/C_YES' ) && array( amnesty_sf_consent_field() => true ) === $patch[0]['body'], wp_json_encode( $patch ) );
t( 'zgoda = nie → brak aktualizacji pola (nie odbieramy wcześniejszej zgody)', 1 === count( $patch ) );
$GLOBALS['sf_patch_fail'] = true;
$wpdb->insert( $table, array( 'petition_id' => $petition, 'first_name' => 'Z', 'last_name' => 'T', 'email' => 'consent-bad@example.invalid', 'custom_message' => '', 'campaign_consent' => 1 ) );
$bad_id = (int) $wpdb->insert_id;
delete_transient( 'amnesty_sf_sync_lock' );
amnesty_sf_process_queue();
t( 'błędna nazwa pola w ustawieniach nie blokuje synchronizacji podpisu', 1 === (int) $wpdb->get_var( "SELECT salesforce_synced FROM $table WHERE id = $bad_id" ) );
update_option( 'amnesty_sf_consent_field', '' );
$GLOBALS['sf_patches'] = array(); $GLOBALS['sf_patch_fail'] = false;
$wpdb->insert( $table, array( 'petition_id' => $petition, 'first_name' => 'Z', 'last_name' => 'T', 'email' => 'consent-off@example.invalid', 'custom_message' => '', 'campaign_consent' => 1 ) );
delete_transient( 'amnesty_sf_sync_lock' );
amnesty_sf_process_queue();
t( 'puste pole w ustawieniach → zgoda nie jest wysyłana', 0 === count( $GLOBALS['sf_patches'] ) );
t( 'nazwa pola: tylko poprawne nazwy API', '' === amnesty_sanitize_sf_field( "Field'; DROP" ) && 'Newsletter_Subscribe__c' === amnesty_sanitize_sf_field( 'Newsletter_Subscribe__c' ) );
t( 'log: adres e-mail maskowany', 'j***@example.org' === amnesty_mask_email( 'jan@example.org' ) );
rollback();
remove_filter( 'pre_http_request', $sf_mock, 5 );
