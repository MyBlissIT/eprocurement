<?php
/**
 * Public (frontend) handler.
 *
 * Registers the [eprocurement] shortcode and renders frontend views.
 * The shortcode routes to different views based on URL parameters:
 *
 * /tenders/              → Tender listing
 * /tenders/bid/123/      → Bid detail page
 * /tenders/register/     → Bidder registration
 * /tenders/login/        → Bidder login
 * /tenders/my-account/   → Bidder dashboard
 * /tenders/compliance/   → SCM documents
 * /tenders/verify/       → Email verification handler
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Public {

    /**
     * Initialise frontend hooks.
     */
    public function __construct() {
        add_shortcode( 'eprocurement', [ $this, 'render_shortcode' ] );
        add_shortcode( 'eprocurement_open', [ $this, 'render_open_bids' ] );
        add_shortcode( 'eprocurement_closed', [ $this, 'render_closed_bids' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_theme_assets' ], 999 );
        add_action( 'init', [ $this, 'register_rewrite_rules' ] );
        add_action( 'init', [ $this, 'handle_login' ] );
        add_action( 'init', [ $this, 'handle_logout' ] );
        add_action( 'template_redirect', [ $this, 'maybe_redirect_home' ] );
        add_filter( 'template_include', [ $this, 'override_page_template' ] );
        add_filter( 'show_admin_bar', [ $this, 'control_admin_bar' ] );
        add_action( 'wp_head', [ $this, 'output_standalone_reset' ], 1 );
        add_action( 'wp_loaded', [ $this, 'remove_global_styles_early' ] );
    }

    /**
     * Redirect the site homepage to the tenders page.
     *
     * Avoids setting the tenders page as the WordPress static front page
     * (which causes 301 redirects that break sub-page routing like
     * /tenders/bid/123/). Instead, uses a 302 redirect from / to /tenders/.
     *
     * Controlled by the 'eprocurement_redirect_home' option (set on activation).
     * Can be disabled in Settings if the site has its own homepage.
     */
    public function maybe_redirect_home(): void {
        if ( ! get_option( 'eprocurement_redirect_home' ) ) {
            return;
        }

        // Only redirect the actual front page
        if ( ! is_front_page() && ! is_home() ) {
            return;
        }

        // Don't redirect if admin or during AJAX/REST/cron
        if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        wp_redirect( home_url( '/' . $slug . '/' ), 302 );
        exit;
    }

    /**
     * Override the page template for eProcurement pages.
     *
     * Loads a custom template that bypasses the active theme entirely,
     * ensuring the site looks like a standalone eProcurement application.
     *
     * @param string $template Current template path.
     * @return string Filtered template path.
     */
    public function override_page_template( string $template ): string {
        if ( ! $this->is_eprocurement_page() ) {
            return $template;
        }

        $custom = EPROC_PLUGIN_DIR . 'templates/page-eprocurement.php';
        if ( file_exists( $custom ) ) {
            return $custom;
        }

        return $template;
    }

    /**
     * Dequeue all theme styles and scripts on eProcurement pages.
     *
     * Removes theme CSS/JS, block library styles, global styles, and
     * theme fonts so the page renders only eProcurement assets.
     */
    public function dequeue_theme_assets(): void {
        if ( ! $this->is_eprocurement_page() ) {
            return;
        }

        global $wp_styles, $wp_scripts;

        // Styles we want to keep.
        $keep_styles = [
            'eprocurement-frontend',
            'eprocurement-admin-shell',
            'eprocurement-admin',
            'eprocurement-frontend-admin',
            'admin-bar',
            'dashicons',
        ];

        // Scripts we want to keep.
        $keep_scripts = [
            'eprocurement-frontend',
            'eprocurement-frontend-admin',
            'jquery',
            'jquery-core',
            'jquery-migrate',
            'admin-bar',
            'wp-hooks',
        ];

        // Dequeue theme and block styles.
        if ( ! empty( $wp_styles->queue ) ) {
            foreach ( $wp_styles->queue as $handle ) {
                if ( ! in_array( $handle, $keep_styles, true ) ) {
                    wp_dequeue_style( $handle );
                }
            }
        }

        // Dequeue theme and block scripts.
        if ( ! empty( $wp_scripts->queue ) ) {
            foreach ( $wp_scripts->queue as $handle ) {
                if ( ! in_array( $handle, $keep_scripts, true ) ) {
                    wp_dequeue_script( $handle );
                }
            }
        }

        // Remove WordPress global styles (theme.json) and SVG filters.
        remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
        remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
        remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );

        // Remove block library inline styles.
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
        wp_dequeue_style( 'global-styles' );
        wp_dequeue_style( 'classic-theme-styles' );

        // Remove speculation rules (prefetch).
        wp_dequeue_script( 'wp-a11y' );
    }

    /**
     * Only show admin bar on eProcurement pages for Super Admins.
     *
     * @param bool $show Whether to show the admin bar.
     * @return bool
     */
    public function control_admin_bar( bool $show ): bool {
        if ( $this->is_eprocurement_page() && ! is_super_admin() ) {
            return false;
        }
        return $show;
    }

    /**
     * Remove WordPress global styles, SVG filters, and theme fonts early.
     *
     * Must run on wp_loaded (before wp_head) so the actions are removed
     * before they output inline <style> blocks.
     */
    public function remove_global_styles_early(): void {
        // We check is_eprocurement_page() later in dequeue_theme_assets,
        // but these removals must happen before template rendering.
        // Use a template_redirect hook (fires after query is parsed) for the check.
        add_action( 'template_redirect', function () {
            if ( ! $this->is_eprocurement_page() ) {
                return;
            }

            // Remove global styles (theme.json → inline CSS with font-face, etc.)
            remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
            remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
            remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );

            // Remove emoji styles/scripts.
            remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
            remove_action( 'wp_print_styles', 'print_emoji_styles' );

            // Remove oEmbed.
            remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
            remove_action( 'wp_head', 'wp_oembed_add_host_js' );

            // Remove REST API link.
            remove_action( 'wp_head', 'rest_output_link_wp_head' );

            // Remove shortlink.
            remove_action( 'wp_head', 'wp_shortlink_wp_head' );

            // Remove WP generator tag.
            remove_action( 'wp_head', 'wp_generator' );

            // Remove RSS feed links.
            remove_action( 'wp_head', 'feed_links', 2 );
            remove_action( 'wp_head', 'feed_links_extra', 3 );

            // Remove wlwmanifest link.
            remove_action( 'wp_head', 'wlwmanifest_link' );

            // Remove RSD link.
            remove_action( 'wp_head', 'rsd_link' );

            // Remove speculation rules (WordPress 6.7+ prefetch).
            remove_action( 'wp_footer', 'wp_enqueue_speculation_rules', 1 );
            if ( function_exists( 'wp_register_speculation_rules' ) ) {
                remove_action( 'wp_footer', 'wp_register_speculation_rules' );
            }
            add_filter( 'wp_speculation_rules_configuration', '__return_empty_array' );
            remove_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );

            // Remove theme fonts (wp-fonts-local).
            add_filter( 'wp_theme_json_data_theme', function ( $theme_json ) {
                $data = $theme_json->get_data();
                // Remove fontFamilies to prevent theme font-face output.
                if ( isset( $data['settings']['typography']['fontFamilies'] ) ) {
                    $data['settings']['typography']['fontFamilies'] = [];
                }
                return $theme_json->update_with( $data );
            }, 999 );

            // Also remove wp_print_font_faces which outputs the font-face CSS.
            remove_action( 'wp_head', 'wp_print_font_faces', 50 );
        } );
    }

    /**
     * Output minimal CSS reset in <head> for standalone mode.
     */
    public function output_standalone_reset(): void {
        if ( ! $this->is_eprocurement_page() ) {
            return;
        }
        ?>
        <style id="eproc-standalone-reset">
            /* Reset theme styles for standalone eProcurement */
            body.eproc-standalone {
                margin: 0;
                padding: 0;
                background: #f8fafc;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                font-size: 14px;
                line-height: 1.5;
                color: #2d3748;
                -webkit-font-smoothing: antialiased;
            }
            body.eproc-standalone * {
                box-sizing: border-box;
            }
            body.admin-bar.eproc-standalone .eproc-navbar {
                top: 32px;
            }
            @media screen and (max-width: 782px) {
                body.admin-bar.eproc-standalone .eproc-navbar {
                    top: 46px;
                }
            }
        </style>
        <?php
    }

    /**
     * Check if the current page is an eProcurement page.
     *
     * @return bool
     */
    private function is_eprocurement_page(): bool {
        // During AJAX/REST requests, this isn't applicable.
        if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return false;
        }

        global $post;
        if ( ! $post ) {
            return false;
        }

        return has_shortcode( $post->post_content, 'eprocurement' )
            || has_shortcode( $post->post_content, 'eprocurement_open' )
            || has_shortcode( $post->post_content, 'eprocurement_closed' );
    }

    /**
     * Register rewrite rules for frontend sub-pages (register, login, etc.).
     */
    public function register_rewrite_rules(): void {
        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

        // Route tenders/manage/sub-page/ to the tenders page (must come before single-level rule)
        add_rewrite_rule(
            '^' . preg_quote( $slug, '/' ) . '/manage/([^/]*)/?$',
            'index.php?pagename=' . $slug . '&eproc_route=manage&eproc_manage_page=$matches[1]',
            'top'
        );

        // Route tenders/bid/{id}/ to the bid detail view (clean URL)
        add_rewrite_rule(
            '^' . preg_quote( $slug, '/' ) . '/bid/(\d+)/?$',
            'index.php?pagename=' . $slug . '&eproc_route=bid&eproc_bid_id=$matches[1]',
            'top'
        );

        // Route tenders/register/, tenders/login/, etc. to the tenders page
        add_rewrite_rule(
            '^' . preg_quote( $slug, '/' ) . '/([^/]+)/?$',
            'index.php?pagename=' . $slug . '&eproc_route=$matches[1]',
            'top'
        );

        add_rewrite_tag( '%eproc_route%', '([^&]+)' );
        add_rewrite_tag( '%eproc_manage_page%', '([^&]+)' );
        add_rewrite_tag( '%eproc_bid_id%', '(\d+)' );

        // Prevent WordPress canonical redirect from stripping our sub-paths.
        add_filter( 'redirect_canonical', [ $this, 'prevent_subpage_redirect' ], 10, 2 );
    }

    /**
     * Prevent canonical redirect for eProcurement pages.
     *
     * Prevents redirect when our eproc_route query var is present, and
     * also prevents WordPress from redirecting /tenders/ → / when the
     * tenders page is set as the front page.
     */
    public function prevent_subpage_redirect( $redirect_url, $requested_url ) {
        // Always prevent redirect for our sub-pages.
        if ( get_query_var( 'eproc_route' ) ) {
            return false;
        }

        // Prevent redirect of /tenders/ to / when tenders is front page.
        $slug     = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        $raw_path = trim( wp_parse_url( $requested_url, PHP_URL_PATH ) ?: '', '/' );
        if ( $raw_path === $slug ) {
            return false;
        }

        return $redirect_url;
    }

    /**
     * Enqueue frontend CSS and JS.
     */
    public function enqueue_assets(): void {
        // Only load on pages that use our shortcode
        global $post;
        $has_shortcode = $post && (
            has_shortcode( $post->post_content, 'eprocurement' ) ||
            has_shortcode( $post->post_content, 'eprocurement_open' ) ||
            has_shortcode( $post->post_content, 'eprocurement_closed' )
        );
        if ( ! $has_shortcode ) {
            return;
        }

        wp_enqueue_style(
            'eprocurement-frontend',
            EPROC_PLUGIN_URL . 'public/frontend.css',
            [],
            EPROC_VERSION
        );

        wp_enqueue_script(
            'eprocurement-frontend',
            EPROC_PLUGIN_URL . 'public/frontend.js',
            [ 'jquery' ],
            EPROC_VERSION,
            true
        );

        wp_localize_script( 'eprocurement-frontend', 'eprocFrontend', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'restUrl'  => rest_url( 'eprocurement/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'loggedIn' => is_user_logged_in(),
            'slug'     => get_option( 'eprocurement_frontend_page_slug', 'tenders' ),
            'strings'  => [
                'login_required'  => __( 'Please register or log in to send queries.', 'eprocurement' ),
                'verify_required' => __( 'Please verify your email address before sending queries.', 'eprocurement' ),
                'sending'         => __( 'Sending...', 'eprocurement' ),
                'sent'            => __( 'Query submitted successfully!', 'eprocurement' ),
                'error'           => __( 'An error occurred. Please try again.', 'eprocurement' ),
                'registering'     => __( 'Creating your account...', 'eprocurement' ),
                'registered'      => __( 'Registration successful! Check your email for the verification link.', 'eprocurement' ),
            ],
        ] );

        // Load admin CSS/JS on manage pages
        $raw_path  = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
        $page_slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        if ( strpos( $raw_path, "/{$page_slug}/manage" ) !== false ) {
            wp_enqueue_style(
                'eprocurement-admin-shell',
                EPROC_PLUGIN_URL . 'admin/admin-shell.css',
                [],
                EPROC_VERSION
            );
            wp_enqueue_style(
                'eprocurement-admin',
                EPROC_PLUGIN_URL . 'admin/admin.css',
                [ 'eprocurement-admin-shell' ],
                EPROC_VERSION
            );
            wp_enqueue_style(
                'eprocurement-frontend-admin',
                EPROC_PLUGIN_URL . 'public/frontend-admin.css',
                [ 'eprocurement-admin' ],
                EPROC_VERSION
            );
            wp_enqueue_script(
                'eprocurement-frontend-admin',
                EPROC_PLUGIN_URL . 'public/frontend-admin.js',
                [ 'jquery' ],
                EPROC_VERSION,
                true
            );
            wp_localize_script( 'eprocurement-frontend-admin', 'eprocManage', [
                'restUrl' => rest_url( 'eprocurement/v1/' ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'ajaxNonce' => wp_create_nonce( 'eproc_admin_nonce' ),
                'slug'    => $page_slug,
                'strings' => [
                    'confirm_delete'  => __( 'Are you sure you want to delete this?', 'eprocurement' ),
                    'saving'          => __( 'Saving...', 'eprocurement' ),
                    'saved'           => __( 'Saved successfully.', 'eprocurement' ),
                    'error'           => __( 'An error occurred. Please try again.', 'eprocurement' ),
                    'uploading'       => __( 'Uploading...', 'eprocurement' ),
                    'upload_success'  => __( 'File uploaded successfully.', 'eprocurement' ),
                    'testing'         => __( 'Testing connection...', 'eprocurement' ),
                    'connected'       => __( 'Connection successful!', 'eprocurement' ),
                    'connection_fail' => __( 'Connection failed.', 'eprocurement' ),
                ],
            ] );
        }
    }

    /**
     * Main shortcode handler — routes to the correct view.
     */
    public function render_shortcode( array $atts = [] ): string {
        ob_start();

        // Determine which view to render based on URL sub-path.
        // Parse only the path portion (strip query string) and match against slug sub-pages.
        $slug      = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        $raw_path  = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
        $page_path = trim( $raw_path, '/' );

        // Extract the sub-path after the slug (e.g. "tenders/register" → "register").
        // Also handles the case where tenders is the front page and URL is just "/".
        $sub_path = '';
        $slug_pos = strpos( $page_path, $slug );
        if ( $slug_pos !== false ) {
            $sub_path = trim( substr( $page_path, $slug_pos + strlen( $slug ) ), '/' );
        }
        // If we're on the front page (path is empty or "/"), sub_path stays empty → tender listing.

        // Resolve bid ID from clean URL (/tenders/bid/5/) or legacy query param (?bid=5)
        $eproc_bid_id = get_query_var( 'eproc_bid_id' );
        if ( ! $eproc_bid_id && isset( $_GET['bid'] ) ) {
            $eproc_bid_id = absint( $_GET['bid'] );
        }

        if ( strpos( $sub_path, 'manage' ) === 0 ) {
            // Staff admin panel — delegate to frontend admin handler
            $frontend_admin = new Eprocurement_Frontend_Admin();
            $frontend_admin->render( $sub_path );
            return ob_get_clean();
        } elseif ( $eproc_bid_id ) {
            // Bid detail page — set $_GET['bid'] for backward compat with tender-detail.php
            $_GET['bid'] = $eproc_bid_id;
            require EPROC_PLUGIN_DIR . 'public/partials/tender-detail.php';
        } elseif ( $sub_path === 'register' ) {
            // Registration page
            require EPROC_PLUGIN_DIR . 'public/partials/bidder-register.php';
        } elseif ( $sub_path === 'login' ) {
            // Login page
            require EPROC_PLUGIN_DIR . 'public/partials/bidder-login.php';
        } elseif ( $sub_path === 'my-account' ) {
            // Bidder dashboard (requires login)
            if ( is_user_logged_in() && Eprocurement_Roles::is_bidder() ) {
                require EPROC_PLUGIN_DIR . 'public/partials/bidder-dashboard.php';
            } else {
                require EPROC_PLUGIN_DIR . 'public/partials/bidder-login.php';
            }
        } elseif ( $sub_path === 'compliance' ) {
            // SCM documents
            require EPROC_PLUGIN_DIR . 'public/partials/compliance-docs.php';
        } elseif ( in_array( $sub_path, [ 'briefing-register', 'closing-register', 'appointments' ], true ) ) {
            // Category-specific listing
            $category_map = [
                'briefing-register' => 'briefing_register',
                'closing-register'  => 'closing_register',
                'appointments'      => 'appointments',
            ];
            $eproc_category = $category_map[ $sub_path ];
            require EPROC_PLUGIN_DIR . 'public/partials/tender-listing.php';
        } else {
            // Default: tender listing
            require EPROC_PLUGIN_DIR . 'public/partials/tender-listing.php';
        }

        return ob_get_clean();
    }

    /**
     * Handle frontend login form submission.
     */
    public function handle_login(): void {
        if ( empty( $_POST['eproc_login_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eproc_login_nonce'] ) ), 'eproc_login' ) ) {
            return;
        }

        $email    = sanitize_email( $_POST['email'] ?? '' );
        $password = $_POST['password'] ?? '';

        $user = wp_authenticate( $email, $password );

        if ( is_wp_error( $user ) ) {
            // Try by email
            $user_data = get_user_by( 'email', $email );
            if ( $user_data ) {
                $user = wp_signon( [
                    'user_login'    => $user_data->user_login,
                    'user_password' => $password,
                    'remember'      => true,
                ] );
            }
        }

        if ( is_wp_error( $user ) ) {
            // Store error for display
            set_transient( 'eproc_login_error_' . md5( $email ), $user->get_error_message(), 60 );
            return;
        }

        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

        // Route staff to manage panel, bidders to my-account
        if ( Eprocurement_Roles::is_staff( $user->ID ) ) {
            $redirect = home_url( "/{$slug}/manage/" );
        } else {
            $redirect = home_url( "/{$slug}/my-account/" );
        }

        if ( ! empty( $_POST['redirect_to'] ) ) {
            $redirect = esc_url_raw( wp_unslash( $_POST['redirect_to'] ) );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle frontend logout.
     */
    public function handle_logout(): void {
        if ( empty( $_GET['eproc_logout'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'eproc_logout' ) ) {
            return;
        }

        wp_logout();

        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        wp_safe_redirect( home_url( "/{$slug}/" ) );
        exit;
    }

    /**
     * Get navigation items for the frontend.
     */
    public static function get_nav_items(): array {
        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

        $items = [
            [
                'label' => __( 'Tenders', 'eprocurement' ),
                'url'   => home_url( "/{$slug}/" ),
            ],
        ];

        // Add enabled bid categories to nav.
        $categories = [
            'briefing_register' => [ 'label' => __( 'Briefing Register', 'eprocurement' ), 'path' => 'briefing-register' ],
            'closing_register'  => [ 'label' => __( 'Closing Register', 'eprocurement' ),  'path' => 'closing-register' ],
            'appointments'      => [ 'label' => __( 'Appointments', 'eprocurement' ),       'path' => 'appointments' ],
        ];
        foreach ( $categories as $key => $cat ) {
            if ( get_option( "eprocurement_category_{$key}", '0' ) === '1' ) {
                $items[] = [
                    'label' => $cat['label'],
                    'url'   => home_url( "/{$slug}/{$cat['path']}/" ),
                ];
            }
        }

        $items[] = [
            'label' => Eprocurement_Compliance_Docs::get_section_title(),
            'url'   => home_url( "/{$slug}/compliance/" ),
        ];

        if ( ! is_user_logged_in() ) {
            $items[] = [
                'label' => __( 'How to Register', 'eprocurement' ),
                'url'   => home_url( "/{$slug}/register/" ),
            ];
        }

        if ( is_user_logged_in() && Eprocurement_Roles::is_bidder() ) {
            $items[] = [
                'label' => __( 'My Dashboard', 'eprocurement' ),
                'url'   => home_url( "/{$slug}/my-account/" ),
            ];
        }

        if ( is_user_logged_in() && Eprocurement_Roles::is_staff() ) {
            $items[] = [
                'label' => __( 'Manage', 'eprocurement' ),
                'url'   => home_url( "/{$slug}/manage/" ),
            ];
        }

        return $items;
    }

    /**
     * Format file size for display.
     */
    public static function format_file_size( int $bytes ): string {
        if ( $bytes >= 1048576 ) {
            return round( $bytes / 1048576, 1 ) . ' MB';
        } elseif ( $bytes >= 1024 ) {
            return round( $bytes / 1024 ) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Render the [eprocurement_open] shortcode — card grid of open bids.
     */
    public function render_open_bids( array $atts = [] ): string {
        return $this->render_bid_listing_shortcode( 'open', $atts );
    }

    /**
     * Render the [eprocurement_closed] shortcode — card grid of closed bids.
     */
    public function render_closed_bids( array $atts = [] ): string {
        return $this->render_bid_listing_shortcode( 'closed', $atts );
    }

    /**
     * Shared renderer for the open/closed bid listing shortcodes.
     */
    private function render_bid_listing_shortcode( string $status, array $atts ): string {
        $atts = shortcode_atts( [
            'limit' => 12,
        ], $atts );

        $slug      = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        $documents = new Eprocurement_Documents();

        $result = $documents->list( [
            'per_page' => absint( $atts['limit'] ),
            'page'     => 1,
            'status'   => $status,
            'orderby'  => 'closing_date',
            'order'    => 'DESC',
        ] );

        $items = $result['items'];

        $hero_class = $status === 'open' ? 'eproc-hero eproc-hero--open' : 'eproc-hero eproc-hero--closed';
        $hero_title = $status === 'open'
            ? __( 'Open Tenders & Bids', 'eprocurement' )
            : __( 'Closed Tenders & Bids', 'eprocurement' );
        $hero_sub = $status === 'open'
            ? __( 'Currently accepting submissions. Download bid documents and submit your queries before the closing date.', 'eprocurement' )
            : __( 'These tenders have closed and are no longer accepting submissions.', 'eprocurement' );

        ob_start();
        ?>
        <div class="eproc-wrap">
            <section class="<?php echo esc_attr( $hero_class ); ?>">
                <div class="eproc-hero-inner">
                    <h1 class="eproc-hero-title"><?php echo esc_html( $hero_title ); ?></h1>
                    <p class="eproc-hero-subtitle"><?php echo esc_html( $hero_sub ); ?></p>
                </div>
            </section>
            <div class="eproc-card-grid eproc-card-grid--3col">
                <?php if ( empty( $items ) ) : ?>
                    <div class="eproc-empty-state">
                        <p>
                            <?php echo esc_html(
                                $status === 'open'
                                    ? __( 'No open tenders at this time.', 'eprocurement' )
                                    : __( 'No closed tenders to display.', 'eprocurement' )
                            ); ?>
                        </p>
                    </div>
                <?php else : ?>
                    <?php foreach ( $items as $doc ) :
                        $closing_date = $doc->closing_date
                            ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $doc->closing_date ) )
                            : '';
                        $short_desc   = wp_trim_words( wp_strip_all_tags( $doc->description ), 20, '...' );
                    ?>
                        <div class="eproc-card eproc-bid-card">
                            <div class="eproc-card-header">
                                <?php echo self::status_badge( $doc->status ); // phpcs:ignore ?>
                            </div>
                            <div class="eproc-card-body">
                                <p class="eproc-bid-number"><?php echo esc_html( $doc->bid_number ); ?></p>
                                <h3 class="eproc-bid-title"><?php echo esc_html( $doc->title ); ?></h3>
                                <?php if ( $short_desc ) : ?>
                                    <p class="eproc-bid-excerpt"><?php echo esc_html( $short_desc ); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="eproc-card-meta">
                                <?php if ( $closing_date ) : ?>
                                    <div class="eproc-meta-item">
                                        <span class="eproc-meta-label"><?php echo esc_html__( 'Closing:', 'eprocurement' ); ?></span>
                                        <span class="eproc-meta-value"><?php echo esc_html( $closing_date ); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="eproc-card-footer">
                                <a href="<?php echo esc_url( self::bid_url( (int) $doc->id ) ); ?>"
                                   class="eproc-btn eproc-btn-primary eproc-btn-block">
                                    <?php echo esc_html__( 'View Details', 'eprocurement' ); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get a status badge HTML.
     */
    public static function status_badge( string $status ): string {
        $colors = [
            'draft'     => '#888',
            'open'      => '#16a34a',
            'closed'    => '#8b1a2b',
            'cancelled' => '#95a5a6',
            'archived'  => '#7f8c8d',
        ];

        $color = $colors[ $status ] ?? '#888';

        return sprintf(
            '<span class="eproc-status-badge" style="background:%s">%s</span>',
            esc_attr( $color ),
            esc_html( strtoupper( $status ) )
        );
    }

    /**
     * Generate a clean bid detail URL: /tenders/bid/{id}/
     */
    public static function bid_url( int $bid_id ): string {
        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        return home_url( "/{$slug}/bid/{$bid_id}/" );
    }
}
