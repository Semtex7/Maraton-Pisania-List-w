<?php
/**
 * Render a single petition card based on the design.
 * Can be used in loops, shortcodes, and Elementor widgets.
 * 
 * @param int $post_id The ID of the petition.
 * @return string HTML of the petition card.
 */
function amnesty_get_petition_card_html( $post_id ) {
    if ( ! $post_id || get_post_type( $post_id ) !== 'amnesty_petition' ) {
        return '';
    }

    // Get basic post data
    $title     = get_the_title( $post_id );
    $permalink = get_permalink( $post_id );
    
    // Get thumbnail (fallback to placeholder if empty)
    $thumbnail = get_the_post_thumbnail( $post_id, 'medium_large', array( 'class' => 'amnesty-card-img' ) );
    if ( empty( $thumbnail ) ) {
        $thumbnail = '<img src="' . esc_url( AMNESTY_PETITIONS_URL . 'assets/images/placeholder.jpg' ) . '" class="amnesty-card-img" alt="Placeholder">';
    }

    // Fetch custom meta fields
    $country = get_post_meta( $post_id, '_amnesty_petition_country', true );
    $target  = (int) get_post_meta( $post_id, '_amnesty_petition_target', true );
    $extra   = (int) get_post_meta( $post_id, '_amnesty_petition_extra_letters', true );

    // Calculate progress directly from the database
    global $wpdb;
    $table_name = $wpdb->prefix . 'amnesty_signatures';
    $db_count   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_name WHERE petition_id = %d", $post_id ) );
    
    $total_signatures = $db_count + $extra;
    
    // Calculate percentage (max 100%)
    $percent = 0;
    $target_val = (int) $target;
    $signatures_val = (int) $total_signatures;

    if ( $target_val > 0 ) {
        $percent = ( $signatures_val / $target_val ) * 100;
        
        // Cap at 100% so the bar doesn't break out of the container
        if ( $percent > 100 ) {
            $percent = 100;
        }
        
        // Round to 2 decimal places for cleaner inline CSS
        $percent = round( $percent, 2 ); 
    }

    // Default country text if empty
    if ( empty( $country ) ) {
        $country = 'KRAJ NIEZNANY';
    }

    // Start building HTML
    ob_start();
    ?>
    <article class="amnesty-petition-card">
        <div class="amnesty-card-header">
            <?php echo $thumbnail; // Escaped by WordPress function ?>
            <span class="amnesty-card-badge"><?php echo esc_html( mb_strtoupper( $country, 'UTF-8' ) ); ?></span>
        </div>
        
        <div class="amnesty-card-body">
            <h3 class="amnesty-card-title"><?php echo esc_html( $title ); ?></h3>
            
            <div class="amnesty-card-progress-wrapper">
                <div class="amnesty-card-progress-track">
                    <div class="amnesty-card-progress-fill" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
                </div>
                <div class="amnesty-card-progress-text">
                    <strong><?php echo esc_html( $total_signatures ); ?>/<?php echo esc_html( $target ); ?></strong> listów
                </div>
            </div>
            <a href="<?php echo esc_url( $permalink ); ?>" class="amnesty-card-button">
                <?php _e('WYŚLIJ LIST','amnesty-petitions');?>
            </a>
        </div>
    </article>
    <?php
    return ob_get_clean();
}