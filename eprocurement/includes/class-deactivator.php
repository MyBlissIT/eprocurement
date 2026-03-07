<?php
/**
 * Plugin deactivation handler.
 *
 * Cleans up scheduled events. Does NOT remove data — that happens on uninstall.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Deactivator {

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate(): void {
        // Remove scheduled cron events
        wp_clear_scheduled_hook( 'eprocurement_daily_cleanup' );
        wp_clear_scheduled_hook( 'eprocurement_weekly_digest' );

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
