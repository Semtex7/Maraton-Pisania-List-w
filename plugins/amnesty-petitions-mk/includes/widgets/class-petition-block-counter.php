<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Block_Counter_Widget extends \Elementor\Widget_Base {

    // Define widget internal name
    public function get_name() {
        return 'block_counter';
    }

    // Define widget visible title
    public function get_title() {
        return esc_html__( 'Licznik Digitalowy', 'amnesty-petitions' );
    }

    // Define widget icon in the Elementor panel
    public function get_icon() {
        return 'eicon-counter';
    }

    // Define widget category to match the plugin's custom category
    public function get_categories() {
        return [ 'amnesty_petitions_category' ];
    }

    // Tell Elementor to load this specific script when the widget is rendered
    public function get_script_depends() {
        return [ 'amnesty-counter-script' ];
    }

    // Register input fields for the user
    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__( 'Ustawienia', 'amnesty-petitions' ),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        // Select control for data source (mutually exclusive options)
        $this->add_control(
            'data_source',
            [
                'label' => esc_html__( 'Źródło danych', 'amnesty-petitions' ),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'manual',
                'options' => [
                    'manual'     => esc_html__( 'Wpisz ręcznie', 'amnesty-petitions' ),
                    'signatures' => esc_html__( 'Pobierz liczbę podpisów', 'amnesty-petitions' ),
                    'events'     => esc_html__( 'Pobierz liczbę wydarzeń', 'amnesty-petitions' ),
                ],
            ]
        );

        // Control for the target number (hidden if dynamic count is active)
        $this->add_control(
            'target_number',
            [
                'label' => esc_html__( 'Docelowa liczba', 'amnesty-petitions' ),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 2000,
                'condition' => [
                    'data_source' => 'manual', // Display only when manual is selected
                ],
            ]
        );

        // Control for the animation duration in milliseconds
        $this->add_control(
            'duration_ms',
            [
                'label' => esc_html__( 'Czas animacji (ms)', 'amnesty-petitions' ),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 3000,
                'step' => 100,
            ]
        );

        $this->end_controls_section();
    }

    // Render the widget output on the frontend
    protected function render() {
        // Enforce script loading directly when the widget is rendered on frontend
        wp_enqueue_script( 'amnesty-counter-script' );

        $settings = $this->get_settings_for_display();
        
        $target_number = 0;
        $data_source = $settings['data_source'];

        // Determine the target number based on the selected data source
        // Both dynamic sources are limited to the edition range (see includes/edition.php)
        if ( 'signatures' === $data_source ) {
            $target_number = amnesty_count_edition_letters();

        } elseif ( 'events' === $data_source ) {
            $target_number = amnesty_count_edition_events();

        } else {
            // Use the static number provided in Elementor settings
            $target_number = absint( $settings['target_number'] );
        }
        
        $duration = absint( $settings['duration_ms'] );
        
        // Convert number to string to count its digits for HTML output
        $target_string = (string) $target_number;
        $digits_count = strlen( $target_string );

        // Output the wrapper with data attributes for JS animation
        echo '<div class="custom-block-counter-wrapper" data-target="' . esc_attr( $target_number ) . '" data-duration="' . esc_attr( $duration ) . '">';
        
        // Pre-render exact number of spans initialized with zeros
        for ( $i = 0; $i < $digits_count; $i++ ) {
            echo '<span class="counter-digit">0</span>';
        }
        
        echo '</div>';
    }
}