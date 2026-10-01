<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Create or update custom database table on activation
register_activation_hook( AMNESTY_PETITIONS_PATH . 'amnesty-petitions.php', 'amnesty_petitions_activate' );

function amnesty_petitions_activate() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'amnesty_signatures';
    $charset_collate = $wpdb->get_charset_collate();

    // Define table structure including new columns for letter type and custom message
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        petition_id bigint(20) NOT NULL,
        first_name varchar(100) NOT NULL,
        last_name varchar(150) NOT NULL,
        email varchar(150) NOT NULL,
        phone varchar(50) DEFAULT '' NOT NULL,
        city varchar(150) DEFAULT '' NOT NULL,
        letter_type varchar(50) DEFAULT 'appeal' NOT NULL,
        custom_message text NOT NULL,
        campaign_consent tinyint(1) DEFAULT 0 NOT NULL,
        newsletter_consent tinyint(1) DEFAULT 0 NOT NULL,
        signed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        salesforce_synced tinyint(1) DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id),
        KEY petition_id (petition_id),
        KEY email (email)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    // dbDelta safely executes the SQL, creating the table or adding missing columns
    dbDelta( $sql );

    // Ensure CPT is registered before flushing rewrite rules
    if ( function_exists( 'amnesty_register_petition_cpt' ) ) {
        amnesty_register_petition_cpt();
    }
    flush_rewrite_rules();
}

// Flush rewrite rules on deactivation
register_deactivation_hook( AMNESTY_PETITIONS_PATH . 'amnesty-petitions.php', 'amnesty_petitions_deactivate' );

function amnesty_petitions_deactivate() {
    flush_rewrite_rules();
}