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

    ob_start();

    ?>
    <div class="event-auth-panel event-login-panel">
        <h3>Logowanie</h3>
        <?php if ( ! empty( $login_error ) ) : ?>
            <div class="event-login-error" role="alert"><strong>Logowanie nieudane:</strong> <?php echo esc_html( $login_error ); ?></div>
        <?php endif; ?>

    <form name="event-login-form" id="event-login-form" class="event-login-form" action="<?php echo esc_url( get_permalink() ); ?>" method="post">
        <?php wp_nonce_field( 'event_login_action', 'event_login_nonce' ); ?>

        <p class="login-username">
            <label for="user_login">Nazwa użytkownika lub e-mail</label>
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
        return '<script>window.location.href="' . esc_url(home_url('/panel/')) . '";</script>';
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_registration']) ) {
        
        // Security check
        if ( ! isset($_POST['registration_nonce']) || ! wp_verify_nonce($_POST['registration_nonce'], 'register_action') ) {
            $registration_error = 'Błąd bezpieczeństwa formularza. Odśwież stronę i spróbuj ponownie.';
            return;
        }

        $username = sanitize_user($_POST['user_login']);
        $email    = sanitize_email($_POST['user_email']);
        $password = $_POST['user_pass'];

        // Validation
        if ( empty($username) || empty($email) || empty($password) ) {
            $registration_error = 'Wypełnij wszystkie wymagane pola.';
        } elseif ( username_exists($username) ) {
            $registration_error = 'Taki użytkownik już istnieje. Wybierz inną nazwę.';
        } elseif ( ! is_email($email) ) {
            $registration_error = 'Podaj poprawny adres e-mail.';
        } elseif ( email_exists($email) ) {
            $registration_error = 'Ten adres e-mail jest już zajęty. Przejdź do logowania.';
        } else {
            // Secure user creation
            $user_id = wp_create_user($username, $password, $email);
            
            if ( ! is_wp_error($user_id) ) {
                // Set auth cookies safely before HTML loads
                wp_clear_auth_cookie();
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
                
                // Clean redirect
                wp_safe_redirect( home_url('/panel/') );
                exit; 
            } else {
                $registration_error = $user_id->get_error_message(); 
            }
        }
    }
}

// ====================================================================
// 4. SHORTCODE: REGISTRATION FORM
// ====================================================================

add_shortcode('event_registration', 'render_registration_shortcode');

function render_registration_shortcode() {
    global $registration_error;

    // Check if user is logged in
    if ( is_user_logged_in() ) {
        return '<div>Jesteś już zalogowany! <a href="' . home_url('/panel/') . '">Przejdź do panelu</a></div>';
    }

    $val_login = isset( $_POST['user_login'] ) ? esc_attr( wp_unslash( $_POST['user_login'] ) ) : '';
    $val_email = isset( $_POST['user_email'] ) ? esc_attr( wp_unslash( $_POST['user_email'] ) ) : '';

    ob_start();

    ?>
    <div class="event-auth-panel event-registration-panel">
        <h3>Rejestracja</h3>
        <?php if ( ! empty($registration_error) ) : ?>
            <div class="register-error"><strong>Błąd rejestracji:</strong> <?php echo esc_html( $registration_error ); ?></div>
        <?php endif; ?>

    <form method="post" action="" class="new-event-form">
        <?php wp_nonce_field('register_action', 'registration_nonce'); ?>
        
        <p class="login-username">
            <label>Wybierz nazwę użytkownika <span style="color:red;">*</span></label>
            <input type="text" name="user_login" value="<?php echo $val_login; ?>" required>
        </p>
        
        <p class="login-email">
            <label>Twój adres e-mail <span style="color:red;">*</span></label>
            <input type="email" name="user_email" value="<?php echo $val_email; ?>" required>
        </p>
        
        <p class="login-password">
            <label>Ustaw hasło <span style="color:red;">*</span></label>
            <input type="password" name="user_pass" required>
        </p>
        
        <p class="login-submit">
            <button type="submit" name="submit_registration" class="amnesty-card-button">Zarejestruj się</button>
        </p>
    </form>
    </div>
    <?php
    
    return ob_get_clean();
}

// ====================================================================
// DYNAMIC MENU: Switch Login to Logout for authenticated users
// ====================================================================

add_filter( 'wp_nav_menu_objects', 'dynamic_menu_items', 10, 2 );

function dynamic_menu_items( $items, $args ) {
    foreach ( $items as $key => $item ) {
        
        // Match the exact menu item title set in WordPress Admin (Appearance -> Menus)
        if ( $item->title === 'Zaloguj się' ) {
            if ( is_user_logged_in() ) {
                $item->title = 'Wyloguj się';
                $item->url   = wp_logout_url( home_url() ); // Redirects to homepage after logout
            }
        }
        
        // Hide registration link for users who are already logged in
        if ( $item->title === 'Zarejestruj się' ) {
            if ( is_user_logged_in() ) {
                unset( $items[$key] ); 
            }
        }

        if ( $item->title === 'Panel' ) {
            if ( ! is_user_logged_in() ) {
                unset( $items[$key] ); 
            }
        }
    }
    
    return $items;
}