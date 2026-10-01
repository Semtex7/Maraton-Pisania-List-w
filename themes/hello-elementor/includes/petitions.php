<?php

declare( strict_types = 1 );

add_filter( 'amnesty_petitions_template', 'amnesty_theme_petitions_layout' );

if ( ! function_exists( 'amnesty_theme_petitions_layout' ) ) {
	/**
	 * Register the layout for a petition
	 *
	 * @package Amnesty
	 *
	 * @return array<int,array<int,string>>
	 */
	function amnesty_theme_petitions_layout(): array {
		return [
			[ 'amnesty-core/block-section' ],
			[ 'amnesty/petition-template' ],
			[ 'amnesty/petition' ],
		];
	}
}

add_filter( 'amnesty_petition_container', 'amnesty_theme_petitions_container_template' );

if ( ! function_exists( 'amnesty_theme_petitions_container_template' ) ) {
	/**
	 * Override the template for the petitions container
	 *
	 * @package Amnesty
	 *
	 * @return string
	 */
	function amnesty_theme_petitions_container_template(): string {
		return locate_template( 'partials/petition-container.php' );
	}
}

add_filter( 'amnesty_petition_view_markup', 'amnesty_theme_petitions_view_markup' );

if ( ! function_exists( 'amnesty_theme_petitions_view_markup' ) ) {
	/**
	 * Wrap the petitions markup in a container
	 *
	 * @package Amnesty
	 *
	 * @param string $markup the existing markup
	 *
	 * @return string
	 */
	function amnesty_theme_petitions_view_markup( string $markup = '' ): string {
		if ( 'templates/without-sidebar.php' === get_page_template_slug() ) {
			return $markup;
		}

		return sprintf( '<div class="article-sidebar">%s</div>', $markup );
	}
}

add_action( 'init', 'amnesty_theme_register_petitions_meta' );

if ( ! function_exists( 'amnesty_theme_register_petitions_meta' ) ) {
	/**
	 * Register theme metadata with petitions post type
	 *
	 * @package Amnesty
	 *
	 * @return void
	 */
	function amnesty_theme_register_petitions_meta(): void {
		$slug = get_option( 'aip_petition_slug' ) ?: 'petition';

		// post type isn't registered
		if ( ! get_post_type( $slug ) ) {
			return;
		}

		$args = [
			'show_in_rest'  => true,
			'single'        => true,
			'auth_callback' => fn () => current_user_can( 'edit_posts' ),
		];

		register_meta( $slug, '_hero_title', $args );
		register_meta( $slug, '_hero_content', $args );
		register_meta( $slug, '_hero_cta_text', $args );
		register_meta( $slug, '_hero_cta_link', $args );
		register_meta( $slug, '_hero_alignment', $args );
		register_meta( $slug, '_hero_background', $args );
		register_meta( $slug, '_hero_size', $args );
		register_meta( $slug, '_hero_show', $args );
		register_meta( $slug, '_hero_type', $args );
		register_meta( $slug, '_hero_embed', $args );
		register_meta( $slug, '_hero_video_id', $args + [ 'type' => 'integer' ] );
	}
}
