<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

// Bump when the table structure below changes; amnesty_petitions_maybe_upgrade_db() then runs dbDelta
// on every environment without re-activating the plugin.
define( 'AMNESTY_PETITIONS_DB_VERSION', 2 );

// Create or update custom database table on activation
register_activation_hook( AMNESTY_PETITIONS_PATH . 'amnesty-petitions.php', 'amnesty_petitions_activate' );

function amnesty_petitions_activate() {
    amnesty_petitions_install_table();

    // Ensure CPT is registered before flushing rewrite rules
    if ( function_exists( 'amnesty_register_petition_cpt' ) ) {
        amnesty_register_petition_cpt();
    }
    flush_rewrite_rules();
}

function amnesty_petitions_install_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'amnesty_signatures';
    $charset_collate = $wpdb->get_charset_collate();

    // Define table structure including new columns for letter type and custom message.
    // salesforce_attempts: failed background sync attempts (v2, see includes/salesforce-sync.php)
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
        salesforce_attempts tinyint(3) DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id),
        KEY petition_id (petition_id),
        KEY email (email),
        KEY salesforce_synced (salesforce_synced)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    // dbDelta safely executes the SQL, creating the table or adding missing columns
    dbDelta( $sql );

    update_option( 'amnesty_petitions_db_version', AMNESTY_PETITIONS_DB_VERSION );
}

// Schema upgrade on existing installs (cheap option check on every request)
add_action( 'init', 'amnesty_petitions_maybe_upgrade_db', 5 );

function amnesty_petitions_maybe_upgrade_db() {
    global $wpdb;

    $from = (int) get_option( 'amnesty_petitions_db_version', 1 );
    if ( $from >= AMNESTY_PETITIONS_DB_VERSION ) {
        return;
    }

    amnesty_petitions_install_table();

    // v2 introduced the background Salesforce queue. Signatures that failed before it existed were never
    // retried; keep it that way so old test data is not sent to Salesforce (only new signatures are queued).
    if ( $from < 2 ) {
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}amnesty_signatures SET salesforce_attempts = %d WHERE salesforce_synced = 0",
            AMNESTY_SF_MAX_ATTEMPTS // Defined in salesforce-sync.php (all modules are loaded before init)
        ) );
    }
}

// Flush rewrite rules on deactivation
register_deactivation_hook( AMNESTY_PETITIONS_PATH . 'amnesty-petitions.php', 'amnesty_petitions_deactivate' );

function amnesty_petitions_deactivate() {
    flush_rewrite_rules();
}
