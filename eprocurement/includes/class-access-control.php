<?php
/**
 * Access control — restrict wp-admin and wp-login.php to Super Admin only.
 *
 * Non-Super-Admin staff are redirected to /tenders/manage/.
 * Bidders are redirected to /tenders/my-account/.
 * AJAX, REST API, cron, and POST requests to wp-login.php (auth) are allowed.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Access_Control {

    public function __construct() {
        add_action( 'admin_init', [ $this, 'restrict_wp_admin' ] );
        add_filter( 'show_admin_bar', [ $this, 'hide_admin_bar' ] );
        add_filter( 'login_redirect', [ $this, 'role_based_login_redirect' ], 10, 3 );

        // Security hardening (eProcurement-specific — general branding/security handled by MU-plugin).
        $this->apply_security_hardening();
    }

    /**
     * Apply eProcurement-specific security hardening.
     * General security (XMLRPC, file edit, wp_generator) handled by MU-plugin.
     */
    private function apply_security_hardening(): void {
        // Disable user enumeration via REST API for non-admins.
        add_filter( 'rest_endpoints', function ( $endpoints ) {
            if ( ! is_super_admin() ) {
                unset( $endpoints['/wp/v2/users'] );
                unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
            }
            return $endpoints;
        } );

        // Disable user enumeration via ?author=N.
        add_action( 'template_redirect', function () {
            if ( ! is_super_admin() && isset( $_GET['author'] ) ) {
                $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
                wp_safe_redirect( home_url( "/{$slug}/" ) );
                exit;
            }
        } );

        // Add security headers on frontend.
        add_action( 'send_headers', function () {
            if ( is_admin() ) {
                return;
            }
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: SAMEORIGIN' );
            header( 'Referrer-Policy: strict-origin-when-cross-origin' );
            header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
        } );

        // Disable application passwords for non-Super-Admin.
        add_filter( 'wp_is_application_passwords_available_for_user', function ( $available, $user ) {
            return is_super_admin( $user->ID ) ? $available : false;
        }, 10, 2 );
    }

    /**
     * Redirect non-Super-Admin users away from wp-admin.
     * Allow AJAX, cron, and REST API requests.
     */
    public function restrict_wp_admin(): void {
        if ( is_super_admin() ) {
            return;
        }

        // Allow AJAX requests (admin-ajax.php is needed by the plugin)
        if ( wp_doing_ajax() ) {
            return;
        }

        // Allow cron
        if ( wp_doing_cron() ) {
            return;
        }

        // Allow REST API requests routed through admin
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return;
        }

        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

        if ( Eprocurement_Roles::is_staff() ) {
            wp_safe_redirect( home_url( "/{$slug}/manage/" ) );
        } elseif ( Eprocurement_Roles::is_bidder() ) {
            wp_safe_redirect( home_url( "/{$slug}/my-account/" ) );
        } else {
            wp_safe_redirect( home_url( "/{$slug}/" ) );
        }
        exit;
    }

    /**
     * Hide the WordPress admin bar for non-Super-Admin users.
     */
    public function hide_admin_bar( bool $show ): bool {
        if ( is_super_admin() ) {
            return $show;
        }
        return false;
    }

    /**
     * Route users to the correct frontend page after login.
     *
     * @param string   $redirect_to Default redirect URL.
     * @param string   $requested   Requested redirect URL.
     * @param \WP_User $user        The logged-in user.
     * @return string Redirect URL.
     */
    public function role_based_login_redirect( string $redirect_to, string $requested, $user ): string {
        if ( is_wp_error( $user ) || ! ( $user instanceof \WP_User ) ) {
            return $redirect_to;
        }

        // Super Admin goes wherever they requested (usually wp-admin)
        if ( is_super_admin( $user->ID ) ) {
            return $redirect_to;
        }

        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

        if ( Eprocurement_Roles::is_staff( $user->ID ) ) {
            return home_url( "/{$slug}/manage/" );
        }

        if ( Eprocurement_Roles::is_bidder( $user->ID ) ) {
            return home_url( "/{$slug}/my-account/" );
        }

        return home_url( "/{$slug}/" );
    }

}
