<?php
/* Block direct access to the file */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Block_Counter_Widget extends \Elementor\Widget_Base {

	/* Define widget internal name */
	public function get_name() {
		return 'block_counter';
	}

	/* Define widget visible title */
	public function get_title() {
		return 'Licznik Digitalowy';
	}

	/* Define widget icon in the Elementor panel */
	public function get_icon() {
		return 'eicon-counter';
	}

	/* Define widget category */
	public function get_categories() {
		return [ 'general' ];
	}

	/* Register input fields for the user */
	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			[
				'label' => 'Ustawienia',
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		/* Control for the target number */
		$this->add_control(
			'target_number',
			[
				'label' => 'Docelowa liczba',
				'type' => \Elementor\Controls_Manager::NUMBER,
				'default' => 2000,
			]
		);

		/* Control for the animation duration in milliseconds */
		$this->add_control(
			'duration_ms',
			[
				'label' => 'Czas animacji (ms)',
				'type' => \Elementor\Controls_Manager::NUMBER,
				'default' => 3000,
				'step' => 100,
			]
		);

		$this->end_controls_section();
	}

	/* Render the widget output on the frontend */
	protected function render() {
		$settings = $this->get_settings_for_display();
		
		$target_number = absint( $settings['target_number'] );
		$duration = absint( $settings['duration_ms'] );
		
		/* Convert number to string to count its digits */
		$target_string = (string) $target_number;
		$digits_count = strlen( $target_string );

		/* Output the wrapper with data attributes for JS */
		echo '<div class="custom-block-counter-wrapper" data-target="' . esc_attr( $target_number ) . '" data-duration="' . esc_attr( $duration ) . '">';
		
		/* Pre-render exact number of spans initialized with zeros */
		for ( $i = 0; $i < $digits_count; $i++ ) {
			echo '<span class="counter-digit">0</span>';
		}
		
		echo '</div>';
	}
}