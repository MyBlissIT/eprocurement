<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Cleans up all database tables, options, and custom roles
 * created by the eProcurement plugin.
 *
 * @package Eprocurement
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$prefix = $wpdb->prefix . 'eproc_';

// Drop all plugin tables in correct order (respecting foreign key dependencies)
$tables = [
    'message_attachments',
    'messages',
    'threads',
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
    'eprocurement_notification_settings',
    'eprocurement_smtp_configured',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove custom roles
remove_role( 'eprocurement_scm_manager' );
remove_role( 'eprocurement_scm_official' );
remove_role( 'eprocurement_unit_manager' );
remove_role( 'eprocurement_subscriber' );

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
];

foreach ( $capabilities as $cap ) {
    if ( $admin_role ) {
        $admin_role->remove_cap( $cap );
    }
    if ( $editor_role ) {
        $editor_role->remove_cap( $cap );
    }
}

// Clear scheduled cron event
wp_clear_scheduled_hook( 'eprocurement_daily_cleanup' );

// Clean up user meta for bidder profiles
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'eproc_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
