<?php
/**
 * Petition archive (/petycja/): list of current actions.
 * The main query is limited to the current edition (see amnesty_petition_archive_query()).
 *
 * To override it, copy this file to your active theme's folder.
 */

get_header(); ?>

<main id="primary" class="site-main amnesty-petitions-archive">
    <h1 class="amnesty-archive-title"><?php esc_html_e( 'Akcje w tegorocznym Maratonie', 'amnesty-petitions' ); ?></h1>

    <?php if ( have_posts() ) : ?>
        <div class="amnesty-petitions-grid">
            <?php
            while ( have_posts() ) :
                the_post();
                echo amnesty_get_petition_card_html( get_the_ID() ); // Card HTML is escaped in template-tags.php
            endwhile;
            ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e( 'Brak aktualnych akcji.', 'amnesty-petitions' ); ?></p>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
