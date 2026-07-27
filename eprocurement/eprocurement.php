<?php
/**
 * Plugin Name: eProcurement
 * Plugin URI:  https://www.myblisstech.com/eprocurement
 * Description: A mini-CRM WordPress plugin for procurement processes. Manages bid/tender notices, structured communication between procurement officials and prospective bidders, cloud-based document storage, and role-based access control.
 * Version:     2.18.0
 * Author:      MyBliss Tech
 * Author URI:  https://www.myblisstech.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: eprocurement
 * Domain Path: /languages
 * Network:     true
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants
 */
define( 'EPROC_VERSION', '2.18.0' );
define( 'EPROC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EPROC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EPROC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'EPROC_TABLE_PREFIX', 'eproc_' );
define( 'EPROC_GITHUB_REPO', 'MyBlissIT/eprocurement' );

// Load shared helper functions (DRY utilities).
require_once EPROC_PLUGIN_DIR . 'includes/helpers.php';

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

    // v2.12.0: Add bid submission columns to documents table.
    if ( version_compare( $installed_version, '2.12.0', '<' ) ) {
        global $wpdb;
        $doc_table = $wpdb->prefix . EPROC_TABLE_PREFIX . 'documents';

        $col1 = $wpdb->get_var( "SHOW COLUMNS FROM {$doc_table} LIKE 'allow_late_submissions'" ); // phpcs:ignore
        if ( ! $col1 ) {
            $wpdb->query( "ALTER TABLE {$doc_table} ADD COLUMN allow_late_submissions TINYINT(1) NOT NULL DEFAULT 0 AFTER closing_date" ); // phpcs:ignore
        }

        $col2 = $wpdb->get_var( "SHOW COLUMNS FROM {$doc_table} LIKE 'briefing_compulsory'" ); // phpcs:ignore
        if ( ! $col2 ) {
            $wpdb->query( "ALTER TABLE {$doc_table} ADD COLUMN briefing_compulsory TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_late_submissions" ); // phpcs:ignore
        }
    }

    // v2.12.2: Add accept_online_submissions column (default OFF — submissions are outside the system)
    if ( version_compare( $installed_version, '2.12.2', '<' ) ) {
        global $wpdb;
        $doc_table = $wpdb->prefix . EPROC_TABLE_PREFIX . 'documents';

        $col = $wpdb->get_var( "SHOW COLUMNS FROM {$doc_table} LIKE 'accept_online_submissions'" ); // phpcs:ignore
        if ( ! $col ) {
            $wpdb->query( "ALTER TABLE {$doc_table} ADD COLUMN accept_online_submissions TINYINT(1) NOT NULL DEFAULT 0 AFTER closing_date" ); // phpcs:ignore
        }
    }

    // v2.14.0: Add award + Q&A deadline columns for premium evaluation features.
    if ( version_compare( $installed_version, '2.14.0', '<' ) ) {
        global $wpdb;
        $doc_table = $wpdb->prefix . EPROC_TABLE_PREFIX . 'documents';

        $cols_to_add = [
            'qa_deadline'           => "ADD COLUMN qa_deadline DATETIME DEFAULT NULL AFTER closing_date",
            'awarded_to_user_id'    => "ADD COLUMN awarded_to_user_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER updated_at",
            'award_amount'          => "ADD COLUMN award_amount DECIMAL(15,2) DEFAULT NULL AFTER awarded_to_user_id",
            'award_date'            => "ADD COLUMN award_date DATETIME DEFAULT NULL AFTER award_amount",
            'award_notes'           => "ADD COLUMN award_notes TEXT DEFAULT NULL AFTER award_date",
            'reminder_48h_sent'     => "ADD COLUMN reminder_48h_sent TINYINT(1) NOT NULL DEFAULT 0 AFTER award_notes",
            'reminder_24h_sent'     => "ADD COLUMN reminder_24h_sent TINYINT(1) NOT NULL DEFAULT 0 AFTER reminder_48h_sent",
        ];

        foreach ( $cols_to_add as $col_name => $alter_sql ) {
            $exists = $wpdb->get_var( "SHOW COLUMNS FROM {$doc_table} LIKE '{$col_name}'" ); // phpcs:ignore
            if ( ! $exists ) {
                $wpdb->query( "ALTER TABLE {$doc_table} {$alter_sql}" ); // phpcs:ignore
            }
        }

        // Create evaluation + submission_requirements tables (also created
        // on fresh activation, but existing sites need them too).
        require_once EPROC_PLUGIN_DIR . 'includes/class-activator.php';
        Eprocurement_Activator::create_tables();
    }

    // v2.16.0: Add submission_mode column for per-document uploads.
    // This was previously inside the v2.14.0 block — a bug that meant sites
    // already at v2.14.0 would never get the column. Now gated independently.
    if ( version_compare( $installed_version, '2.16.0', '<' ) ) {
        global $wpdb;
        $doc_table = $wpdb->prefix . EPROC_TABLE_PREFIX . 'documents';
        $sub_mode_col = $wpdb->get_var( "SHOW COLUMNS FROM {$doc_table} LIKE 'submission_mode'" ); // phpcs:ignore
        if ( ! $sub_mode_col ) {
            $wpdb->query( "ALTER TABLE {$doc_table} ADD COLUMN submission_mode ENUM('single','per_document') NOT NULL DEFAULT 'single' AFTER accept_online_submissions" ); // phpcs:ignore
        }

        // Ensure evaluation + submission_requirements tables exist
        // (idempotent — dbDelta won't recreate existing tables).
        require_once EPROC_PLUGIN_DIR . 'includes/class-activator.php';
        Eprocurement_Activator::create_tables();
    }

    // v2.17.0: Threads ENUM expansion (add 'cancelled' for retract feature).
    // Also re-runs create_tables() to add any columns still missing on
    // sites that skipped intermediate versions (audit fix A2 + A12).
    if ( version_compare( $installed_version, '2.17.0', '<' ) ) {
        global $wpdb;
        $threads_table = $wpdb->prefix . EPROC_TABLE_PREFIX . 'threads';

        // ALTER the threads.status ENUM to include 'cancelled'.
        $wpdb->query(
            "ALTER TABLE {$threads_table} MODIFY COLUMN status ENUM('open','resolved','closed','cancelled') NOT NULL DEFAULT 'open'"
        ); // phpcs:ignore

        // Re-run create_tables() to backfill any columns that prior
        // migrations missed on this site (defensive — dbDelta is idempotent).
        require_once EPROC_PLUGIN_DIR . 'includes/class-activator.php';
        Eprocurement_Activator::create_tables();
    }

    // v2.18.0: Audit log migration to dedicated DB table (audit fix A10).
    // Creates the new audit_log table via create_tables(), then migrates
    // existing entries from the old wp_options arrays into the table.
    // Also handles credential re-encryption (CBC → GCM) for cloud creds —
    // see class-storage-interface.php for the format detection logic.
    if ( version_compare( $installed_version, '2.18.0', '<' ) ) {
        require_once EPROC_PLUGIN_DIR . 'includes/class-activator.php';
        Eprocurement_Activator::create_tables();

        require_once EPROC_PLUGIN_DIR . 'includes/class-activity-log.php';
        Eprocurement_Activity_Log::migrate_from_options();

        // Trigger credential re-encryption on next read. The storage
        // interface decrypt() will detect old CBC format, re-encrypt with
        // GCM, and save_credentials() will persist the new format.
        // We do this lazily on first access rather than eagerly here, to
        // avoid double-decrypting during the upgrade request.
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
    $bid_submissions = new Eprocurement_Bid_Submissions();
    $evaluation     = new Eprocurement_Evaluation();
    $sub_reqs       = new Eprocurement_Submission_Requirements();
    $rest_api        = new Eprocurement_Rest_Api();
    $access_control  = new Eprocurement_Access_Control();
    $admin_rest_api  = new Eprocurement_Admin_Rest_Api();

    // SMTP configuration
    $smtp = new Eprocurement_Smtp();

    // Activity log — register hooks for key procurement actions.
    Eprocurement_Activity_Log::register_hooks();

    // Two-Factor Authentication for staff users.
    $two_factor = new Eprocurement_Two_Factor();

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

    // Hourly reminder check — sends 48h and 24h closing reminders to bidders.
    add_action( 'eprocurement_hourly_reminder_check', function (): void {
        $documents_model = new Eprocurement_Documents();
        $notifications   = new Eprocurement_Notifications();

        // 48-hour reminder.
        foreach ( $documents_model->get_tenders_needing_reminder( 48, 'reminder_48h_sent' ) as $tender ) {
            $notifications->send_closing_reminder( (int) $tender->id, 48 );
            $documents_model->mark_reminder_sent( (int) $tender->id, 'reminder_48h_sent' );
        }

        // 24-hour reminder.
        foreach ( $documents_model->get_tenders_needing_reminder( 24, 'reminder_24h_sent' ) as $tender ) {
            $notifications->send_closing_reminder( (int) $tender->id, 24 );
            $documents_model->mark_reminder_sent( (int) $tender->id, 'reminder_24h_sent' );
        }
    } );

    // Auto-close expired bids, throttled to once per 5 minutes (fix M-07/P-01).
    // The previous implementation ran a write UPDATE query on every page load,
    // which caused lock contention under load and added latency to every
    // request (frontend, REST, AJAX, cron). The transient lock limits the
    // query to at most once per 5 minutes — still responsive enough for
    // closing bids at the right moment, without hammering the DB.
    if ( ! get_transient( 'eproc_last_auto_close' ) ) {
        $documents->auto_close_expired_bids();
        set_transient( 'eproc_last_auto_close', 1, 5 * MINUTE_IN_SECONDS );
    }

    // Exclude eProcurement pages from Bluehost Endurance Page Cache.
    // The file-based cache serves stale HTML for dynamic routes like
    // /tenders/register/, /tenders/bid/5/, etc.
    add_filter( 'epc_exempt_uri_contains', function ( array $exempt ): array {
        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        $exempt[] = $slug;
        return $exempt;
    } );
}
add_action( 'plugins_loaded', 'eprocurement_init' );

/**
 * Track each user's last login timestamp.
 *
 * Stored in usermeta as 'eproc_last_login' (MySQL datetime).
 * Surfaced on the bidder dashboard profile card as "Last Login: 2 hours ago".
 *
 * @since 2.14.0
 * @param string  $user_login Username being logged in.
 * @param WP_User $user       User object.
 */
function eprocurement_track_last_login( string $user_login, \WP_User $user ): void {
    update_user_meta( $user->ID, 'eproc_last_login', current_time( 'mysql' ) );
}
add_action( 'wp_login', 'eprocurement_track_last_login', 10, 2 );

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
 *
 * Gated on EPROC_DEV_MODE (or wp_get_environment_type() === 'local') so that
 * production sites that leave WP_DEBUG enabled for troubleshooting do not
 * silently route all mail into a void (security/performance fix H-07).
 */
if ( ! get_option( 'eprocurement_smtp_settings' ) ) {
    $is_dev = defined( 'EPROC_DEV_MODE' ) && EPROC_DEV_MODE
        || ( function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'local' );

    if ( $is_dev ) {
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
}
