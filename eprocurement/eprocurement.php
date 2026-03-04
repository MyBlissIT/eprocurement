<?php
/**
 * Plugin Name: eProcurement
 * Plugin URI:  https://www.myblisstech.com/eprocurement
 * Description: A mini-CRM WordPress plugin for procurement processes. Manages bid/tender notices, structured communication between procurement officials and prospective bidders, cloud-based document storage, and role-based access control.
 * Version:     2.10.1
 * Author:      MyBliss Tech
 * Author URI:  https://www.myblisstech.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: eprocurement
 * Domain Path: /languages
 * Network:     true
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants
 */
define( 'EPROC_VERSION', '2.10.1' );
define( 'EPROC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EPROC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EPROC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'EPROC_TABLE_PREFIX', 'eproc_' );
define( 'EPROC_GITHUB_REPO', 'MyBlissIT/eprocurement' );

/**
 * Autoloader for plugin classes.
 *
 * Maps class names to file paths following WordPress naming conventions:
 * - Eprocurement_Documents      => includes/class-documents.php
 * - Eprocurement_Google_Drive   => includes/storage/class-google-drive.php
 * - Eprocurement_Admin          => admin/class-admin.php
 * - Eprocurement_Public         => public/class-public.php
 */
spl_autoload_register( function ( string $class_name ): void {
    // Only autoload our classes
    if ( strpos( $class_name, 'Eprocurement_' ) !== 0 ) {
        return;
    }

    // Convert class name to file path
    $file_part = str_replace( 'Eprocurement_', '', $class_name );
    $file_part = strtolower( str_replace( '_', '-', $file_part ) );

    // Check storage subdirectory first
    $storage_classes = [ 'google-drive', 'onedrive', 'dropbox', 's3', 'local-storage' ];
    if ( in_array( $file_part, $storage_classes, true ) ) {
        $file = EPROC_PLUGIN_DIR . 'includes/storage/class-' . $file_part . '.php';
    } elseif ( $file_part === 'admin' ) {
        $file = EPROC_PLUGIN_DIR . 'admin/class-admin.php';
    } elseif ( $file_part === 'public' ) {
        $file = EPROC_PLUGIN_DIR . 'public/class-public.php';
    } elseif ( $file_part === 'frontend-admin' ) {
        $file = EPROC_PLUGIN_DIR . 'public/class-frontend-admin.php';
    } else {
        $file = EPROC_PLUGIN_DIR . 'includes/class-' . $file_part . '.php';
    }

    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

/**
 * Activation hook.
 *
 * Handles both single-site and network-wide (multisite) activation.
 *
 * @param bool $network_wide True if activated network-wide on multisite.
 */
function eprocurement_activate( $network_wide = false ): void {
    require_once EPROC_PLUGIN_DIR . 'includes/class-activator.php';

    if ( is_multisite() && $network_wide ) {
        // Activate on every existing site in the network.
        $sites = get_sites( [ 'number' => 0 ] );
        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );
            Eprocurement_Activator::activate();
            restore_current_blog();
        }
    } else {
        Eprocurement_Activator::activate();
    }
}
register_activation_hook( __FILE__, 'eprocurement_activate' );

/**
 * Auto-activate on new sites created after network-wide activation.
 *
 * When the plugin is network-activated and a new site is added to the
 * multisite network, this hook ensures the plugin's tables, roles, and
 * options are provisioned on the new site automatically.
 *
 * Priority 900 ensures WordPress core has finished setting up the site.
 */
add_action( 'wp_initialize_site', function ( WP_Site $new_site ): void {
    if ( is_plugin_active_for_network( EPROC_PLUGIN_BASENAME ) ) {
        switch_to_blog( $new_site->blog_id );
        require_once EPROC_PLUGIN_DIR . 'includes/class-activator.php';
        Eprocurement_Activator::activate();
        restore_current_blog();
    }
}, 900 );

/**
 * Deactivation hook.
 */
function eprocurement_deactivate(): void {
    require_once EPROC_PLUGIN_DIR . 'includes/class-deactivator.php';
    Eprocurement_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'eprocurement_deactivate' );

/**
 * Run database migrations when plugin version changes.
 */
function eprocurement_maybe_upgrade(): void {
    $installed_version = get_option( 'eprocurement_version', '1.0.0' );

    if ( version_compare( $installed_version, EPROC_VERSION, '>=' ) ) {
        return;
    }

    // Re-run dbDelta to add any new columns/tables.
    require_once EPROC_PLUGIN_DIR . 'includes/class-activator.php';
    Eprocurement_Activator::activate();

    // v1.1.0: Migrate 'published' status → 'open' and update ENUM.
    if ( version_compare( $installed_version, '1.1.0', '<' ) ) {
        global $wpdb;
        $table = $wpdb->prefix . EPROC_TABLE_PREFIX . 'documents';

        // Migrate any published rows before altering the ENUM.
        $wpdb->query( "UPDATE {$table} SET status = 'open' WHERE status = 'published'" ); // phpcs:ignore
        $wpdb->query( "ALTER TABLE {$table} MODIFY COLUMN status ENUM('draft','open','closed','cancelled','archived') NOT NULL DEFAULT 'draft'" ); // phpcs:ignore

        // Rename "Compliance Documents" → "SCM Documents" in saved option.
        $current_title = get_option( 'eprocurement_compliance_section_title', '' );
        if ( $current_title === 'Compliance Documents' ) {
            update_option( 'eprocurement_compliance_section_title', 'SCM Documents' );
        }
    }

    // v2.9.0: Add notify_replies column to bidder_profiles.
    if ( version_compare( $installed_version, '2.9.0', '<' ) ) {
        global $wpdb;
        $bp_table = $wpdb->prefix . EPROC_TABLE_PREFIX . 'bidder_profiles';
        $col_exists = $wpdb->get_var( "SHOW COLUMNS FROM {$bp_table} LIKE 'notify_replies'" ); // phpcs:ignore
        if ( ! $col_exists ) {
            $wpdb->query( "ALTER TABLE {$bp_table} ADD COLUMN notify_replies TINYINT(1) NOT NULL DEFAULT 1 AFTER verified" ); // phpcs:ignore
        }
    }

    update_option( 'eprocurement_version', EPROC_VERSION );
}

/**
 * Main plugin bootstrap.
 */
function eprocurement_init(): void {
    // Check for version upgrade.
    eprocurement_maybe_upgrade();

    // Load text domain
    load_plugin_textdomain( 'eprocurement', false, dirname( EPROC_PLUGIN_BASENAME ) . '/languages' );

    // Initialise core classes
    $roles          = new Eprocurement_Roles();
    $database       = new Eprocurement_Database();
    $documents      = new Eprocurement_Documents();
    $contacts       = new Eprocurement_Contact_Persons();
    $messaging      = new Eprocurement_Messaging();
    $bidder         = new Eprocurement_Bidder();
    $downloads      = new Eprocurement_Downloads();
    $notifications  = new Eprocurement_Notifications();
    $compliance     = new Eprocurement_Compliance_Docs();
    $rest_api       = new Eprocurement_Rest_Api();
    $access_control = new Eprocurement_Access_Control();
    $admin_rest_api = new Eprocurement_Admin_Rest_Api();

    // SMTP configuration
    $smtp = new Eprocurement_Smtp();

    // Self-update via GitHub Releases
    require_once EPROC_PLUGIN_DIR . 'includes/class-updater.php';
    $updater = new Eprocurement_Updater( EPROC_GITHUB_REPO );

    // Admin-only classes (wp-admin backend — Super Admin only after access control)
    if ( is_admin() ) {
        $admin = new Eprocurement_Admin();
    }

    // Frontend (public) classes
    $public_handler = new Eprocurement_Public();

    // Self-healing rewrite flush: runs once per version to ensure custom
    // rewrite rules are registered. Fixes the issue where activation flush
    // runs before init registers the rules.
    $flush_key = 'eprocurement_rewrite_version';
    if ( get_option( $flush_key ) !== EPROC_VERSION ) {
        add_action( 'init', function () use ( $flush_key ) {
            flush_rewrite_rules();
            update_option( $flush_key, EPROC_VERSION );
        }, 99 ); // Priority 99: run after our rules are registered at default priority
    }

    // Hook cron callback (scheduling happens on activation only)
    add_action( 'eprocurement_daily_cleanup', [ $documents, 'auto_close_expired_bids' ] );
    add_action( 'eprocurement_daily_cleanup', [ $documents, 'archive_expired_closed_bids' ] );

    // Also run auto-close on every page load (lightweight UPDATE query)
    $documents->auto_close_expired_bids();
}
add_action( 'plugins_loaded', 'eprocurement_init' );

/**
 * Add plugin settings link on plugins page.
 */
function eprocurement_settings_link( array $links ): array {
    if ( is_super_admin() ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=eprocurement-settings' ) . '">'
                       . esc_html__( 'Settings', 'eprocurement' ) . '</a>';
        array_unshift( $links, $settings_link );
    }
    return $links;
}
add_filter( 'plugin_action_links_' . EPROC_PLUGIN_BASENAME, 'eprocurement_settings_link' );

/**
 * Fallback: Route mail through Mailpit in dev environments
 * when no SMTP settings are configured in the plugin.
 * Once SMTP is configured via Settings, the Eprocurement_Smtp class handles routing.
 */
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! get_option( 'eprocurement_smtp_settings' ) ) {
    add_filter( 'wp_mail_from', function ( $from ) {
        return strpos( $from, '@localhost' ) !== false ? 'noreply@eprocurement.test' : $from;
    } );
    add_filter( 'wp_mail_from_name', function ( $name ) {
        return $name === 'WordPress' ? 'eProcurement Dev' : $name;
    } );

    add_action( 'phpmailer_init', function ( $phpmailer ) {
        $phpmailer->isSMTP();
        $phpmailer->Host        = 'mailpit';
        $phpmailer->Port        = 1025;
        $phpmailer->SMTPAuth    = false;
        $phpmailer->SMTPAutoTLS = false;
    } );
}
