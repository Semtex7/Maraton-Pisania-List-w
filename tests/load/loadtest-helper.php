<?php
/**
 * Plugin Name: Maraton load-test helper (LOCAL ONLY)
 * Description: Simulates Salesforce (300 ms per call) and blocks e-mails while active. Copy to wp-content/plugins/maraton-loadtest-helper/ on a LOCAL site, activate for the test, then deactivate and delete. Never on staging/production.
 *
 * Kept in tests/ (not in plugins/) so it can never be activated by accident on a deployed site.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
    if ( strpos( $url, 'oauth2' ) === false && strpos( $url, '/services/' ) === false ) {
        return $pre;
    }
    usleep( 300000 );
    if ( strpos( $url, 'oauth2' ) !== false ) {
        $body = '{"access_token":"T"}';
    } elseif ( strpos( $url, '/query/' ) !== false ) {
        $body = '{"records":[{"Id":"X1"}]}';
    } else {
        $body = '{"id":"X2"}';
    }
    return array( 'body' => $body, 'response' => array( 'code' => 200 ), 'headers' => array(), 'cookies' => array() );
}, 1, 3 );

add_filter( 'pre_wp_mail', '__return_true' );

// All load-test requests come from one IP: lift the per-IP petition limit (form-protection.php)
add_filter( 'amnesty_petition_rate_limit', function () {
    return array( 'count' => 1000000, 'window' => MINUTE_IN_SECONDS );
} );

// DB queries and generation time as an HTML comment at the end of each page
add_action( 'shutdown', function () {
    if ( wp_doing_ajax() || wp_doing_cron() || is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
        return;
    }
    global $wpdb;
    echo "\n<!-- loadtest: queries=" . (int) $wpdb->num_queries . ' time=' . esc_html( timer_stop( 0, 3 ) ) . ' -->';
}, 9999 );
