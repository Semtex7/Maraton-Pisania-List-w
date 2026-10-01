<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main Elementor Extension Class for Amnesty Petitions
 */
final class Amnesty_Elementor_Extension {

    // Singleton instance
    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        // Hook into WordPress plugins_loaded to check for Elementor
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        // Check if Elementor is installed and activated
        if ( ! did_action( 'elementor/loaded' ) ) {
            return;
        }

        // Register custom category for our widgets
        add_action( 'elementor/elements/categories_registered', [ $this, 'add_widget_categories' ] );
        
        // Register the widgets themselves
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
    }

    public function add_widget_categories( $elements_manager ) {
        $elements_manager->add_category(
            'amnesty_petitions_category',
            [
                'title' => esc_html__( 'Amnesty Petycje', 'amnesty-petitions' ),
                'icon'  => 'fa fa-pen',
            ]
        );
    }

    public function register_widgets( $widgets_manager ) {
        // Require the widget classes only when Elementor is active and registering widgets
        require_once AMNESTY_PETITIONS_PATH . 'includes/widgets/class-petition-form-widget.php';
        // require_once AMNESTY_PETITIONS_PATH . 'includes/widgets/class-petition-map-widget.php';
        require_once AMNESTY_PETITIONS_PATH . 'includes/widgets/class-petition-card-widget.php';

        $widgets_manager->register( new \Amnesty_Petition_Form_Widget() );
        // $widgets_manager->register( new \Amnesty_Petition_Map_Widget() );
        $widgets_manager->register( new \Amnesty_Petition_Card_Widget() );
    }
}

// Instantiate the extension
Amnesty_Elementor_Extension::instance();