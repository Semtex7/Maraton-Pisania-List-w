<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/* ====================================================================
 * GDPR: WordPress personal data tools (Tools → Export / Erase Personal Data)
 * Signatures are matched by e-mail address. Salesforce is a separate system:
 * data sent there has to be exported/erased in Salesforce.
 * ==================================================================== */

add_filter( 'wp_privacy_personal_data_exporters', 'amnesty_register_privacy_exporter' );

function amnesty_register_privacy_exporter( $exporters ) {
    $exporters['amnesty-petitions'] = array(
        'exporter_friendly_name' => __( 'Podpisy pod petycjami', 'amnesty-petitions' ),
        'callback'               => 'amnesty_privacy_export_signatures',
    );
    return $exporters;
}

function amnesty_privacy_export_signatures( $email, $page = 1 ) {
    global $wpdb;

    $per_page = 100;
    $page     = max( 1, (int) $page );
    $rows     = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}amnesty_signatures WHERE email = %s ORDER BY id ASC LIMIT %d OFFSET %d",
        $email, $per_page, ( $page - 1 ) * $per_page
    ) );

    $items = array();
    foreach ( $rows as $row ) {
        $items[] = array(
            'group_id'    => 'amnesty-signatures',
            'group_label' => __( 'Podpisy pod petycjami', 'amnesty-petitions' ),
            'item_id'     => 'amnesty-signature-' . $row->id,
            'data'        => array(
                array( 'name' => __( 'Petycja', 'amnesty-petitions' ), 'value' => get_the_title( $row->petition_id ) ?: '#' . $row->petition_id ),
                array( 'name' => __( 'Data podpisu', 'amnesty-petitions' ), 'value' => $row->signed_at ),
                array( 'name' => __( 'Imię i nazwisko', 'amnesty-petitions' ), 'value' => trim( $row->first_name . ' ' . $row->last_name ) ),
                array( 'name' => __( 'E-mail', 'amnesty-petitions' ), 'value' => $row->email ),
                array( 'name' => __( 'Telefon', 'amnesty-petitions' ), 'value' => $row->phone ),
                array( 'name' => __( 'Miejscowość', 'amnesty-petitions' ), 'value' => $row->city ),
                array( 'name' => __( 'Typ listu', 'amnesty-petitions' ), 'value' => 'solidarity' === $row->letter_type ? 'Solidarnościowy' : 'Do władz' ),
                array( 'name' => __( 'Treść listu', 'amnesty-petitions' ), 'value' => $row->custom_message ),
                array( 'name' => __( 'Zgoda na informacje o akcjach', 'amnesty-petitions' ), 'value' => $row->campaign_consent ? 'tak' : 'nie' ),
                array( 'name' => __( 'Przekazano do Salesforce', 'amnesty-petitions' ), 'value' => $row->salesforce_synced ? 'tak' : 'nie' ),
            ),
        );
    }

    return array(
        'data' => $items,
        'done' => count( $rows ) < $per_page,
    );
}

add_filter( 'wp_privacy_personal_data_erasers', 'amnesty_register_privacy_eraser' );

function amnesty_register_privacy_eraser( $erasers ) {
    $erasers['amnesty-petitions'] = array(
        'eraser_friendly_name' => __( 'Podpisy pod petycjami', 'amnesty-petitions' ),
        'callback'             => 'amnesty_privacy_erase_signatures',
    );
    return $erasers;
}

function amnesty_privacy_erase_signatures( $email, $page = 1 ) {
    global $wpdb;

    $table   = $wpdb->prefix . 'amnesty_signatures';
    $synced  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE email = %s AND salesforce_synced = 1", $email ) );
    $removed = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE email = %s", $email ) );

    $messages = array();
    if ( $synced > 0 ) {
        $messages[] = sprintf(
            /* translators: %d: number of signatures that were sent to Salesforce */
            __( 'Podpisy tej osoby (%d) zostały wcześniej przekazane do Salesforce — dane w Salesforce trzeba usunąć lub zanonimizować osobno.', 'amnesty-petitions' ),
            $synced
        );
    }

    return array(
        'items_removed'  => $removed > 0,
        'items_retained' => false,
        'messages'       => $messages,
        'done'           => true,
    );
}
