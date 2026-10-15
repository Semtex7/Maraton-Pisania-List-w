<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) exit; // Only via run.php (WP-CLI)
// Suite 12: background Salesforce sync (includes/salesforce-sync.php) and schema version. Loaded by run.php.
// Salesforce is simulated; all rows are created inside a rolled-back transaction.
global $wpdb;
$table = $wpdb->prefix . 'amnesty_signatures';

echo "\n== 12. Synchronizacja Salesforce w tle ==\n";

t( 'wersja schematu tabeli = ' . AMNESTY_PETITIONS_DB_VERSION, (int) get_option( 'amnesty_petitions_db_version' ) === AMNESTY_PETITIONS_DB_VERSION );
t( 'kolumna salesforce_attempts istnieje', in_array( 'salesforce_attempts', $wpdb->get_col( "SHOW COLUMNS FROM $table" ), true ) );
t( 'cykliczna synchronizacja zaplanowana (co 5 min)', (bool) wp_next_scheduled( 'amnesty_sf_sync_queue' ) && wp_get_schedule( 'amnesty_sf_sync_queue' ) === 'amnesty_five_minutes' );

$petitions = get_posts( array( 'post_type' => 'amnesty_petition', 'post_status' => 'publish', 'numberposts' => 2, 'fields' => 'ids' ) );
if ( count( $petitions ) < 2 ) {
    skip( 'kolejka Salesforce', 'potrzebne min. 2 opublikowane petycje' );
    return;
}

// Simulated Salesforce: $GLOBALS['sf_mode'] = ok | fail | duplicate
$GLOBALS['sf_mode']  = 'ok';
$GLOBALS['sf_calls'] = 0;
$sf_mock = function ( $pre, $args, $url ) {
    if ( strpos( $url, 'oauth2' ) === false && strpos( $url, '/services/' ) === false ) return $pre;
    $GLOBALS['sf_calls']++;
    $reply = function ( $body ) { return array( 'body' => $body, 'response' => array( 'code' => 200 ), 'headers' => array(), 'cookies' => array() ); };
    if ( strpos( $url, 'oauth2' ) !== false ) return $reply( '{"access_token":"T"}' );
    if ( strpos( $url, '/query/' ) !== false ) return $reply( '{"records":[{"Id":"X1"}]}' );
    if ( 'fail' === $GLOBALS['sf_mode'] ) return $reply( '[{"errorCode":"UNKNOWN_EXCEPTION","message":"test"}]' );
    if ( 'duplicate' === $GLOBALS['sf_mode'] && strpos( $url, 'TopicAssignment' ) !== false ) return $reply( '[{"errorCode":"DUPLICATE_VALUE","message":"test"}]' );
    return $reply( '{"id":"X2"}' );
};
add_filter( 'pre_http_request', $sf_mock, 5, 3 );

begin();
list( $with_topic, $without_topic ) = $petitions;
update_post_meta( $with_topic, '_amnesty_petition_topic_name', 'TEST: kolejka' );
delete_post_meta( $without_topic, '_amnesty_petition_topic_name' );
// Older pending rows (e.g. on staging) are part of the same queue; they are synced in the simulation and rolled back
$wpdb->query( "UPDATE $table SET salesforce_attempts = " . AMNESTY_SF_MAX_ATTEMPTS . " WHERE salesforce_synced = 0" );

$insert = function ( $petition_id, $tag ) use ( $wpdb, $table ) {
    $wpdb->insert( $table, array( 'petition_id' => $petition_id, 'first_name' => 'Kolejka', 'last_name' => 'Test', 'email' => "queue-$tag@example.invalid", 'custom_message' => '' ) );
    return (int) $wpdb->insert_id;
};
$row  = function ( $id ) use ( $wpdb, $table ) { return $wpdb->get_row( $wpdb->prepare( "SELECT salesforce_synced, salesforce_attempts FROM $table WHERE id = %d", $id ) ); };
$run  = function () { delete_transient( 'amnesty_sf_sync_lock' ); return amnesty_sf_process_queue(); };

// Success
$ok_id   = $insert( $with_topic, 'ok' );
$skip_id = $insert( $without_topic, 'no-topic' );
$r = $run();
t( 'podpis zsynchronizowany w tle', 1 === (int) $row( $ok_id )->salesforce_synced, wp_json_encode( $r ) );
t( 'petycja bez Topiku pomijana (nie blokuje kolejki)', 0 === (int) $row( $skip_id )->salesforce_synced && 0 === (int) $row( $skip_id )->salesforce_attempts );
t( 'lock zwolniony po przebiegu', false === get_transient( 'amnesty_sf_sync_lock' ) );

// One token and one Contact lookup per batch: 3 signatures of the same e-mail
$GLOBALS['sf_calls'] = 0;
for ( $i = 0; $i < 3; $i++ ) { $wpdb->insert( $table, array( 'petition_id' => $with_topic, 'first_name' => 'K', 'last_name' => 'T', 'email' => 'queue-same@example.invalid', 'custom_message' => '' ) ); }
$r = $run();
t( 'paczka: 1 token + 1 zapytanie o Contact + przypisania', $r['synced'] === 3 && $GLOBALS['sf_calls'] <= 5, 'wywołań API: ' . $GLOBALS['sf_calls'] );

// Failure and retry limit
$GLOBALS['sf_mode'] = 'fail';
$fail_id = $insert( $with_topic, 'fail' );
$run();
t( 'nieudana synchronizacja → próba +1, nadal w kolejce', 0 === (int) $row( $fail_id )->salesforce_synced && 1 === (int) $row( $fail_id )->salesforce_attempts );
for ( $i = 0; $i < AMNESTY_SF_MAX_ATTEMPTS + 2; $i++ ) { $run(); }
t( 'po ' . AMNESTY_SF_MAX_ATTEMPTS . ' próbach wiersz nie jest już ponawiany', AMNESTY_SF_MAX_ATTEMPTS === (int) $row( $fail_id )->salesforce_attempts );

// Duplicate TopicAssignment = already assigned = success
$GLOBALS['sf_mode'] = 'duplicate';
$dup_id = $insert( $with_topic, 'dup' );
$run();
t( 'DUPLICATE_VALUE przy przypisaniu traktowane jako sukces', 1 === (int) $row( $dup_id )->salesforce_synced );

// Lock prevents parallel runs
set_transient( 'amnesty_sf_sync_lock', 1, 60 );
$r = amnesty_sf_process_queue();
t( 'równoległy przebieg zablokowany', 'locked' === $r['skipped'] );
delete_transient( 'amnesty_sf_sync_lock' );

// Status column in Podpisy
if ( ! class_exists( 'Amnesty_Signatures_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
$GLOBALS['hook_suffix'] = 'amnesty_petition_page_amnesty-signatures';
set_current_screen( 'amnesty_petition_page_amnesty-signatures' );
$list   = new Amnesty_Signatures_List_Table();
$status = function ( $id ) use ( $wpdb, $table, $list ) {
    $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
    $m = new ReflectionMethod( $list, 'column_default' );
    $m->setAccessible( true );
    return wp_strip_all_tags( $m->invoke( $list, $item, 'salesforce_synced' ) );
};
t( 'kolumna statusu: ✅ / ❌ / — ', $status( $ok_id ) === '✅' && $status( $fail_id ) === '❌' && $status( $skip_id ) === '—', $status( $ok_id ) . ' ' . $status( $fail_id ) . ' ' . $status( $skip_id ) );

rollback();
remove_filter( 'pre_http_request', $sf_mock, 5 );
t( 'rollback usunął wiersze testowe kolejki', 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE email LIKE 'queue-%@example.invalid'" ) );
