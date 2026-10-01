<?php 

// Global variable for registration errors
global $registration_error;
$registration_error = '';

// ====================================================================
// 1. SHORTCODE: LOGIN FORM
// ====================================================================

add_shortcode('event_login', 'render_login_shortcode');

function render_login_shortcode() {
    if ( is_user_logged_in() ) {
        return '<script>window.location.href="' . esc_url(home_url('/panel/')) . '";</script>';
    }
    
    ob_start();
    
    wp_login_form(array(
        'redirect'       => home_url('/panel/'), 
        'label_username' => 'Nazwa użytkownika lub E-mail',
        'label_password' => 'Hasło',
        'label_remember' => 'Zapamiętaj mnie',
        'label_log_in'   => 'Zaloguj się',
        'form_id'        => 'event-login-form'
    ));
    
    $login_form_html = ob_get_clean();
    
    $login_form_html = str_replace(
        'class="button button-primary"', 
        'class="button button-primary amnesty-card-button"', 
        $login_form_html
    );
    
    return $login_form_html;
}

// ====================================================================
// 2. HANDLE USER REGISTRATION
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
// 3. SHORTCODE: REGISTRATION FORM
// ====================================================================

add_shortcode('event_registration', 'render_registration_shortcode');

function render_registration_shortcode() {
    global $registration_error;

    // Check if user is logged in
    if ( is_user_logged_in() ) {
        return '<div>Jesteś już zalogowany! <a href="' . home_url('/panel/') . '">Przejdź do panelu</a></div>';
    }

    ob_start();

    // Display registration errors if any exist
    if ( ! empty($registration_error) ) {
        echo '<div class="register-error"><strong>Błąd rejestracji:</strong> ' . esc_html($registration_error) . '</div>';
    }

    // Remember input values after failed validation
    $val_login = isset($_POST['user_login']) ? esc_attr($_POST['user_login']) : '';
    $val_email = isset($_POST['user_email']) ? esc_attr($_POST['user_email']) : '';

    // Registration form HTML structured exactly like wp_login_form output
    ?>
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