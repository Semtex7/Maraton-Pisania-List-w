<?php
/**
 * Integration tests for the Maraton theme and the amnesty-petitions-mk plugin.
 *
 * Run (from the WordPress root, e.g. Local "Open site shell" or WP Engine SSH gateway):
 *   wp eval-file wp-content/tests/integration/run.php
 *
 * Safety: every database write happens inside a transaction that is rolled back; outgoing HTTP
 * (Salesforce, Nominatim) and e-mails are blocked or simulated; the plugin log is redirected to the
 * system temp directory. Exit code 1 when any test fails. See tests/README.md.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    exit; // Never run over HTTP
}

// Plugin log goes to the temp dir during tests (read by amnesty_custom_log() on every call)
if ( ! defined( 'AMNESTY_PETITIONS_LOG_DIR' ) ) {
    define( 'AMNESTY_PETITIONS_LOG_DIR', sys_get_temp_dir() );
}

global $wpdb;

$GLOBALS['results'] = array();
function t( $name, $ok, $detail = '' ) {
    $GLOBALS['results'][] = array( $name, (bool) $ok, $detail );
    echo ( $ok ? "PASS " : "FAIL " ) . $name . ( $detail !== '' ? "  [$detail]" : '' ) . "\n";
}
function skip( $name, $reason ) {
    echo "SKIP $name  [$reason]\n";
}
class Test_Redirect extends Exception {}
class Test_Json extends Exception {}

// Block external calls and e-mails (a suite can add its own simulated responses with a higher priority)
$GLOBALS['test_http'] = array();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) { if ( false !== $pre ) return $pre; $GLOBALS['test_http'][] = $url; return new WP_Error( 'blocked', 'blocked in tests' ); }, 10, 3 );
$GLOBALS['test_mails'] = array();
add_filter( 'pre_wp_mail', function ( $r, $atts ) { $GLOBALS['test_mails'][] = $atts; return true; }, 10, 2 );
// Turn redirects and JSON responses into exceptions instead of exit
add_filter( 'wp_redirect', function ( $loc ) { throw new Test_Redirect( $loc ); }, 9999 );
add_filter( 'wp_die_ajax_handler', function () { return function ( $msg ) { throw new Test_Json( is_string( $msg ) ? $msg : '' ); }; } );

function begin() { global $wpdb; $wpdb->query( 'START TRANSACTION' ); wp_cache_flush(); }
function rollback() { global $wpdb; $wpdb->query( 'ROLLBACK' ); wp_cache_flush(); }

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );

require __DIR__ . '/suite-core.php';
require __DIR__ . '/suite-features.php';
require __DIR__ . '/suite-salesforce-queue.php';
require __DIR__ . '/suite-protection-privacy.php';

echo "\nHTTP do serwisów zewnętrznych zablokowane: " . count( $GLOBALS['test_http'] ) . " prób\n";
$results = $GLOBALS['results'];
$fails   = array_filter( $results, function ( $r ) { return ! $r[1]; } );
echo "\nRAZEM: " . ( count( $results ) - count( $fails ) ) . " / " . count( $results ) . " OK\n";

if ( $fails ) {
    WP_CLI::halt( 1 );
}
