<?php
/**
 * Frontend Admin Panel Handler.
 *
 * Renders the staff admin panel at /tenders/manage/ with the same
 * sidebar layout as wp-admin, but entirely on the frontend.
 * All data operations use the REST API via fetch().
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Frontend_Admin {

    /**
     * Render the manage panel based on the sub-path.
     *
     * @param string $sub_path The sub-path after the slug (e.g. "manage/bids").
     */
    public function render( string $sub_path ): void {
        // Must be logged in as staff
        if ( ! is_user_logged_in() || ! Eprocurement_Roles::is_staff() ) {
            $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
            if ( ! is_user_logged_in() ) {
                wp_safe_redirect( home_url( "/{$slug}/login/?redirect_to=" . urlencode( $_SERVER['REQUEST_URI'] ) ) );
                exit;
            }
            // Logged in but not staff (e.g., bidder)
            require EPROC_PLUGIN_DIR . 'public/partials/manage/access-denied.php';
            return;
        }

        // Extract the manage sub-page
        $manage_page = '';
        if ( strpos( $sub_path, 'manage/' ) === 0 ) {
            $manage_page = trim( substr( $sub_path, strlen( 'manage/' ) ), '/' );
        } elseif ( $sub_path === 'manage' ) {
            $manage_page = '';
        }

        // Super Admin check for settings and users pages
        $admin_only_pages = [ 'settings', 'users' ];
        if ( in_array( $manage_page, $admin_only_pages, true ) && ! is_super_admin() ) {
            require EPROC_PLUGIN_DIR . 'public/partials/manage/access-denied.php';
            return;
        }

        // Capability checks for specific pages
        $page_caps = [
            ''           => 'eproc_view_dashboard',
            'bids'       => 'eproc_view_dashboard',
            'messages'   => 'eproc_view_threads',
            'contacts'   => 'eproc_manage_contacts',
            'bidders'    => 'eproc_view_bidders',
            'scm-docs'   => 'eproc_manage_compliance',
            'downloads'  => 'eproc_view_downloads',
        ];

        if ( isset( $page_caps[ $manage_page ] ) && ! current_user_can( $page_caps[ $manage_page ] ) ) {
            require EPROC_PLUGIN_DIR . 'public/partials/manage/access-denied.php';
            return;
        }

        // Open layout
        $this->open_layout( $manage_page );

        // Route to partial
        switch ( $manage_page ) {
            case '':
                require EPROC_PLUGIN_DIR . 'public/partials/manage/dashboard.php';
                break;
            case 'bids':
                $action = sanitize_text_field( $_GET['action'] ?? 'list' );
                if ( $action === 'edit' || $action === 'new' ) {
                    $eproc_category = sanitize_text_field( $_GET['category'] ?? 'bid' );
                    require EPROC_PLUGIN_DIR . 'public/partials/manage/bid-edit.php';
                } else {
                    $eproc_category = sanitize_text_field( $_GET['category'] ?? 'bid' );
                    require EPROC_PLUGIN_DIR . 'public/partials/manage/bid-list.php';
                }
                break;
            case 'messages':
                require EPROC_PLUGIN_DIR . 'public/partials/manage/messages.php';
                break;
            case 'contacts':
                require EPROC_PLUGIN_DIR . 'public/partials/manage/contacts.php';
                break;
            case 'bidders':
                require EPROC_PLUGIN_DIR . 'public/partials/manage/bidders.php';
                break;
            case 'scm-docs':
                require EPROC_PLUGIN_DIR . 'public/partials/manage/scm-docs.php';
                break;
            case 'downloads':
                require EPROC_PLUGIN_DIR . 'public/partials/manage/downloads.php';
                break;
            case 'settings':
                require EPROC_PLUGIN_DIR . 'public/partials/manage/settings.php';
                break;
            case 'users':
                require EPROC_PLUGIN_DIR . 'public/partials/manage/users.php';
                break;
            default:
                require EPROC_PLUGIN_DIR . 'public/partials/manage/dashboard.php';
                break;
        }

        // Close layout
        $this->close_layout();
    }

    /**
     * Open the frontend admin layout shell.
     */
    private function open_layout( string $active_page ): void {
        require EPROC_PLUGIN_DIR . 'public/partials/manage/layout-wrapper.php';
    }

    /**
     * Close the frontend admin layout shell.
     */
    private function close_layout(): void {
        echo '</main></div></div>'; // closes .eproc-admin-content + .eproc-admin-shell + .eproc-wrap
    }
}
