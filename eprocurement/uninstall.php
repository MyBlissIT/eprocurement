<?php
/**
 * Fired when the plugin is uninstalled (deleted from Plugins page).
 *
 * SAFE BY DEFAULT: preserves all data (tables, options, roles) so that
 * reinstalling or updating the plugin picks up where it left off.
 *
 * Data is ONLY deleted if the admin has explicitly enabled
 * "Delete all data on uninstall" in eProcurement > Settings.
 *
 * @package Eprocurement
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// ── Safety gate: only delete data if admin explicitly opted in ──────────
$delete_data = get_option( 'eprocurement_delete_data_on_uninstall', '0' );

if ( $delete_data !== '1' ) {
    // Data preserved — plugin can be reinstalled without loss.
    // Only clean up the transient cache so a fresh install fetches new data.
    delete_transient( 'eproc_github_latest_release' );
    return;
}

// ── Admin opted in: full cleanup ────────────────────────────────────────
global $wpdb;

$prefix = $wpdb->prefix . 'eproc_';

// Drop all plugin tables in correct order (respecting foreign key dependencies)
$tables = [
    'evaluation_scores',
    'evaluation_criteria',
    'submission_requirements',
    'message_attachments',
    'messages',
    'threads',
    'bid_submissions',
    'briefing_attendees',
    'downloads',
    'supporting_docs',
    'compliance_docs',
    'contact_persons',
    'bidder_profiles',
    'documents',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Remove all plugin options
$options = [
    'eprocurement_version',
    'eprocurement_cloud_provider',
    'eprocurement_cloud_credentials',
    'eprocurement_closed_bid_retention_days',
    'eprocurement_compliance_section_title',
    'eprocurement_frontend_page_slug',
    'eprocurement_frontend_page_id',
    'eprocurement_notification_settings',
    'eprocurement_smtp_settings',
    'eprocurement_category_briefing_register',
    'eprocurement_category_closing_register',
    'eprocurement_category_appointments',
    'eprocurement_departments',
    'eprocurement_external_db_settings',
    'eprocurement_cors_origins',
    'eprocurement_redirect_home',
    'eprocurement_initial_cleanup_done',
    'eprocurement_rewrite_version',
    'eprocurement_brand_name',
    'eprocurement_brand_url',
    'eprocurement_support_email',
    'eprocurement_brand_logo',
    'eprocurement_brand_tagline',
    'eprocurement_brand_colors',
    'eprocurement_login_title',
    'eprocurement_delete_data_on_uninstall',
    'eproc_audit_log', // Added in 2.14.0 — audit trail for bid backdating
    'eproc_activity_log', // Added in 2.15.0 — dashboard activity feed
    'eprocurement_inherit_theme_colors', // Added in 2.16.4 — theme color inheritance
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove custom roles
remove_role( 'eprocurement_scm_manager' );
remove_role( 'eprocurement_scm_official' );
remove_role( 'eprocurement_unit_manager' );
remove_role( 'eprocurement_subscriber' ); // Fix L-05: was 'eprocurement_bidder' (non-existent slug)

// Remove custom capabilities from admin and editor roles
$admin_role  = get_role( 'administrator' );
$editor_role = get_role( 'editor' );

$capabilities = [
    'eproc_manage_settings',
    'eproc_create_bids',
    'eproc_edit_bids',
    'eproc_publish_bids',
    'eproc_close_bids',
    'eproc_delete_bids',
    'eproc_upload_documents',
    'eproc_manage_contacts',
    'eproc_view_threads',
    'eproc_reply_threads',
    'eproc_view_bidders',
    'eproc_view_downloads',
    'eproc_manage_compliance',
    'eproc_view_dashboard',
    'eproc_send_queries',
];

foreach ( $capabilities as $cap ) {
    if ( $admin_role ) {
        $admin_role->remove_cap( $cap );
    }
    if ( $editor_role ) {
        $editor_role->remove_cap( $cap );
    }
}

// Clear scheduled cron events
wp_clear_scheduled_hook( 'eprocurement_daily_cleanup' );
wp_clear_scheduled_hook( 'eprocurement_hourly_reminder_check' );
wp_clear_scheduled_hook( 'eprocurement_weekly_digest' ); // Fix L-05: was missing — left orphaned cron firing into void

// Clean up user meta for bidder profiles
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_eproc_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Also clear last_login tracking meta added in 2.14.0
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'eproc_last_login'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
