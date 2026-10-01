<?php
/**
 * Template Name: Single Petition Action Page
 * Description: Displays a single Amnesty Petition with its custom fields and form.
 * 
 * Note: This file is loaded directly from the plugin. 
 * To override it, copy this file to your active theme's folder.
 */

get_header(); ?>

<main id="primary" class="site-main amnesty-single-petition">
    <?php
    while ( have_posts() ) :
        the_post();
        $petition_id = get_the_ID();

        // Fetch custom meta fields
        $short_desc  = get_post_meta( $petition_id, '_amnesty_petition_short_description', true );
        $video_link  = get_post_meta( $petition_id, '_amnesty_petition_video_link', true );
        $materials   = get_post_meta( $petition_id, '_amnesty_petition_materials', true );
        $top_content = get_post_meta( $petition_id, '_amnesty_petition_top_content', true );
        $show_map    = get_post_meta( $petition_id, '_amnesty_petition_show_map', true );
        
        // Custom field for the country (Assuming this is how you store it)
        $country = get_post_meta( $petition_id, '_amnesty_petition_country', true );
        ?>

        <article id="post-<?php echo esc_attr( $petition_id ); ?>" <?php post_class(); ?>>
            
            <!-- Section: Hero (CSS Grid Layout) -->
            <section class="amnesty-petition-hero-grid">
                
                <!-- Left Column: Image -->
                <div class="amnesty-hero-image">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'large' ); ?>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Content -->
                <div class="amnesty-hero-info">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                    
                    <?php if ( ! empty( $country ) ) : ?>
                        <h2 class="amnesty-country-label"><?php echo esc_html( $country ); ?></h2>
                    <?php endif; ?>

                    <?php if ( ! empty( $short_desc ) ) : ?>
                        <p class="amnesty-short-desc"><?php echo esc_html( $short_desc ); ?></p>
                    <?php endif; ?>

                    <div class="amnesty-main-content">
                        <?php 
                        // Temporarily remove shortcode to avoid double rendering
                        remove_shortcode( 'amnesty_petition' );
                        the_content(); 
                        ?>
                    </div>

                    <?php if ( ! empty( $top_content ) ) : ?>
                        <div class="amnesty-petition-top-content">
                            <?php echo wp_kses_post( $top_content ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $video_link ) ) : ?>
                        <button type="button" class="amnesty-btn-video" data-video-url="<?php echo esc_url( $video_link ); ?>">
                            <?php esc_html_e( 'Obejrzyj wideo', 'amnesty-petitions' ); ?>
                        </button>
                        
                        <!-- Video Modal -->
                        <div id="amnesty-video-modal" class="amnesty-popup-overlay">
                            <div class="amnesty-video-content-inner">
                                <button id="amnesty-close-video" type="button">&times;</button>
                                <div id="amnesty-iframe-container"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Section: Petition Form -->
            <section class="amnesty-petition-form-section">
                <?php 
                // Render the form via shortcode
                add_shortcode( 'amnesty_petition', 'amnesty_petition_render_shortcode' );
                echo do_shortcode( '[amnesty_petition id="' . $petition_id . '"]' ); 
                ?>
            </section>

            <!-- Map and Materials sections remain unchanged below... -->
            
            <?php if ( ! empty( $show_map ) ) : ?>
                <section class="amnesty-petition-map-section" style="margin-bottom: 40px;">
                    <h2 style="margin-top: 0; text-align: center; font-weight: 900; text-transform: uppercase;">
                        <?php esc_html_e( 'Skąd dołączają sygnatariusze?', 'amnesty-petitions' ); ?>
                    </h2>
                    <?php echo do_shortcode( '[amnesty_petition_map id="' . $petition_id . '"]' ); ?>
                </section>
            <?php endif; ?>

        </article>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>