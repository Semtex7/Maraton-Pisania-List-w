<?php
// EVENTS LIST (/wydarzenie/ archive, template: archive-wydarzenie.php)
// Shows the same events as the map: published and marked "widoczne na mapie".

add_action('pre_get_posts', 'maraton_events_archive_query');

function maraton_events_archive_query( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive('wydarzenie') ) {
        return;
    }

    $query->set('posts_per_page', -1);
    $query->set('meta_query', array(
        array(
            'key'   => 'widoczne_na_mapie',
            'value' => '1',
        ),
    ));
}

// Event data prepared for the list, sorted by start date (events without a date go last)
function maraton_get_events_list_items( $posts ) {
    $items = array();

    foreach ( $posts as $post ) {
        $start  = maraton_get_event_datetime($post->ID, 'start');
        $end    = maraton_get_event_datetime($post->ID, 'end');
        $public = get_post_meta($post->ID, 'wydarzenie_publiczne', true);

        $items[] = array(
            'title'      => get_the_title($post),
            'start'      => $start,
            'end'        => $end,
            'venue'      => get_post_meta($post->ID, 'nazwa_lokalu', true),
            'street'     => get_post_meta($post->ID, 'ulica', true),
            'zip'        => get_post_meta($post->ID, 'kod_pocztowy', true),
            'city'       => get_post_meta($post->ID, 'miejscowosc', true),
            'is_public'  => ( '1' === (string) $public || 'tak' === $public ),
            'accessible' => ( '1' === (string) get_post_meta($post->ID, 'dostepnosc', true) ),
        );
    }

    usort($items, function ( $a, $b ) {
        if ( $a['start'] === $b['start'] ) return strcmp($a['title'], $b['title']);
        if ( '' === $a['start'] ) return 1;
        if ( '' === $b['start'] ) return -1;
        return strcmp($a['start'], $b['start']);
    });

    return $items;
}

// "12.12.2026, 17:00 – 20:00" / "12.12.2026" / "12–14.12.2026 ..." (date-only values have no time part)
function maraton_format_event_dates( $start, $end ) {
    if ( '' === $start ) {
        return '';
    }

    $has_time = function ( $value ) { return strlen($value) > 10 && '00:00' !== substr($value, 11, 5); };
    $out = date_i18n('d.m.Y', strtotime($start));
    if ( $has_time($start) ) {
        $out .= ', ' . date_i18n('H:i', strtotime($start));
    }

    if ( '' !== $end ) {
        $same_day = substr($start, 0, 10) === substr($end, 0, 10);
        if ( ! $same_day ) {
            $out .= ' – ' . date_i18n('d.m.Y', strtotime($end));
            if ( $has_time($end) ) $out .= ', ' . date_i18n('H:i', strtotime($end));
        } elseif ( $has_time($end) ) {
            $out .= ' – ' . date_i18n('H:i', strtotime($end));
        }
    }

    return $out;
}
