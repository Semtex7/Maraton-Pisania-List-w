<?php
// Prevent direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Amnesty_Petition_Card_Widget extends Widget_Base {

    // Define widget name used in code
    public function get_name() {
        return 'amnesty_petition_card_widget';
    }

    // Define widget title displayed in Elementor editor
    public function get_title() {
        return esc_html__( 'Miniatura Akcji', 'amnesty-petitions' );
    }

    // Define icon displayed in Elementor editor
    public function get_icon() {
        return 'eicon-portrait';
    }

    // Assign widget to our custom category
    public function get_categories() {
        return [ 'amnesty_petitions_category' ];
    }

    // Register widget controls (UI in Elementor panel)
    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__( 'Ustawienia Miniatury', 'amnesty-petitions' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        // Fetch all published petitions to populate the select dropdown
        $petitions = get_posts( [
            'post_type'   => 'amnesty_petition',
            'numberposts' => -1,
            'post_status' => 'publish',
        ] );
        
        $petition_options = [
            '0' => esc_html__( '--- Wybierz petycję ---', 'amnesty-petitions' ),
        ];
        
        if ( ! empty( $petitions ) ) {
            foreach ( $petitions as $p ) {
                $petition_options[ $p->ID ] = $p->post_title;
            }
        }

        // Add dropdown control to select a specific petition
        $this->add_control(
            'selected_petition',
            [
                'label'   => esc_html__( 'Wybierz Petycję', 'amnesty-petitions' ),
                'type'    => Controls_Manager::SELECT,
                'default' => '0',
                'options' => $petition_options,
            ]
        );

        $this->end_controls_section();
    }

    // Render the widget output on the frontend
    protected function render() {
        $settings = $this->get_settings_for_display();
        $petition_id = (int) $settings['selected_petition'];

        // Guard clause: Display warning if no petition is selected
        if ( empty( $petition_id ) ) {
            echo '<div class="elementor-alert elementor-alert-warning">' . esc_html__( 'Wybierz petycję w ustawieniach widżetu, aby wyświetlić miniaturę.', 'amnesty-petitions' ) . '</div>';
            return;
        }

        // Check if our custom function exists to prevent fatal errors
        if ( function_exists( 'amnesty_get_petition_card_html' ) ) {
            // Output the HTML using our template tag
            echo amnesty_get_petition_card_html( $petition_id );
        } else {
            echo '<p>' . esc_html__( 'Błąd: Funkcja renderująca kartę nie została znaleziona.', 'amnesty-petitions' ) . '</p>';
        }
    }
}