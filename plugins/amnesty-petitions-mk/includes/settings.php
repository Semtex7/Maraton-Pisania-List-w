<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Register Settings Page
add_action( 'admin_menu', 'amnesty_register_global_settings_page' );
function amnesty_register_global_settings_page() {
    add_submenu_page( 
        'edit.php?post_type=amnesty_petition', 
        __( 'Ustawienia Wtyczki Petycji', 'amnesty-petitions' ), 
        __( 'Ustawienia', 'amnesty-petitions' ), 
        'manage_options', 
        'amnesty-global-settings', 
        'amnesty_render_global_settings_page' 
    );
}

// Register Settings
add_action( 'admin_init', 'amnesty_register_global_settings' );
function amnesty_register_global_settings() {
    $group = 'amnesty_global_settings_group';
    
    $settings = [
        // Colors
        'amnesty_color_btn_bg'            => 'color',
        'amnesty_color_btn_hover'         => 'color',
        'amnesty_color_btn_text'          => 'color', 
        'amnesty_color_marker_bg'         => 'color',
        'amnesty_color_marker_text'       => 'color',
        
        // Texts & Textareas
        'amnesty_text_rodo_intro'         => 'textarea',
        'amnesty_text_rodo_full'          => 'textarea', 
        'amnesty_text_consent_campaign'   => 'textarea',
        'amnesty_text_consent_newsletter' => 'textarea', 
        'amnesty_text_popup_title'        => 'text',
        'amnesty_text_popup_desc'         => 'textarea',
        
        // URLs
        'amnesty_link_privacy'            => 'url',
        'amnesty_link_donate'             => 'url',
        'amnesty_link_campaign'           => 'url',
        'amnesty_sf_instance_url'         => 'url',
        
        // API Keys (Standard text strings)
        'amnesty_sf_client_id'            => 'text',
        'amnesty_sf_client_secret'        => 'text'
    ];

    foreach ($settings as $setting_name => $type) {
        $sanitize_callback = '';
        
        if ( $type === 'color' ) {
            $sanitize_callback = 'sanitize_hex_color';
        } elseif ( $type === 'url' ) {
            $sanitize_callback = 'esc_url_raw';
        } elseif ( $type === 'textarea' ) {
            $sanitize_callback = 'sanitize_textarea_field';
        } else {
            $sanitize_callback = 'sanitize_text_field';
        }

        register_setting($group, $setting_name, [
            'sanitize_callback' => $sanitize_callback
        ]);
    }
}

// Helper function to render table rows
function amnesty_field_row($label, $input_html) {
    echo "<tr><th scope='row'>{$label}</th><td>{$input_html}</td></tr>";
}

// Render Settings Page
function amnesty_render_global_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Ustawienia Wtyczki Petycji', 'amnesty-petitions' ); ?></h1>
        
        <!-- Tab navigation -->
        <h2 class="nav-tab-wrapper" id="amnesty-tabs">
            <a href="#appearance" class="nav-tab nav-tab-active" data-tab="appearance"><?php esc_html_e( 'Wygląd', 'amnesty-petitions' ); ?></a>
            <a href="#content" class="nav-tab" data-tab="content"><?php esc_html_e( 'Treści', 'amnesty-petitions' ); ?></a>
            <a href="#links" class="nav-tab" data-tab="links"><?php esc_html_e( 'Linki', 'amnesty-petitions' ); ?></a>
            <a href="#api" class="nav-tab" data-tab="api"><?php esc_html_e( 'Salesforce API', 'amnesty-petitions' ); ?></a>
        </h2>

        <form method="post" action="options.php">
            <?php settings_fields( 'amnesty_global_settings_group' ); ?>
            
            <!-- Tab: Appearance -->
            <table class="form-table amnesty-tab-content" id="tab-appearance">
            <?php
                amnesty_field_row(__( 'Tło przycisku', 'amnesty-petitions' ), '<input type="color" name="amnesty_color_btn_bg" value="'.esc_attr(get_option('amnesty_color_btn_bg', '#1a1a1a')).'" />');
                amnesty_field_row(__( 'Tło przycisku (hover)', 'amnesty-petitions' ), '<input type="color" name="amnesty_color_btn_hover" value="'.esc_attr(get_option('amnesty_color_btn_hover', '#000000')).'" />');
                amnesty_field_row(__( 'Tekst przycisku', 'amnesty-petitions' ), '<input type="color" name="amnesty_color_btn_text" value="'.esc_attr(get_option('amnesty_color_btn_text', '#ffffff')).'" />');
                // amnesty_field_row(__( 'Tło markera mapy', 'amnesty-petitions' ), '<input type="color" name="amnesty_color_marker_bg" value="'.esc_attr(get_option('amnesty_color_marker_bg', '#1a1a1a')).'" />');
                // amnesty_field_row(__( 'Tekst markera mapy', 'amnesty-petitions' ), '<input type="color" name="amnesty_color_marker_text" value="'.esc_attr(get_option('amnesty_color_marker_text', '#eebf9c')).'" />');
            ?>
            </table>

            <!-- Tab: Content -->
            <table class="form-table amnesty-tab-content" id="tab-content" style="display: none;">
            <?php
                amnesty_field_row(__( 'Skrót RODO', 'amnesty-petitions' ), '<textarea name="amnesty_text_rodo_intro" style="width:100%">'.esc_textarea(get_option('amnesty_text_rodo_intro', 'Klikając "Wysyłam!", przekazujesz swój głos...')).'</textarea>');
                amnesty_field_row(__( 'Pełne RODO', 'amnesty-petitions' ), '<textarea name="amnesty_text_rodo_full" style="width:100%">'.esc_textarea(get_option('amnesty_text_rodo_full', 'Twoje dane będą przetwarzane...')).'</textarea>');
                amnesty_field_row(__( 'Zgoda Kampanijna', 'amnesty-petitions' ), '<textarea name="amnesty_text_consent_campaign" style="width:100%">'.esc_textarea(get_option('amnesty_text_consent_campaign', 'Chcę otrzymywać informacje...')).'</textarea>');
                amnesty_field_row(__( 'Zgoda Newsletter', 'amnesty-petitions' ), '<textarea name="amnesty_text_consent_newsletter" style="width:100%">'.esc_textarea(get_option('amnesty_text_consent_newsletter', 'Chcę otrzymywać informacje...')).'</textarea>');
                amnesty_field_row(__( 'Tytuł Pop-up', 'amnesty-petitions' ), '<input type="text" name="amnesty_text_popup_title" value="'.esc_attr(get_option('amnesty_text_popup_title', 'Dziękujemy!')).'" style="width:100%" />');
                amnesty_field_row(__( 'Opis Pop-up', 'amnesty-petitions' ), '<textarea name="amnesty_text_popup_desc" style="width:100%">'.esc_textarea(get_option('amnesty_text_popup_desc', 'Wesprzyj nas:')).'</textarea>');
            ?>
            </table>
            
            <!-- Tab: Links -->
            <table class="form-table amnesty-tab-content" id="tab-links" style="display: none;">
            <?php
                amnesty_field_row(__( 'Link Prywatności', 'amnesty-petitions' ), '<input type="url" name="amnesty_link_privacy" value="'.esc_attr(get_option('amnesty_link_privacy', '#')).'" style="width:100%" />');
                amnesty_field_row(__( 'Link Darowizny', 'amnesty-petitions' ), '<input type="url" name="amnesty_link_donate" value="'.esc_attr(get_option('amnesty_link_donate', '#')).'" style="width:100%" />');
                amnesty_field_row(__( 'Link Kampanii', 'amnesty-petitions' ), '<input type="url" name="amnesty_link_campaign" value="'.esc_attr(get_option('amnesty_link_campaign', '#')).'" style="width:100%" />');
            ?>
            </table>

            <!-- Tab: API -->
            <table class="form-table amnesty-tab-content" id="tab-api" style="display: none;">
            <?php
                amnesty_field_row(__( 'Instance URL', 'amnesty-petitions' ), '<input type="url" name="amnesty_sf_instance_url" value="'.esc_attr(get_option('amnesty_sf_instance_url', '')).'" style="width:100%" />');
                amnesty_field_row(__( 'Client ID', 'amnesty-petitions' ), '<input type="text" name="amnesty_sf_client_id" value="'.esc_attr(get_option('amnesty_sf_client_id', '')).'" style="width:100%" />');
                amnesty_field_row(__( 'Client Secret', 'amnesty-petitions' ), '<input type="password" name="amnesty_sf_client_secret" value="'.esc_attr(get_option('amnesty_sf_client_secret', '')).'" style="width:100%" />');
            ?>
            </table>

            <?php submit_button( __( 'Zapisz Ustawienia', 'amnesty-petitions' ) ); ?>
        </form>

        <script>
            // Handle tab switching without reloading the page
            document.addEventListener('DOMContentLoaded', function() {
                const tabs = document.querySelectorAll('#amnesty-tabs .nav-tab');
                const contents = document.querySelectorAll('.amnesty-tab-content');

                tabs.forEach(tab => {
                    tab.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Remove active state from all tabs
                        tabs.forEach(t => t.classList.remove('nav-tab-active'));
                        contents.forEach(c => c.style.display = 'none');
                        
                        // Activate clicked tab
                        this.classList.add('nav-tab-active');
                        const tabId = 'tab-' + this.getAttribute('data-tab');
                        document.getElementById(tabId).style.display = 'table';
                        
                        // Save active tab in URL hash
                        window.location.hash = this.getAttribute('data-tab');
                    });
                });

                // Restore active tab from URL hash on page load
                if(window.location.hash) {
                    const activeHash = window.location.hash.substring(1);
                    const activeTab = document.querySelector('.nav-tab[data-tab="' + activeHash + '"]');
                    if(activeTab) {
                        activeTab.click();
                    }
                }
            });
        </script>
    </div>
    <?php
}