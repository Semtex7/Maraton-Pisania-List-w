<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class Amnesty_Petition_Form_Widget extends Widget_Base {

    public function get_name() {
        return 'amnesty_petition_form';
    }

    public function get_title() {
        return esc_html__( 'Formularz Petycji + Postęp', 'amnesty-petitions' );
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return [ 'amnesty_petitions_category' ];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__( 'Ustawienia Petycji', 'amnesty-petitions' ),
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
                'label'   => esc_html__( 'Wybierz Petycję', 'amnesty-petitions' ),
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
            echo '<div class="elementor-alert elementor-alert-warning">' . esc_html__( 'Proszę wybrać petycję w ustawieniach widżetu.', 'amnesty-petitions' ) . '</div>';
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'amnesty_signatures';
        
        // Count database signatures for this specific petition
        $db_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE petition_id = %d", $petition_id ) );
        
        // Fetch meta values defined in cpt-meta.php
        $target = (int) get_post_meta( $petition_id, '_amnesty_petition_target', true );
        $extra  = (int) get_post_meta( $petition_id, '_amnesty_petition_extra_letters', true );
        
        $total_signatures = $db_count + $extra;
        
        // Calculate percentage (max 100%)
        $percent = 0;
        if ( $target > 0 ) {
            $percent = min( 100, round( ( $total_signatures / $target ) * 100 ) );
        }

        // Output Progress Bar HTML
        ?>
        <div class="amnesty-progress-container" style="margin-bottom: 30px; text-align: center;">
            <h4 style="margin-bottom: 10px; font-weight: bold; text-transform: uppercase;">
                <?php printf( esc_html__( 'Zebraliśmy już %d z %d podpisów!', 'amnesty-petitions' ), $total_signatures, $target ); ?>
            </h4>
            <div class="amnesty-progress-bar-wrap" style="width: 100%; background-color: #e0e0e0; border-radius: 8px; overflow: hidden; height: 25px;">
                <div class="amnesty-progress-bar-fill" style="width: <?php echo esc_attr( $percent ); ?>%; background-color: #ffff00; height: 100%; transition: width 0.5s ease-in-out;"></div>
            </div>
        </div>
        <?php

        // Render the form shortcode
        echo do_shortcode( '[amnesty_petition id="' . $petition_id . '"]' );
    }
}