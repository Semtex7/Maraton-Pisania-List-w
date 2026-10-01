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
        
        require_once get_theme_file_path( 'functions/widgets/block-counter.php' );
        
        $widgets_manager->register( new \Block_Counter_Widget() );
    }
}

new Widgets_Manager();