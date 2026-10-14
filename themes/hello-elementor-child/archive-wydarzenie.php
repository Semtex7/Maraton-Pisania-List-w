<?php
/**
 * Events list (/wydarzenie/): all events shown on the map, sorted by start date.
 * Query: maraton_events_archive_query() in functions/events-list.php.
 */

global $wp_query;
$events = maraton_get_events_list_items( $wp_query->posts );

get_header(); ?>

<main id="primary" class="site-main events-list-page">
    <h1 class="events-list-title"><?php esc_html_e('Wydarzenia Maratonu', 'amnestympl'); ?></h1>

    <?php if ( ! empty($events) ) : ?>
        <input type="search" id="events-list-search" class="events-list-search" placeholder="<?php esc_attr_e('Wyszukaj miasto, ulicę lub nazwę organizatora...', 'amnestympl'); ?>" aria-label="<?php esc_attr_e('Wyszukaj wydarzenie', 'amnestympl'); ?>">

        <ul class="events-list" id="events-list">
            <?php foreach ( $events as $event ) :
                $address = trim( $event['street'] . ', ' . trim( $event['zip'] . ' ' . $event['city'] ), ', ' );
                $search  = mb_strtolower( implode(' ', array( $event['title'], $event['venue'], $address )), 'UTF-8' );
            ?>
                <li class="events-list-item" data-search="<?php echo esc_attr($search); ?>">
                    <div class="events-list-date"><?php echo esc_html( maraton_format_event_dates($event['start'], $event['end']) ); ?></div>
                    <div class="events-list-main">
                        <h2 class="events-list-name"><?php echo esc_html($event['title']); ?></h2>
                        <?php if ( $event['venue'] ) : ?>
                            <div class="events-list-venue"><?php echo esc_html($event['venue']); ?></div>
                        <?php endif; ?>
                        <div class="events-list-address"><?php echo esc_html($address); ?></div>
                    </div>
                    <div class="events-list-badges">
                        <?php if ( $event['is_public'] ) : ?>
                            <span class="events-list-badge badge-public"><?php esc_html_e('Otwarte', 'amnestympl'); ?></span>
                        <?php else : ?>
                            <span class="events-list-badge badge-private"><?php esc_html_e('Zamknięte', 'amnestympl'); ?></span>
                        <?php endif; ?>
                        <?php if ( $event['accessible'] ) : ?>
                            <span class="events-list-badge badge-accessible"><?php esc_html_e('Dostępne dla osób z niepełnosprawnościami', 'amnestympl'); ?></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="events-list-empty" id="events-list-empty" hidden><?php esc_html_e('Brak wydarzeń pasujących do wyszukiwania.', 'amnestympl'); ?></p>

        <script>
            // Client-side filter (same fields as the map search)
            document.getElementById('events-list-search').addEventListener('input', function () {
                const query = this.value.toLowerCase().trim();
                let visible = 0;
                document.querySelectorAll('#events-list .events-list-item').forEach(function (item) {
                    const match = ! query || item.dataset.search.includes(query);
                    item.hidden = ! match;
                    if (match) visible++;
                });
                document.getElementById('events-list-empty').hidden = visible > 0;
            });
        </script>
    <?php else : ?>
        <p><?php esc_html_e('Nie ma jeszcze wydarzeń.', 'amnestympl'); ?></p>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
