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
        // Hide admin REST API routes and WP pages endpoint from unauthenticated users.
        add_filter( 'rest_endpoints', function ( $endpoints ) {
            if ( ! is_super_admin() ) {
                unset( $endpoints['/wp/v2/users'] );
                unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
            }

            // Hide admin endpoints from unauthenticated users.
            if ( ! is_user_logged_in() ) {
                foreach ( $endpoints as $route => $data ) {
                    if ( strpos( $route, '/eprocurement/v1/admin' ) !== false ) {
                        unset( $endpoints[ $route ] );
                    }
                }
                // Hide WP pages endpoint (leaks full rendered HTML).
                unset( $endpoints['/wp/v2/pages'] );
                unset( $endpoints['/wp/v2/pages/(?P<id>[\d]+)'] );
            }

            return $endpoints;
        } );

        // Disable user enumeration via ?author=N.
        // Audit fix A4: block for ALL users (including Super Admin) and
        // respond with 404 instead of redirecting home (cleaner UX + harder
        // for scanners to detect).
        add_action( 'template_redirect', function () {
            if ( isset( $_GET['author'] ) && absint( $_GET['author'] ) > 0 ) {
                global $wp_query;
                $wp_query->set_404();
                status_header( 404 );
                nocache_headers();
                include get_query_template( '404' );
                exit;
            }
        } );

        // Audit fix A3: CSP hardening with per-request nonce.
        // The previous CSP allowed 'unsafe-inline' for both scripts and
        // styles — that largely defeated XSS protection. The new CSP uses
        // a per-request nonce for scripts (injected into all <script> tags
        // via an output buffer) and keeps 'unsafe-inline' for styles only
        // (CSS injection is much lower risk and would require touching
        // every inline style attribute across the plugin + theme).
        add_action( 'send_headers', function () {
            if ( is_admin() ) {
                return;
            }

            $nonce = Eprocurement_CSP_Nonce::get_nonce();

            // Build the CSP header. Note: 'strict-dynamic' allows nonce'd
            // scripts to load their own dependencies without listing them
            // explicitly (CSP Level 3, supported in Chrome 52+, Firefox 52+,
            // Safari 15.4+). Old browsers fall back to 'self'.
            header( "Content-Security-Policy: default-src 'self'; " .
                    "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'; " .
                    "style-src 'self' 'unsafe-inline'; " .
                    "img-src 'self' data: https:; " .
                    "font-src 'self' data:; " .
                    "connect-src 'self'; " .
                    "frame-ancestors 'self'; " .
                    "base-uri 'self'; " .
                    "form-action 'self'" );
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: SAMEORIGIN' );
            header( 'Referrer-Policy: strict-origin-when-cross-origin' );
            header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
        } );

        // Start the output buffer that injects the nonce into all <script> tags.
        // Runs on template_redirect (after the headers above are sent, before
        // page rendering). Uses a high-priority callback so it wraps any other
        // buffers added by themes/plugins.
        add_action( 'template_redirect', function () {
            if ( is_admin() ) {
                return;
            }
            $nonce = Eprocurement_CSP_Nonce::get_nonce();
            ob_start( function ( $html ) use ( $nonce ) {
                // Inject nonce="..." into every <script> tag that doesn't already have one.
                // Pattern matches <script ...> (with any attributes or none), case-insensitive.
                return preg_replace_callback(
                    '/<script(?![^>]*\snonce=)([^>]*)>/i',
                    function ( $m ) use ( $nonce ) {
                        return '<script nonce="' . esc_attr( $nonce ) . '"' . $m[1] . '>';
                    },
                    $html
                );
            } );
        }, 1 );

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
