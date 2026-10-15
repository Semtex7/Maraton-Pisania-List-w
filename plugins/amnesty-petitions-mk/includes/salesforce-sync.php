<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) exit;

/* ====================================================================
 * BACKGROUND SALESFORCE SYNC
 * Signatures are saved with salesforce_synced = 0. WP-Cron sends them to Salesforce:
 * right after a signature (single event) and every 5 minutes (retries, missed events).
 * A row that fails AMNESTY_SF_MAX_ATTEMPTS times is no longer retried (shown as failed in Podpisy).
 * Manual run: wp eval 'print_r( amnesty_sf_process_queue() );'
 * ==================================================================== */

define( 'AMNESTY_SF_MAX_ATTEMPTS', 5 );
define( 'AMNESTY_SF_BATCH_SIZE', 50 );
define( 'AMNESTY_SF_TIME_BUDGET', 40 ); // seconds per run

add_filter( 'cron_schedules', 'amnesty_sf_cron_schedules' );

function amnesty_sf_cron_schedules( $schedules ) {
    $schedules['amnesty_five_minutes'] = array(
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display'  => 'Co 5 minut (synchronizacja Salesforce)',
    );
    return $schedules;
}

add_action( 'init', 'amnesty_sf_schedule_recurring_sync' );

function amnesty_sf_schedule_recurring_sync() {
    if ( ! wp_next_scheduled( 'amnesty_sf_sync_queue' ) ) {
        wp_schedule_event( time() + MINUTE_IN_SECONDS, 'amnesty_five_minutes', 'amnesty_sf_sync_queue' );
    }
}

// Called after a signature is saved. WordPress ignores a duplicate single event within 10 minutes,
// later signatures are then picked up by the recurring run.
function amnesty_sf_schedule_sync_soon() {
    if ( ! wp_next_scheduled( 'amnesty_sf_sync_now' ) ) {
        wp_schedule_single_event( time(), 'amnesty_sf_sync_now' );
    }
}

add_action( 'amnesty_sf_sync_queue', 'amnesty_sf_process_queue' );
add_action( 'amnesty_sf_sync_now', 'amnesty_sf_process_queue' );

/**
 * Sends pending signatures (petitions with a Salesforce Topic) to Salesforce.
 * Returns array( 'synced' => int, 'failed' => int, 'skipped' => string ).
 */
function amnesty_sf_process_queue() {
    global $wpdb;

    $result = array( 'synced' => 0, 'failed' => 0, 'skipped' => '' );

    // One run at a time (cron can overlap with the "soon" event)
    if ( get_transient( 'amnesty_sf_sync_lock' ) ) {
        $result['skipped'] = 'locked';
        return $result;
    }
    set_transient( 'amnesty_sf_sync_lock', 1, 5 * MINUTE_IN_SECONDS );

    $table   = $wpdb->prefix . 'amnesty_signatures';
    $started = time();
    $auth    = null;

    while ( time() - $started < AMNESTY_SF_TIME_BUDGET ) {
        // Only rows whose petition has a Topic; rows without one are not part of the queue
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.first_name, s.last_name, s.email, s.phone, s.city, s.campaign_consent, pm.meta_value AS topic_name
             FROM $table s
             JOIN {$wpdb->postmeta} pm ON pm.post_id = s.petition_id AND pm.meta_key = '_amnesty_petition_topic_name' AND pm.meta_value <> ''
             WHERE s.salesforce_synced = 0 AND s.salesforce_attempts < %d
             ORDER BY s.id ASC
             LIMIT %d",
            AMNESTY_SF_MAX_ATTEMPTS,
            AMNESTY_SF_BATCH_SIZE
        ) );

        if ( empty( $rows ) ) {
            break;
        }

        // One token per run
        if ( null === $auth ) {
            $auth = amnesty_sf_get_access_token();
            if ( ! $auth ) {
                $result['skipped'] = 'no-token';
                break; // Credentials/connection problem: leave attempts untouched, retry on next run
            }
        }

        $failed_in_batch = 0;
        foreach ( $rows as $row ) {
            $ok = amnesty_sf_push_signature_to_topic( $row->first_name, $row->last_name, $row->email, $row->phone, $row->city, $row->topic_name, $auth, (int) $row->campaign_consent );

            if ( $ok ) {
                $wpdb->update( $table, array( 'salesforce_synced' => 1 ), array( 'id' => $row->id ) );
                $result['synced']++;
            } else {
                $wpdb->query( $wpdb->prepare( "UPDATE $table SET salesforce_attempts = salesforce_attempts + 1 WHERE id = %d", $row->id ) );
                amnesty_custom_log( "SF Sync Failed (signature #{$row->id}) on topic: {$row->topic_name}" );
                $result['failed']++;
                $failed_in_batch++;
            }

            if ( time() - $started >= AMNESTY_SF_TIME_BUDGET ) {
                break 2;
            }
        }

        // Whole batch failed: probably an API outage, stop and retry on the next run
        if ( $failed_in_batch === count( $rows ) ) {
            break;
        }
    }

    delete_transient( 'amnesty_sf_sync_lock' );
    return $result;
}
