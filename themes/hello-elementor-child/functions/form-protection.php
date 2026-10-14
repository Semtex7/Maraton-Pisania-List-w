<?php
// FORM PROTECTION: registration and login (no third-party service)
// - registration: honeypot field, minimum time on page (set by JS), limit per IP
// - login: lockout after repeated failures per IP and per account; applies to [event_login],
//   wp-login.php and XML-RPC (authenticate filter)
// Limits can be tuned with the "maraton_form_limits" filter.

function maraton_form_limits() {
    return apply_filters('maraton_form_limits', array(
        'registration_per_ip' => array('count' => 5, 'window' => HOUR_IN_SECONDS),
        'login_fail_per_ip'   => array('count' => 10, 'window' => 15 * MINUTE_IN_SECONDS),
        'login_fail_per_user' => array('count' => 10, 'window' => 15 * MINUTE_IN_SECONDS),
        'min_seconds'         => 2,
    ));
}

function maraton_client_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function maraton_counter_key( $bucket, $id ) {
    return 'maraton_rl_' . md5($bucket . '|' . strtolower($id));
}

// Hidden fields for the registration form + script that stores the time spent on the page
function maraton_form_protection_fields() {
    ?>
    <div class="maraton-hp" aria-hidden="true" style="position:absolute !important;left:-10000px !important;width:1px;height:1px;overflow:hidden;">
        <label>Nie wypełniaj tego pola <input type="text" name="maraton_website" value="" tabindex="-1" autocomplete="off"></label>
    </div>
    <input type="hidden" name="maraton_elapsed" value="">
    <script>
        (function (field) {
            field.form.addEventListener('submit', function () { field.value = (performance.now() / 1000).toFixed(1); });
        })(document.currentScript.previousElementSibling);
    </script>
    <?php
}

/**
 * Registration check. Returns '' when OK, 'bot' (silent rejection) or a message for the visitor.
 */
function maraton_check_registration_submission() {
    $limits = maraton_form_limits();

    if ( ! empty($_POST['maraton_website']) ) {
        return 'bot';
    }

    $elapsed = isset($_POST['maraton_elapsed']) ? (float) $_POST['maraton_elapsed'] : 0;
    if ( $elapsed < $limits['min_seconds'] ) {
        return 'bot';
    }

    $key   = maraton_counter_key('register', maraton_client_ip());
    $count = (int) get_transient($key);
    if ( $count >= $limits['registration_per_ip']['count'] ) {
        return 'Zbyt wiele rejestracji z tego adresu. Spróbuj ponownie później.';
    }
    set_transient($key, $count + 1, $limits['registration_per_ip']['window']);

    return '';
}

// Login lockout: refuse when the IP or the account is locked. Runs last (100) because the core
// authenticate callbacks (priority 20) replace an earlier WP_Error with their own result.
add_filter('authenticate', 'maraton_login_lockout_check', 100, 2);

function maraton_login_lockout_check( $user, $username ) {
    if ( empty($username) ) {
        return $user;
    }

    $limits = maraton_form_limits();
    $by_ip   = (int) get_transient(maraton_counter_key('login_ip', maraton_client_ip()));
    $by_user = (int) get_transient(maraton_counter_key('login_user', $username));

    if ( $by_ip >= $limits['login_fail_per_ip']['count'] || $by_user >= $limits['login_fail_per_user']['count'] ) {
        return new WP_Error('maraton_locked', 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za 15 minut albo zresetuj hasło.');
    }

    return $user;
}

add_action('wp_login_failed', 'maraton_login_failed_count', 10, 2);

function maraton_login_failed_count( $username, $error = null ) {
    // A lockout or a not-yet-activated account is not a password guess
    if ( $error instanceof WP_Error && in_array($error->get_error_code(), array('maraton_locked', 'maraton_pending'), true) ) {
        return;
    }

    $limits = maraton_form_limits();
    foreach ( array(
        maraton_counter_key('login_ip', maraton_client_ip()) => $limits['login_fail_per_ip']['window'],
        maraton_counter_key('login_user', $username)        => $limits['login_fail_per_user']['window'],
    ) as $key => $window ) {
        set_transient($key, (int) get_transient($key) + 1, $window);
    }
}

// Successful login clears the account counter
add_action('wp_login', 'maraton_login_success_reset', 10, 1);

function maraton_login_success_reset( $user_login ) {
    delete_transient(maraton_counter_key('login_user', $user_login));
}
