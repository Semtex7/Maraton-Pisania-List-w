<?php 

// Global form errors
global $registration_error;
$registration_error = '';
global $login_error;
$login_error = '';

// ====================================================================
// 1. SHORTCODE: LOGIN FORM
// ====================================================================

add_shortcode('event_login', 'render_login_shortcode');

function render_login_shortcode() {
    global $login_error;

    if ( is_user_logged_in() ) {
        return '<script>window.location.href="' . esc_url(home_url('/panel/')) . '";</script>';
    }

    $login_value = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
    $remember_value = ! empty( $_POST['rememberme'] );

    $activation_status = isset( $_GET['activated'] ) ? sanitize_key( wp_unslash( $_GET['activated'] ) ) : '';

    ob_start();

    ?>
    <div class="event-auth-panel event-login-panel">
        <h3>Logowanie</h3>
        <?php if ( '1' === $activation_status ) : ?>
            <div class="event-login-notice" role="status">Konto zostało aktywowane. Możesz się zalogować.</div>
        <?php elseif ( '0' === $activation_status ) : ?>
            <div class="event-login-error" role="alert">Link aktywacyjny jest nieprawidłowy lub został już zastąpiony nowym. Zaloguj się, aby otrzymać nowy link.</div>
        <?php endif; ?>
        <?php if ( ! empty( $login_error ) ) : ?>
            <div class="event-login-error" role="alert"><strong>Logowanie nieudane:</strong> <?php echo esc_html( $login_error ); ?></div>
        <?php endif; ?>

    <form name="event-login-form" id="event-login-form" class="event-login-form" action="<?php echo esc_url( get_permalink() ); ?>" method="post">
        <?php wp_nonce_field( 'event_login_action', 'event_login_nonce' ); ?>

        <p class="login-username">
            <label for="user_login">Adres e-mail</label>
            <input type="text" name="log" id="user_login" autocomplete="username" value="<?php echo esc_attr( $login_value ); ?>" required>
        </p>

        <p class="login-password">
            <label for="user_pass">Hasło</label>
            <input type="password" name="pwd" id="user_pass" autocomplete="current-password" required>
        </p>

        <p class="login-remember">
            <label for="rememberme">
                <input name="rememberme" type="checkbox" id="rememberme" value="forever" <?php checked( $remember_value ); ?>>
                Zapamiętaj mnie
            </label>
        </p>

        <p class="login-submit">
            <button type="submit" name="submit_login" class="amnesty-card-button">Zaloguj się</button>
        </p>

        <p class="login-lost-password">
            <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Nie pamiętasz hasła?</a>
        </p>
    </form>
    </div>
    <?php

    return ob_get_clean();
}

// ====================================================================
// 2. HANDLE USER LOGIN
// ====================================================================

add_action( 'template_redirect', 'handle_user_login', 1 );

function handle_user_login() {
    global $login_error;

    if ( is_user_logged_in() || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['submit_login'] ) ) {
        return;
    }

    if ( ! isset( $_POST['event_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['event_login_nonce'] ) ), 'event_login_action' ) ) {
        $login_error = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
        return;
    }

    $credentials = array(
        'user_login'    => isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '',
        'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '',
        'remember'      => ! empty( $_POST['rememberme'] ),
    );

    $user = wp_signon( $credentials, is_ssl() );

    if ( is_wp_error( $user ) ) {
        if ( 'maraton_locked' === $user->get_error_code() ) {
            $login_error = $user->get_error_message();
            return;
        }

        if ( 'maraton_pending' === $user->get_error_code() ) {
            // Correct password but account not activated: send a fresh activation link
            $pending = $user->get_error_data();
            maraton_send_activation_email( (int) $pending['user_id'], get_permalink( get_queried_object_id() ) );
            $login_error = 'Konto nie zostało jeszcze aktywowane. Wysłaliśmy nowy link aktywacyjny na Twój adres e-mail.';
            return;
        }

        $login_error = 'Nie udało się zalogować. Sprawdź dane i spróbuj ponownie.';
        return;
    }

    wp_safe_redirect( home_url( '/panel/' ) );
    exit;
}

// ====================================================================
// 3. HANDLE USER REGISTRATION
// ====================================================================

add_action('template_redirect', 'handle_user_registration');

function handle_user_registration() {
    global $registration_error;

    if ( is_user_logged_in() ) {
        return;
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_registration']) ) {

        // Security check
        if ( ! isset($_POST['registration_nonce']) || ! wp_verify_nonce($_POST['registration_nonce'], 'register_action') ) {
            $registration_error = 'Błąd bezpieczeństwa formularza. Odśwież stronę i spróbuj ponownie.';
            return;
        }

        // Bot protection (functions/form-protection.php); bots see the normal success screen
        $protection = maraton_check_registration_submission();
        if ( 'bot' === $protection ) {
            wp_safe_redirect( add_query_arg('registered', '1', get_permalink( get_queried_object_id() )) );
            exit;
        } elseif ( '' !== $protection ) {
            $registration_error = $protection;
            return;
        }

        $email    = isset($_POST['user_email']) ? sanitize_email( wp_unslash($_POST['user_email']) ) : '';
        $password = isset($_POST['user_pass']) ? (string) wp_unslash($_POST['user_pass']) : '';

        // Consents: marathon consent is required, Amnesty marketing consent is optional (GDPR art. 7(4))
        $consent_marathon = isset($_POST['consent_marathon']) ? 1 : 0;
        $consent_amnesty  = isset($_POST['consent_amnesty']) ? 1 : 0;

        // Validation
        if ( empty($email) || '' === $password ) {
            $registration_error = 'Wypełnij wszystkie wymagane pola.';
        } elseif ( ! is_email($email) ) {
            $registration_error = 'Podaj poprawny adres e-mail.';
        } elseif ( email_exists($email) ) {
            $registration_error = 'Ten adres e-mail jest już zajęty. Przejdź do logowania.';
        } elseif ( strlen($password) < 8 ) {
            $registration_error = 'Hasło musi mieć minimum 8 znaków.';
        } elseif ( ! $consent_marathon ) {
            $registration_error = 'Zaakceptuj wymaganą zgodę na przetwarzanie danych, aby się zarejestrować.';
        } else {
            // Username is not shown to users (login is by e-mail), so it is generated from the address
            $user_id = wp_create_user( maraton_generate_username($email), $password, $email );

            if ( ! is_wp_error($user_id) ) {
                $now = current_time('mysql');

                // Save consents with the time they were given (accountability)
                update_user_meta($user_id, 'consent_marathon', 1);
                update_user_meta($user_id, 'consent_marathon_date', $now);
                update_user_meta($user_id, 'consent_amnesty', $consent_amnesty);
                if ( $consent_amnesty ) {
                    update_user_meta($user_id, 'consent_amnesty_date', $now);
                }

                // Account stays inactive until the e-mail link is clicked
                $page_url = get_permalink( get_queried_object_id() );
                update_user_meta($user_id, 'maraton_account_pending', 1);
                maraton_send_activation_email($user_id, $page_url);

                // PRG: prevents duplicate registration on refresh
                wp_safe_redirect( add_query_arg('registered', '1', $page_url) );
                exit;
            } else {
                $registration_error = $user_id->get_error_message();
            }
        }
    }
}

// Builds a unique user_login from the local part of the e-mail address
function maraton_generate_username( $email ) {
    $base = sanitize_user( current( explode('@', $email) ), true );
    if ( '' === $base ) {
        $base = 'organizator';
    }

    $username = $base;
    $suffix   = 2;
    while ( username_exists($username) ) {
        $username = $base . $suffix;
        $suffix++;
    }

    return $username;
}

// ====================================================================
// 3a. ACCOUNT ACTIVATION (e-mail link)
// ====================================================================

// Generates a new activation key (only its hash is stored) and e-mails the link
function maraton_send_activation_email( $user_id, $page_url ) {
    $user = get_userdata($user_id);
    if ( ! $user ) {
        return false;
    }

    $key = wp_generate_password(32, false);
    update_user_meta($user_id, 'maraton_activation_key', hash('sha256', $key));

    $link = add_query_arg( array(
        'maraton_activate' => $user_id,
        'key'              => $key,
    ), $page_url ? $page_url : home_url('/') );

    $subject = 'Potwierdź rejestrację - Maraton Pisania Listów';
    $message  = "Cześć,\n\n";
    $message .= "Dziękujemy za rejestrację w systemie Maratonu Pisania Listów Amnesty International.\n";
    $message .= "Aby aktywować konto, kliknij w link:\n\n";
    $message .= $link . "\n\n";
    $message .= "Po aktywacji zaloguj się, aby dodać swoje wydarzenie i zamówić materiały.\n";
    $message .= "Jeśli konto nie zostało założone przez Ciebie, zignoruj tę wiadomość.\n\n";
    $message .= "Pozdrawiamy,\nZespół Amnesty International";

    return wp_mail($user->user_email, $subject, $message);
}

add_action('template_redirect', 'handle_account_activation', 0);

function handle_account_activation() {
    if ( ! isset($_GET['maraton_activate'], $_GET['key']) ) {
        return;
    }

    $user_id = absint($_GET['maraton_activate']);
    $key     = sanitize_text_field( wp_unslash($_GET['key']) );
    $stored  = (string) get_user_meta($user_id, 'maraton_activation_key', true);
    $pending = get_user_meta($user_id, 'maraton_account_pending', true);

    $redirect = remove_query_arg( array('maraton_activate', 'key') );

    if ( $pending && '' !== $stored && hash_equals($stored, hash('sha256', $key)) ) {
        delete_user_meta($user_id, 'maraton_account_pending');
        delete_user_meta($user_id, 'maraton_activation_key');
        $status = '1';
    } elseif ( ! $pending && get_userdata($user_id) ) {
        $status = '1'; // Already active (link clicked twice)
    } else {
        $status = '0';
    }

    wp_safe_redirect( add_query_arg('activated', $status, $redirect) );
    exit;
}

// Blocks login (custom form and wp-login.php) for accounts that are not activated yet
add_filter('authenticate', 'maraton_block_pending_accounts', 30);

function maraton_block_pending_accounts( $user ) {
    if ( $user instanceof WP_User && get_user_meta($user->ID, 'maraton_account_pending', true) ) {
        return new WP_Error(
            'maraton_pending',
            'Konto nie zostało jeszcze aktywowane. Kliknij link z wiadomości e-mail wysłanej po rejestracji.',
            array('user_id' => $user->ID)
        );
    }

    return $user;
}

// ====================================================================
// 4. SHORTCODE: REGISTRATION FORM
// ====================================================================

add_shortcode('event_registration', 'render_registration_shortcode');

function render_registration_shortcode() {
    global $registration_error;

    // Check if user is logged in
    if ( is_user_logged_in() ) {
        return '<div>Jesteś już zalogowany! <a href="' . esc_url(home_url('/panel/')) . '">Przejdź do panelu</a></div>';
    }

    // Success screen after registration (PRG redirect with ?registered=1)
    if ( isset( $_GET['registered'] ) && '1' === $_GET['registered'] ) {
        return '<div class="event-auth-panel event-registration-panel"><h3>Rejestracja</h3>'
            . '<div class="register-success" role="status">Dziękujemy! Wysłaliśmy wiadomość z linkiem aktywacyjnym. Kliknij w link, aby aktywować konto, a następnie zaloguj się.</div></div>';
    }

    $val_email = isset( $_POST['user_email'] ) ? esc_attr( wp_unslash( $_POST['user_email'] ) ) : '';
    $val_consent_amnesty = ! empty( $_POST['consent_amnesty'] );

    ob_start();

    ?>
    <div class="event-auth-panel event-registration-panel">
        <h3>Rejestracja</h3>
        <?php if ( ! empty($registration_error) ) : ?>
            <div class="register-error" style="color: #dc0032; margin-bottom: 15px;"><strong>Błąd rejestracji:</strong> <?php echo esc_html( $registration_error ); ?></div>
        <?php endif; ?>

    <form method="post" action="" class="new-event-form">
        <?php wp_nonce_field('register_action', 'registration_nonce'); ?>
        <?php maraton_form_protection_fields(); ?>

        <p class="login-email">
            <label for="reg_user_email">Adres e-mail <span style="color:red;">*</span></label>
            <input type="email" name="user_email" id="reg_user_email" autocomplete="email" value="<?php echo $val_email; ?>" required>
        </p>

        <p class="login-password">
            <label for="reg_user_pass">Hasło <span style="color:red;">*</span></label>
            <input type="password" name="user_pass" id="reg_user_pass" autocomplete="new-password" minlength="8" required>
            <span style="font-size: 0.85em; color: #555;">Minimum 8 znaków.</span>
            <label style="display: flex; gap: 8px; align-items: center; cursor: pointer; font-size: 0.9em;">
                <input type="checkbox" onchange="document.getElementById('reg_user_pass').type = this.checked ? 'text' : 'password';">
                Pokaż hasło
            </label>
        </p>

        <div class="registration-consents" style="margin: 20px 0; font-size: 0.9em; line-height: 1.4;">
            <p>
                <label style="display: flex; gap: 8px; align-items: flex-start; cursor: pointer;">
                    <input type="checkbox" name="consent_marathon" value="1" required style="margin-top: 4px;">
                    <span>Wyrażam zgodę na przetwarzanie moich danych osobowych w celu rejestracji i organizacji Maratonu Pisania Listów. <span style="color:red;">*</span></span>
                </label>
            </p>
            <p>
                <label style="display: flex; gap: 8px; align-items: flex-start; cursor: pointer;">
                    <input type="checkbox" name="consent_amnesty" value="1" <?php checked( $val_consent_amnesty ); ?> style="margin-top: 4px;">
                    <span>Chcę otrzymywać informacje na temat innych akcji, kampanii i sposobów wspierania Amnesty International.</span>
                </label>
            </p>
        </div>
        
        <p class="login-submit">
            <button type="submit" name="submit_registration" class="amnesty-card-button">Zarejestruj się</button>
        </p>
    </form>
    </div>
    <?php
    
    return ob_get_clean();
}

// ====================================================================
// 5. SHORTCODE: LOGIN-AWARE CALL TO ACTION
// [maraton_cta] — guests: registration page, logged-in users: "add event" tab in the panel.
// All labels and URLs can be overridden with attributes.
// ====================================================================

add_shortcode('maraton_cta', 'render_maraton_cta_shortcode');

function render_maraton_cta_shortcode( $atts = array() ) {
    $atts = shortcode_atts( array(
        'label_guest' => 'Zarejestruj swój maraton',
        'url_guest'   => home_url('/zaloguj-sie/'),
        'label_user'  => 'Dodaj wydarzenie',
        'url_user'    => home_url('/panel/') . '#zakladka-dodaj-wydarzenie',
        'class'       => '',
    ), $atts, 'maraton_cta' );

    $logged_in = is_user_logged_in();
    $label     = $logged_in ? $atts['label_user'] : $atts['label_guest'];
    $url       = $logged_in ? $atts['url_user'] : $atts['url_guest'];

    return '<a class="maraton-cta-button ' . esc_attr($atts['class']) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
}

// ====================================================================
// DYNAMIC MENU: Switch Login to Logout and inject Panel dynamically
// ====================================================================

add_filter( 'wp_nav_menu_objects', 'dynamic_menu_items', 10, 2 );

function dynamic_menu_items( $items, $args ) {
    // Create a new array to rebuild the menu in the correct order
    $new_items = array();
    
    // Check if we are currently on the panel page to set active state dynamically
    $is_panel_current = is_page( 'panel' ); 

    foreach ( $items as $item ) {
        
        // Match the exact menu item title set in WordPress Admin (Appearance -> Menus)
        if ( $item->title === 'Zaloguj się' ) {
            if ( is_user_logged_in() ) {
                
                // Generate a dynamic menu item object for the user dashboard
                // Missing state properties added to satisfy Walker_Nav_Menu requirements
                $panel_item = (object) array(
                    'ID'                    => 'dynamic-panel',
                    'db_id'                 => 0,
                    'menu_item_parent'      => $item->menu_item_parent, 
                    'object_id'             => 0,
                    'type'                  => 'custom',
                    'title'                 => esc_html__( 'Panel', 'amnesty-petitions' ),
                    'url'                   => esc_url( home_url( '/panel/' ) ),
                    'classes'               => array( 'menu-item', 'menu-item-type-custom' ),
                    'target'                => '',
                    'attr_title'            => '',
                    'description'           => '',
                    'xfn'                   => '',
                    'status'                => 'publish',
                    'current'               => $is_panel_current,
                    'current_item_ancestor' => false,
                    'current_item_parent'   => false,
                    'has_children'          => false,
                );

                // If panel is active, append the current menu item class for frontend styling
                if ( $is_panel_current ) {
                    $panel_item->classes[] = 'current-menu-item';
                }

                // Insert the Dashboard item first (before Logout)
                $new_items[] = $panel_item;

                // Transform the original Login item into Logout
                $item->title = esc_html__( 'Wyloguj się', 'amnesty-petitions' );
                $item->url   = wp_logout_url( home_url() );
            }
        }
        
        // Skip registration link for authenticated users
        if ( $item->title === 'Zarejestruj się' && is_user_logged_in() ) {
            continue; 
        }

        // Prevent duplication if the 'Panel' was added manually via WP Admin
        if ( $item->title === 'Panel' ) {
            continue; 
        }

        // Push the item to our new compiled menu array
        $new_items[] = $item;
    }
    
    return $new_items;
}

// ====================================================================
// GDPR: organizer consents in Tools → Export Personal Data
// (account data itself is exported by WordPress core)
// ====================================================================

add_filter( 'wp_privacy_personal_data_exporters', 'maraton_register_consent_exporter' );

function maraton_register_consent_exporter( $exporters ) {
    $exporters['maraton-organizer-consents'] = array(
        'exporter_friendly_name' => 'Zgody organizatora Maratonu',
        'callback'               => 'maraton_export_organizer_consents',
    );
    return $exporters;
}

function maraton_export_organizer_consents( $email, $page = 1 ) {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        return array( 'data' => array(), 'done' => true );
    }

    $data = array();
    foreach ( array(
        'consent_marathon' => 'Zgoda na przetwarzanie danych (organizacja Maratonu)',
        'consent_amnesty'  => 'Zgoda na informacje o akcjach Amnesty International',
    ) as $key => $label ) {
        $value = get_user_meta( $user->ID, $key, true );
        if ( '' === $value ) {
            continue;
        }
        $date   = get_user_meta( $user->ID, $key . '_date', true );
        $data[] = array( 'name' => $label, 'value' => ( $value ? 'tak' : 'nie' ) . ( $date ? ' (' . $date . ')' : '' ) );
    }

    return array(
        'data' => $data ? array( array(
            'group_id'    => 'maraton-consents',
            'group_label' => 'Zgody organizatora Maratonu',
            'item_id'     => 'maraton-consents-' . $user->ID,
            'data'        => $data,
        ) ) : array(),
        'done' => true,
    );
}
