<?php
// Polish texts for Complianz strings that are not editable in its settings (kept here so plugin updates don't revert them)

// Link titles under the cookie banner: Complianz passes them through cmplz_translate() -> wpml_translate_single_string
add_filter( 'wpml_translate_single_string', 'maraton_complianz_link_titles', 20, 3 );

function maraton_complianz_link_titles( $value, $context = '', $name = '' ) {
    if ( 'complianz' !== $context ) {
        return $value;
    }

    $titles = array(
        'cmplz_link_title_cookie-statement'  => 'Klauzula informacyjna',
        'cmplz_link_title_privacy-statement' => 'Polityka prywatności',
    );

    return isset( $titles[ $name ] ) ? $titles[ $name ] : $value;
}

// Placeholder over blocked embeds (e.g. YouTube) until marketing cookies are accepted.
// The per-service variant contains its own markup and button, so it is left as configured.
add_filter( 'cmplz_accept_cookies_blocked_content', 'maraton_complianz_blocked_content_text' );

function maraton_complianz_blocked_content_text( $text ) {
    if ( function_exists( 'cmplz_get_option' ) && 'yes' === cmplz_get_option( 'consent_per_service' ) ) {
        return $text;
    }

    return 'Kliknij, aby zaakceptować marketingowe pliki cookie i wyświetlić tę treść';
}
