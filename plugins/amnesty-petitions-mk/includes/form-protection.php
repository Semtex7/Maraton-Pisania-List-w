<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/* ====================================================================
 * PETITION FORM PROTECTION (no third-party service)
 * 1. Honeypot field "amnesty_website" — hidden from people, bots fill it in.
 * 2. Time check "amnesty_elapsed" — seconds between page load and submit, set by
 *    amnesty-frontend.js; submits faster than AMNESTY_FORM_MIN_SECONDS are rejected.
 * 3. Rate limit per IP — generous on purpose: at Maraton events (schools, offices)
 *    many people sign from one IP address. Tune with the "amnesty_petition_rate_limit" filter.
 * ==================================================================== */

define( 'AMNESTY_FORM_MIN_SECONDS', 2 );

function amnesty_client_ip() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
}

/**
 * Counts an attempt in a fixed window and tells whether the limit is exceeded.
 * Returns true when this attempt is over the limit.
 */
function amnesty_rate_limit_exceeded( $bucket, $limit, $window ) {
    $key   = 'amnesty_rl_' . md5( $bucket . '|' . amnesty_client_ip() );
    $count = (int) get_transient( $key );

    if ( $count >= $limit ) {
        return true;
    }

    set_transient( $key, $count + 1, $window );
    return false;
}

// Hidden fields printed inside the petition form (shortcodes.php)
function amnesty_form_protection_fields() {
    ?>
    <div class="amnesty-hp" aria-hidden="true" style="position:absolute !important;left:-10000px !important;width:1px;height:1px;overflow:hidden;">
        <label>Nie wypełniaj tego pola <input type="text" name="amnesty_website" value="" tabindex="-1" autocomplete="off"></label>
    </div>
    <input type="hidden" name="amnesty_elapsed" value="">
    <?php
}

/**
 * Checks a petition submission. Returns '' when OK, 'bot' for silent rejection
 * (honeypot / too fast) or a message for the visitor (rate limit).
 */
function amnesty_check_petition_submission() {
    if ( ! empty( $_POST['amnesty_website'] ) ) {
        return 'bot';
    }

    $elapsed = isset( $_POST['amnesty_elapsed'] ) ? (float) $_POST['amnesty_elapsed'] : 0;
    if ( $elapsed < AMNESTY_FORM_MIN_SECONDS ) {
        return 'bot';
    }

    $limit = apply_filters( 'amnesty_petition_rate_limit', array( 'count' => 60, 'window' => 10 * MINUTE_IN_SECONDS ) );
    if ( amnesty_rate_limit_exceeded( 'petition', (int) $limit['count'], (int) $limit['window'] ) ) {
        return __( 'Zbyt wiele podpisów z tego adresu w krótkim czasie. Spróbuj ponownie za kilka minut.', 'amnesty-petitions' );
    }

    return '';
}
