<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Amnesty_Petition_Map_Widget extends Widget_Base {

    public function get_name() {
        return 'amnesty_petition_map_widget';
    }

    public function get_title() {
        return esc_html__( 'Mapa Petycji', 'amnesty-petitions' );
    }

    public function get_icon() {
        return 'eicon-map-pin';
    }

    public function get_categories() {
        return [ 'amnesty_petitions_category' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__( 'Ustawienia Mapy', 'amnesty-petitions' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        // Fetch all petitions to populate the select dropdown
        $petitions = get_posts( [
            'post_type'   => 'amnesty_petition',
            'numberposts' => -1,
        ] );
        
        $petition_options = [
            '0' => esc_html__( '--- Wybierz petycję ---', 'amnesty-petitions' ),
        ];
        
        if ( ! empty( $petitions ) ) {
            foreach ( $petitions as $p ) {
                $petition_options[ $p->ID ] = $p->post_title;
            }
        }

        $this->add_control(
            'selected_petition',
            [
                'label'   => esc_html__( 'Wybierz Petycję dla Mapy', 'amnesty-petitions' ),
                'type'    => Controls_Manager::SELECT,
                'default' => '0',
                'options' => $petition_options,
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $petition_id = (int) $settings['selected_petition'];

        // Guard clause if no petition is selected
        if ( empty( $petition_id ) ) {
            echo '<div class="elementor-alert elementor-alert-warning">' . esc_html__( 'Proszę wybrać petycję, aby wygenerować mapę.', 'amnesty-petitions' ) . '</div>';
            return;
        }

        // Render the map shortcode
        echo do_shortcode( '[amnesty_petition_map id="' . $petition_id . '"]' );
    }
}