<?php
/**
 * Shared helper functions for the eProcurement plugin.
 *
 * These functions consolidate patterns that were previously duplicated
 * across the codebase (fix DRY violations). They are loaded by the
 * bootstrap on `plugins_loaded`.
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'eprocurement_get_slug' ) ) {
    /**
     * Get the frontend page slug, cached per-request.
     *
     * Previously this was called 40+ times across the codebase via
     * `get_option( 'eprocurement_frontend_page_slug', 'tenders' )`.
     * The helper caches the result in a static variable for the request.
     *
     * @since 2.14.0
     * @return string Frontend slug (default 'tenders').
     */
    function eprocurement_get_slug(): string {
        static $slug = null;
        if ( $slug === null ) {
            $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
            if ( ! $slug ) {
                $slug = 'tenders';
            }
        }
        return $slug;
    }
}

if ( ! function_exists( 'eprocurement_generate_unique_username' ) ) {
    /**
     * Generate a unique WordPress username from a base string.
     *
     * Previously duplicated in class-bidder.php, class-external-db.php,
     * class-admin.php, and class-admin-rest-api.php.
     *
     * @since 2.14.0
     * @param string $base Suggested username (e.g., 'first.last' or email local-part).
     * @return string Sanitised, unique username.
     */
    function eprocurement_generate_unique_username( string $base ): string {
        $username = sanitize_user( strtolower( $base ), true );
        $original = $username;
        $i        = 1;

        while ( username_exists( $username ) ) {
            $username = $original . $i;
            $i++;
        }

        return $username;
    }
}

if ( ! function_exists( 'eprocurement_create_user_from_email' ) ) {
    /**
     * Create a WordPress user from an email address with a random password.
     *
     * Used by the contact-person save flow and external DB sync. The user
     * receives the standard "Set your password" email from WordPress.
     *
     * @since 2.14.0
     * @param string $email    User email.
     * @param string $name     Display name.
     * @param string $role     WordPress role slug.
     * @return int|\WP_Error User ID on success, WP_Error on failure.
     */
    function eprocurement_create_user_from_email( string $email, string $name, string $role ): int|\WP_Error {
        $existing = get_user_by( 'email', $email );
        if ( $existing ) {
            return $existing->ID;
        }

        $username = eprocurement_generate_unique_username( explode( '@', $email )[0] );

        $user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password( 16 ),
            'display_name' => $name ?: $username,
            'first_name'   => $name ? explode( ' ', $name )[0] : '',
            'role'         => $role,
        ] );

        if ( ! is_wp_error( $user_id ) ) {
            wp_new_user_notification( $user_id, null, 'user' );
        }

        return $user_id;
    }
}

if ( ! function_exists( 'eprocurement_format_bytes' ) ) {
    /**
     * Format a byte count as a human-readable string.
     *
     * @since 2.14.0
     * @param int $bytes    Size in bytes.
     * @param int $decimals Decimal places (default 1).
     * @return string Formatted size (e.g., "1.5 MB").
     */
    function eprocurement_format_bytes( int $bytes, int $decimals = 1 ): string {
        if ( $bytes <= 0 ) {
            return '0 B';
        }
        $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
        $factor = (int) floor( log( $bytes, 1024 ) );
        $factor = min( $factor, count( $units ) - 1 );
        return sprintf( "%.{$decimals}f %s", $bytes / pow( 1024, $factor ), $units[ $factor ] );
    }
}

if ( ! function_exists( 'eprocurement_parse_date_input' ) ) {
    /**
     * Parse a date input string into MySQL datetime format.
     *
     * Accepts `dd/mm/yyyy HH:mm` (admin UI format) or `Y-m-d\TH:i`
     * (HTML datetime-local). Returns null on parse failure.
     *
     * @since 2.14.0
     * @param string $raw Raw date input.
     * @return string|null MySQL datetime (Y-m-d H:i:s) or null.
     */
    function eprocurement_parse_date_input( string $raw ): ?string {
        $raw = sanitize_text_field( $raw );
        if ( $raw === '' ) {
            return null;
        }

        $dt = \DateTime::createFromFormat( 'd/m/Y H:i', $raw );
        if ( $dt ) {
            return $dt->format( 'Y-m-d H:i:s' );
        }

        $dt = \DateTime::createFromFormat( 'Y-m-d\TH:i', $raw );
        if ( $dt ) {
            return $dt->format( 'Y-m-d H:i:s' );
        }

        return null;
    }
}

if ( ! function_exists( 'eprocurement_load_template' ) ) {
    /**
     * Load a template, allowing theme override via locate_template().
     *
     * Themes can override plugin templates by placing a file at
     * `/wp-content/themes/{theme}/eprocurement/{template_name}`.
     * Falls back to the plugin's bundled template.
     *
     * @since 2.14.0
     * @param string $template_name Relative path inside templates/ (e.g., 'email/briefing-invite.php').
     * @param array  $vars          Variables to extract into the template scope.
     * @return void Output is echoed.
     */
    function eprocurement_load_template( string $template_name, array $vars = [] ): void {
        // Allow themes to override.
        $located = locate_template( 'eprocurement/' . $template_name );

        if ( ! $located ) {
            $plugin_template = EPROC_PLUGIN_DIR . 'templates/' . $template_name;
            if ( file_exists( $plugin_template ) ) {
                $located = $plugin_template;
            }
        }

        if ( ! $located ) {
            return;
        }

        if ( ! empty( $vars ) ) {
            // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            extract( $vars, EXTR_SKIP );
        }

        include $located;
    }
}
