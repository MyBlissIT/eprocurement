<?php
/**
 * Plugin activation handler.
 *
 * Creates database tables, registers roles, and sets default options.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Activator {

    /**
     * Run on plugin activation.
     */
    public static function activate(): void {
        self::create_tables();
        self::create_roles();
        self::set_default_options();
        self::create_frontend_page();
        self::clean_default_wp_content();
        self::install_mu_plugin();

        // Store current version
        update_option( 'eprocurement_version', EPROC_VERSION );

        // Schedule daily cron for closed bid archival
        if ( ! wp_next_scheduled( 'eprocurement_daily_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'eprocurement_daily_cleanup' );
        }

        // Flush rewrite rules for any custom endpoints
        flush_rewrite_rules();
    }

    /**
     * Create all 9 custom database tables via dbDelta.
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix . EPROC_TABLE_PREFIX;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Documents (core bid/tender records)
        $sql = "CREATE TABLE {$prefix}documents (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            bid_number VARCHAR(100) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT,
            status ENUM('draft','open','closed','cancelled','archived') NOT NULL DEFAULT 'draft',
            category ENUM('bid','briefing_register','closing_register','appointments') NOT NULL DEFAULT 'bid',
            scm_contact_id BIGINT(20) UNSIGNED DEFAULT NULL,
            technical_contact_id BIGINT(20) UNSIGNED DEFAULT NULL,
            opening_date DATETIME DEFAULT NULL,
            briefing_date DATETIME DEFAULT NULL,
            closing_date DATETIME DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY bid_number (bid_number),
            KEY status (status),
            KEY category (category),
            KEY created_by (created_by),
            KEY closing_date (closing_date)
        ) {$charset_collate};";
        dbDelta( $sql );

        // 2. Contact Persons
        $sql = "CREATE TABLE {$prefix}contact_persons (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            type ENUM('scm','technical') NOT NULL DEFAULT 'scm',
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            email VARCHAR(255) NOT NULL,
            department VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type)
        ) {$charset_collate};";
        dbDelta( $sql );

        // 3. Bid Documents (cloud-stored files per bid)
        $sql = "CREATE TABLE {$prefix}supporting_docs (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id BIGINT(20) UNSIGNED NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            file_type VARCHAR(100) NOT NULL DEFAULT '',
            cloud_provider VARCHAR(50) NOT NULL DEFAULT '',
            cloud_key VARCHAR(500) NOT NULL DEFAULT '',
            cloud_url VARCHAR(500) NOT NULL DEFAULT '',
            label VARCHAR(255) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            uploaded_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY document_id (document_id),
            KEY uploaded_by (uploaded_by)
        ) {$charset_collate};";
        dbDelta( $sql );

        // 4. SCM Documents (static library, cloud-stored)
        $sql = "CREATE TABLE {$prefix}compliance_docs (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            file_name VARCHAR(255) NOT NULL,
            file_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            file_type VARCHAR(100) NOT NULL DEFAULT '',
            cloud_provider VARCHAR(50) NOT NULL DEFAULT '',
            cloud_key VARCHAR(500) NOT NULL DEFAULT '',
            cloud_url VARCHAR(500) NOT NULL DEFAULT '',
            label VARCHAR(255) DEFAULT NULL,
            description TEXT,
            sort_order INT NOT NULL DEFAULT 0,
            uploaded_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";
        dbDelta( $sql );

        // 5. Threads (query/conversation threads per bid)
        $sql = "CREATE TABLE {$prefix}threads (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id BIGINT(20) UNSIGNED NOT NULL,
            bidder_id BIGINT(20) UNSIGNED NOT NULL,
            contact_id BIGINT(20) UNSIGNED NOT NULL,
            subject VARCHAR(255) NOT NULL,
            visibility ENUM('private','public') NOT NULL DEFAULT 'private',
            status ENUM('open','resolved','closed') NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY document_id (document_id),
            KEY bidder_id (bidder_id),
            KEY contact_id (contact_id),
            KEY visibility (visibility),
            KEY status (status)
        ) {$charset_collate};";
        dbDelta( $sql );

        // 6. Messages (individual messages within a thread)
        $sql = "CREATE TABLE {$prefix}messages (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            thread_id BIGINT(20) UNSIGNED NOT NULL,
            sender_id BIGINT(20) UNSIGNED NOT NULL,
            message LONGTEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY thread_id (thread_id),
            KEY sender_id (sender_id),
            KEY is_read (is_read)
        ) {$charset_collate};";
        dbDelta( $sql );

        // 7. Message Attachments (cloud-stored, max 5MB)
        $sql = "CREATE TABLE {$prefix}message_attachments (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id BIGINT(20) UNSIGNED NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_size BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            file_type VARCHAR(100) NOT NULL DEFAULT '',
            cloud_provider VARCHAR(50) NOT NULL DEFAULT '',
            cloud_key VARCHAR(500) NOT NULL DEFAULT '',
            cloud_url VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY message_id (message_id)
        ) {$charset_collate};";
        dbDelta( $sql );

        // 8. Downloads (audit log)
        $sql = "CREATE TABLE {$prefix}downloads (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            document_id BIGINT(20) UNSIGNED NOT NULL,
            supporting_doc_id BIGINT(20) UNSIGNED DEFAULT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            user_agent VARCHAR(255) NOT NULL DEFAULT '',
            downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY document_id (document_id),
            KEY supporting_doc_id (supporting_doc_id),
            KEY user_id (user_id),
            KEY downloaded_at (downloaded_at)
        ) {$charset_collate};";
        dbDelta( $sql );

        // 9. Bidder Profiles (extended profile for Subscriber/Bidder accounts)
        $sql = "CREATE TABLE {$prefix}bidder_profiles (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            company_name VARCHAR(255) NOT NULL DEFAULT '',
            company_reg VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            verified TINYINT(1) NOT NULL DEFAULT 0,
            notify_replies TINYINT(1) NOT NULL DEFAULT 1,
            verification_token VARCHAR(255) DEFAULT NULL,
            token_expires_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            KEY verified (verified)
        ) {$charset_collate};";
        dbDelta( $sql );
    }

    /**
     * Create custom roles and assign capabilities.
     */
    private static function create_roles(): void {
        require_once EPROC_PLUGIN_DIR . 'includes/class-roles.php';
        Eprocurement_Roles::create_roles();
    }

    /**
     * Create the frontend page with the [eprocurement] shortcode if it doesn't exist.
     */
    private static function create_frontend_page(): void {
        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

        // Check if a page with this slug already exists
        $existing = get_page_by_path( $slug );
        if ( $existing ) {
            return;
        }

        $page_id = wp_insert_post( [
            'post_title'   => __( 'Tenders', 'eprocurement' ),
            'post_name'    => $slug,
            'post_content' => '[eprocurement]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ] );

        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'eprocurement_frontend_page_id', $page_id );
        }
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options(): void {
        add_option( 'eprocurement_cloud_provider', '' );
        add_option( 'eprocurement_cloud_credentials', '' );
        add_option( 'eprocurement_closed_bid_retention_days', '' ); // blank = keep forever
        add_option( 'eprocurement_compliance_section_title', 'SCM Documents' );
        add_option( 'eprocurement_frontend_page_slug', 'tenders' );
        add_option( 'eprocurement_category_briefing_register', '0' );
        add_option( 'eprocurement_category_closing_register', '0' );
        add_option( 'eprocurement_category_appointments', '0' );
        add_option( 'eprocurement_departments', wp_json_encode( [
            'Supply Chain Management',
            'Finance',
            'Information Technology',
            'Human Resources',
            'Legal',
            'Operations',
        ] ) );
        add_option( 'eprocurement_notification_settings', wp_json_encode( [
            'new_bid_notify_bidders'  => false,
            'query_notify_contact'    => false,
            'reply_notify_bidder'     => false,
            'status_change_notify'    => false,
        ] ) );

        // SMTP, External DB, and CORS — empty by default
        add_option( 'eprocurement_smtp_settings', '' );
        add_option( 'eprocurement_external_db_settings', '' );
        add_option( 'eprocurement_cors_origins', '' );
    }

    /**
     * Clean default WordPress content on first activation.
     *
     * Removes "Hello World" post, "Sample Page", default widgets,
     * and configures the site for eProcurement. Does NOT set the
     * tenders page as the static front page (causes 301 redirects
     * that break sub-page routing). Instead, a template_redirect
     * hook in class-public.php handles the home → tenders redirect.
     */
    private static function clean_default_wp_content(): void {
        // Only run once (first activation)
        if ( get_option( 'eprocurement_initial_cleanup_done' ) ) {
            return;
        }

        // Delete default "Hello world!" post
        $hello_world = get_page_by_path( 'hello-world', OBJECT, 'post' );
        if ( $hello_world ) {
            wp_delete_post( $hello_world->ID, true );
        }

        // Delete default "Sample Page"
        $sample_page = get_page_by_path( 'sample-page' );
        if ( $sample_page ) {
            wp_delete_post( $sample_page->ID, true );
        }

        // Delete any other default posts
        $default_posts = get_posts( [
            'post_type'   => 'post',
            'post_status' => 'any',
            'numberposts' => -1,
        ] );
        foreach ( $default_posts as $post ) {
            wp_delete_post( $post->ID, true );
        }

        // Enable the home-to-tenders redirect (handled in class-public.php)
        update_option( 'eprocurement_redirect_home', true );

        // Clean sidebar widgets — remove all active widget areas
        $sidebars = get_option( 'sidebars_widgets', [] );
        if ( is_array( $sidebars ) ) {
            foreach ( $sidebars as $key => $val ) {
                if ( $key !== 'wp_inactive_widgets' && $key !== 'array_version' ) {
                    $sidebars[ $key ] = [];
                }
            }
            update_option( 'sidebars_widgets', $sidebars );
        }

        // Remove nav menu locations
        $locations = get_theme_mod( 'nav_menu_locations' );
        if ( is_array( $locations ) ) {
            set_theme_mod( 'nav_menu_locations', [] );
        }

        // Set site title/description for eProcurement
        update_option( 'blogname', 'eProcurement' );
        update_option( 'blogdescription', '' );

        // Disable comments site-wide
        update_option( 'default_comment_status', 'closed' );
        update_option( 'default_ping_status', 'closed' );

        update_option( 'eprocurement_initial_cleanup_done', true );
    }

    /**
     * Install the MU-plugin from the bundled copy.
     *
     * Copies sme-admin-customizations.php and sme-assets/ from the
     * plugin's bundled-mu/ directory into wp-content/mu-plugins/.
     * Skips silently if the source files are missing or the target
     * already exists and is up-to-date.
     */
    private static function install_mu_plugin(): void {
        $source_dir = EPROC_PLUGIN_DIR . 'bundled-mu/';
        $target_dir = WPMU_PLUGIN_DIR . '/';

        // Ensure source exists
        if ( ! is_dir( $source_dir ) ) {
            return;
        }

        // Create mu-plugins directory if it doesn't exist
        if ( ! is_dir( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
        }

        // Copy main MU-plugin file (overwrite to keep updated)
        $source_file = $source_dir . 'sme-admin-customizations.php';
        $target_file = $target_dir . 'sme-admin-customizations.php';

        if ( file_exists( $source_file ) ) {
            // Only copy if source is newer or target doesn't exist
            if ( ! file_exists( $target_file ) || filemtime( $source_file ) > filemtime( $target_file ) ) {
                @copy( $source_file, $target_file ); // phpcs:ignore
            }
        }

        // Copy sme-assets/ directory
        $source_assets = $source_dir . 'sme-assets/';
        $target_assets = $target_dir . 'sme-assets/';

        if ( is_dir( $source_assets ) ) {
            if ( ! is_dir( $target_assets ) ) {
                wp_mkdir_p( $target_assets );
            }

            $files = scandir( $source_assets );
            if ( $files ) {
                foreach ( $files as $file ) {
                    if ( $file === '.' || $file === '..' ) {
                        continue;
                    }
                    $src = $source_assets . $file;
                    $dst = $target_assets . $file;
                    if ( is_file( $src ) && ( ! file_exists( $dst ) || filemtime( $src ) > filemtime( $dst ) ) ) {
                        @copy( $src, $dst ); // phpcs:ignore
                    }
                }
            }
        }
    }
}
