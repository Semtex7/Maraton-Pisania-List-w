<?php
/* Block direct access to the file */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Widgets_Manager {

    /* Constructor to hook into Elementor */
    public function __construct() {
        add_action( 'elementor/widgets/register', [ $this, 'register_custom_widgets' ] );
    }

    /* Main method to load and register widgets */
    public function register_custom_widgets( $widgets_manager ) {
        
        // Block_Counter_Widget is registered by the amnesty-petitions-mk plugin.
    }
}

new Widgets_Manager();