<?php
/**
 * Admin area handler.
 *
 * Registers admin menus, enqueues admin assets,
 * and handles admin AJAX operations.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Admin {

    /**
     * Open the custom admin layout shell (sidebar + content area).
     *
     * @param string $active_page The current page slug for highlighting.
     */
    public static function open_layout( string $active_page = '' ): void {
        require EPROC_PLUGIN_DIR . 'admin/partials/layout-wrapper.php';
    }

    /**
     * Close the layout shell (</main> + </div>).
     */
    public static function close_layout(): void {
        echo '</main></div>'; // closes .eproc-admin-content + .eproc-admin-shell
    }

    /**
     * Initialise admin hooks.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX handlers
        add_action( 'wp_ajax_eproc_save_bid', [ $this, 'ajax_save_bid' ] );
        add_action( 'wp_ajax_eproc_delete_bid', [ $this, 'ajax_delete_bid' ] );
        add_action( 'wp_ajax_eproc_change_status', [ $this, 'ajax_change_status' ] );
        add_action( 'wp_ajax_eproc_save_contact', [ $this, 'ajax_save_contact' ] );
        add_action( 'wp_ajax_eproc_delete_contact', [ $this, 'ajax_delete_contact' ] );
        add_action( 'wp_ajax_eproc_reply_message', [ $this, 'ajax_reply_message' ] );
        add_action( 'wp_ajax_eproc_save_settings', [ $this, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_eproc_upload_supporting_doc', [ $this, 'ajax_upload_supporting_doc' ] );
        add_action( 'wp_ajax_eproc_remove_supporting_doc', [ $this, 'ajax_remove_supporting_doc' ] );
        add_action( 'wp_ajax_eproc_upload_compliance_doc', [ $this, 'ajax_upload_compliance_doc' ] );
        add_action( 'wp_ajax_eproc_delete_compliance_doc', [ $this, 'ajax_delete_compliance_doc' ] );
        add_action( 'wp_ajax_eproc_export_downloads', [ $this, 'ajax_export_downloads' ] );
        add_action( 'wp_ajax_eproc_test_storage', [ $this, 'ajax_test_storage' ] );
        add_action( 'wp_ajax_eproc_resolve_thread', [ $this, 'ajax_resolve_thread' ] );
        add_action( 'wp_ajax_eproc_resend_verification', [ $this, 'ajax_resend_verification' ] );
        add_action( 'wp_ajax_eproc_add_department', [ $this, 'ajax_add_department' ] );
        add_action( 'wp_ajax_eproc_change_thread_visibility', [ $this, 'ajax_change_thread_visibility' ] );
        add_action( 'wp_ajax_eproc_seed_demo_data', [ $this, 'ajax_seed_demo_data' ] );
        add_action( 'wp_ajax_eproc_remove_demo_data', [ $this, 'ajax_remove_demo_data' ] );

        // Handle OAuth callbacks
        add_action( 'admin_init', [ $this, 'handle_oauth_callback' ] );
    }

    /**
     * Register admin menus.
     */
    public function register_menus(): void {
        // Main menu
        add_menu_page(
            __( 'eProcurement', 'eprocurement' ),
            __( 'eProcurement', 'eprocurement' ),
            'eproc_view_dashboard',
            'eprocurement',
            [ $this, 'render_dashboard' ],
            'dashicons-portfolio',
            30
        );

        // Dashboard (same as main)
        add_submenu_page(
            'eprocurement',
            __( 'Dashboard', 'eprocurement' ),
            __( 'Dashboard', 'eprocurement' ),
            'eproc_view_dashboard',
            'eprocurement',
            [ $this, 'render_dashboard' ]
        );

        // Bid Documents
        add_submenu_page(
            'eprocurement',
            __( 'Bid Documents', 'eprocurement' ),
            __( 'Bid Documents', 'eprocurement' ),
            'eproc_create_bids',
            'eprocurement-bids',
            [ $this, 'render_bids' ]
        );

        // Category pages (only if enabled)
        $categories = [
            'briefing_register' => __( 'Briefing Register', 'eprocurement' ),
            'closing_register'  => __( 'Closing Register', 'eprocurement' ),
            'appointments'      => __( 'Appointments', 'eprocurement' ),
        ];
        foreach ( $categories as $cat_key => $cat_label ) {
            if ( get_option( "eprocurement_category_{$cat_key}", '0' ) === '1' ) {
                add_submenu_page(
                    'eprocurement',
                    $cat_label,
                    $cat_label,
                    'eproc_create_bids',
                    'eprocurement-' . $cat_key,
                    [ $this, 'render_category_bids' ]
                );
            }
        }

        // Messages / Inbox
        add_submenu_page(
            'eprocurement',
            __( 'Messages', 'eprocurement' ),
            $this->get_messages_menu_label(),
            'eproc_view_threads',
            'eprocurement-messages',
            [ $this, 'render_messages' ]
        );

        // Contact Persons
        add_submenu_page(
            'eprocurement',
            __( 'Contact Persons', 'eprocurement' ),
            __( 'Contact Persons', 'eprocurement' ),
            'eproc_manage_contacts',
            'eprocurement-contacts',
            [ $this, 'render_contacts' ]
        );

        // Bidders / Subscribers
        add_submenu_page(
            'eprocurement',
            __( 'Bidders', 'eprocurement' ),
            __( 'Bidders', 'eprocurement' ),
            'eproc_view_bidders',
            'eprocurement-bidders',
            [ $this, 'render_bidders' ]
        );

        // SCM Documents
        add_submenu_page(
            'eprocurement',
            __( 'SCM Documents', 'eprocurement' ),
            Eprocurement_Compliance_Docs::get_section_title(),
            'eproc_manage_compliance',
            'eprocurement-compliance',
            [ $this, 'render_compliance' ]
        );

        // Download Log
        add_submenu_page(
            'eprocurement',
            __( 'Download Log', 'eprocurement' ),
            __( 'Download Log', 'eprocurement' ),
            'eproc_view_downloads',
            'eprocurement-downloads',
            [ $this, 'render_download_log' ]
        );

        // Settings — Super Admin only
        if ( is_super_admin() ) {
            add_submenu_page(
                'eprocurement',
                __( 'Settings', 'eprocurement' ),
                __( 'Settings', 'eprocurement' ),
                'manage_options',
                'eprocurement-settings',
                [ $this, 'render_settings' ]
            );
        }
    }

    /**
     * Get messages menu label with unread badge.
     */
    private function get_messages_menu_label(): string {
        $messaging = new Eprocurement_Messaging();
        $unread    = $messaging->get_unread_count( get_current_user_id() );

        $label = __( 'Messages', 'eprocurement' );
        if ( $unread > 0 ) {
            $label .= sprintf( ' <span class="awaiting-mod">%d</span>', $unread );
        }

        return $label;
    }

    /**
     * Enqueue admin CSS and JS.
     */
    public function enqueue_assets( string $hook ): void {
        // Only load on our plugin pages
        if ( strpos( $hook, 'eprocurement' ) === false ) {
            return;
        }

        // Admin shell (sidebar layout + custom UI)
        wp_enqueue_style(
            'eprocurement-admin-shell',
            EPROC_PLUGIN_URL . 'admin/admin-shell.css',
            [],
            EPROC_VERSION
        );

        // Component styles (message bubbles, upload areas, modals, etc.)
        wp_enqueue_style(
            'eprocurement-admin',
            EPROC_PLUGIN_URL . 'admin/admin.css',
            [ 'eprocurement-admin-shell' ],
            EPROC_VERSION
        );

        wp_enqueue_script(
            'eprocurement-admin',
            EPROC_PLUGIN_URL . 'admin/admin.js',
            [ 'jquery', 'wp-util' ],
            EPROC_VERSION,
            true
        );

        // Localise script with AJAX data
        wp_localize_script( 'eprocurement-admin', 'eprocAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'eproc_admin_nonce' ),
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

        // Enqueue WordPress media uploader for file uploads
        wp_enqueue_media();
    }

    // --- Page Renderers ---

    public function render_dashboard(): void {
        self::open_layout( 'eprocurement' );
        require_once EPROC_PLUGIN_DIR . 'admin/partials/dashboard.php';
        self::close_layout();
    }

    public function render_bids(): void {
        $action = sanitize_text_field( $_GET['action'] ?? 'list' );

        self::open_layout( 'eprocurement-bids' );
        if ( $action === 'edit' || $action === 'new' ) {
            require_once EPROC_PLUGIN_DIR . 'admin/partials/bid-edit.php';
        } else {
            require_once EPROC_PLUGIN_DIR . 'admin/partials/bid-list.php';
        }
        self::close_layout();
    }

    public function render_messages(): void {
        self::open_layout( 'eprocurement-messages' );
        require_once EPROC_PLUGIN_DIR . 'admin/partials/messages.php';
        self::close_layout();
    }

    public function render_contacts(): void {
        self::open_layout( 'eprocurement-contacts' );
        require_once EPROC_PLUGIN_DIR . 'admin/partials/contact-persons.php';
        self::close_layout();
    }

    public function render_bidders(): void {
        self::open_layout( 'eprocurement-bidders' );
        require_once EPROC_PLUGIN_DIR . 'admin/partials/bidders.php';
        self::close_layout();
    }

    public function render_compliance(): void {
        self::open_layout( 'eprocurement-compliance' );
        require_once EPROC_PLUGIN_DIR . 'admin/partials/compliance-docs.php';
        self::close_layout();
    }

    public function render_download_log(): void {
        self::open_layout( 'eprocurement-downloads' );
        require_once EPROC_PLUGIN_DIR . 'admin/partials/download-log.php';
        self::close_layout();
    }

    public function render_category_bids(): void {
        $page = sanitize_text_field( $_GET['page'] ?? '' );
        $category_map = [
            'eprocurement-briefing_register' => 'briefing_register',
            'eprocurement-closing_register'  => 'closing_register',
            'eprocurement-appointments'      => 'appointments',
        ];
        $category = $category_map[ $page ] ?? 'bid';
        $action   = sanitize_text_field( $_GET['action'] ?? 'list' );

        self::open_layout( $page );
        if ( $action === 'edit' || $action === 'new' ) {
            // Pass category to the bid-edit partial
            $eproc_category = $category;
            require_once EPROC_PLUGIN_DIR . 'admin/partials/bid-edit.php';
        } else {
            $eproc_category = $category;
            require_once EPROC_PLUGIN_DIR . 'admin/partials/bid-list.php';
        }
        self::close_layout();
    }

    public function render_settings(): void {
        if ( ! is_super_admin() ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'eprocurement' ), 403 );
        }
        self::open_layout( 'eprocurement-settings' );
        require_once EPROC_PLUGIN_DIR . 'admin/partials/settings.php';
        self::close_layout();
    }

    // --- AJAX Handlers ---

    /**
     * Save a bid document (create or update).
     */
    public function ajax_save_bid(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_create_bids' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $documents = new Eprocurement_Documents();
        $id        = absint( $_POST['id'] ?? 0 );

        $category = sanitize_text_field( $_POST['category'] ?? 'bid' );
        if ( ! in_array( $category, [ 'bid', 'briefing_register', 'closing_register', 'appointments' ], true ) ) {
            $category = 'bid';
        }

        $data = [
            'bid_number'           => sanitize_text_field( $_POST['bid_number'] ?? '' ),
            'title'                => sanitize_text_field( $_POST['title'] ?? '' ),
            'description'          => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
            'category'             => $category,
        ];

        // Only include dates and contacts for regular bids
        if ( $category === 'bid' ) {
            $data['scm_contact_id']       = absint( $_POST['scm_contact_id'] ?? 0 ) ?: null;
            $data['technical_contact_id'] = absint( $_POST['technical_contact_id'] ?? 0 ) ?: null;
            $data['opening_date']         = self::parse_date_input( $_POST['opening_date'] ?? '' );
            $data['briefing_date']        = self::parse_date_input( $_POST['briefing_date'] ?? '' );
            $data['closing_date']         = self::parse_date_input( $_POST['closing_date'] ?? '' );
        }

        // Validate bid number uniqueness (scoped to category)
        if ( $data['bid_number'] ) {
            $existing = $documents->get_by_bid_number( $data['bid_number'], $category );
            if ( $existing && (int) $existing->id !== $id ) {
                wp_send_json_error( [
                    'message' => __( 'A bid with this number already exists in this category.', 'eprocurement' ),
                ] );
            }
        }

        if ( $id > 0 ) {
            $result = $documents->update( $id, $data );
            $doc_id = $id;
        } else {
            $result = $documents->create( $data );
            $doc_id = $result;
        }

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Failed to save bid document.', 'eprocurement' ) ] );
        }

        // Associate pending bid docs uploaded before save (BE-09)
        if ( ! empty( $_POST['pending_doc_ids'] ) && $doc_id ) {
            $pending_ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( $_POST['pending_doc_ids'] ) ) ) );
            if ( ! empty( $pending_ids ) ) {
                global $wpdb;
                $table        = Eprocurement_Database::table( 'supporting_docs' );
                $placeholders = implode( ',', array_fill( 0, count( $pending_ids ), '%d' ) );
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET document_id = %d WHERE id IN ({$placeholders}) AND document_id = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
                        $doc_id,
                        ...$pending_ids
                    )
                );
            }
        }

        wp_send_json_success( [
            'message' => __( 'Bid document saved successfully.', 'eprocurement' ),
            'id'      => $doc_id,
        ] );
    }

    /**
     * Delete a bid document.
     */
    public function ajax_delete_bid(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_delete_bids' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $documents = new Eprocurement_Documents();
        $id        = absint( $_POST['id'] ?? 0 );

        if ( $documents->delete( $id ) ) {
            wp_send_json_success( [ 'message' => __( 'Bid document deleted.', 'eprocurement' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to delete.', 'eprocurement' ) ] );
        }
    }

    /**
     * Change bid status.
     */
    public function ajax_change_status(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_publish_bids' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $documents  = new Eprocurement_Documents();
        $id         = absint( $_POST['id'] ?? 0 );
        $new_status = sanitize_text_field( $_POST['status'] ?? '' );

        // If transitioning to 'open' and closing_date is in the past, auto-set to 'closed'
        $auto_closed = false;
        if ( $new_status === 'open' && $id ) {
            $doc = $documents->get( $id );
            if ( $doc && Eprocurement_Documents::is_closing_date_past( $doc->closing_date ) ) {
                // First transition to open, then immediately to closed
                if ( $documents->transition_status( $id, 'open' ) ) {
                    $documents->transition_status( $id, 'closed' );
                    $auto_closed = true;
                }
            }
        }

        if ( $auto_closed ) {
            wp_send_json_success( [
                'message' => __( 'Bid auto-closed — the closing date has already passed.', 'eprocurement' ),
            ] );
        } elseif ( $documents->transition_status( $id, $new_status ) ) {
            wp_send_json_success( [
                'message' => sprintf(
                    /* translators: %s: new status */
                    __( 'Status changed to %s.', 'eprocurement' ),
                    strtoupper( $new_status )
                ),
            ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Invalid status transition.', 'eprocurement' ) ] );
        }
    }

    /**
     * Save a contact person.
     */
    public function ajax_save_contact(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_manage_contacts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $contacts = new Eprocurement_Contact_Persons();
        $id       = absint( $_POST['id'] ?? 0 );

        $data = [
            'user_id'    => absint( $_POST['user_id'] ?? 0 ) ?: null,
            'type'       => sanitize_text_field( $_POST['type'] ?? 'scm' ),
            'name'       => sanitize_text_field( $_POST['name'] ?? '' ),
            'phone'      => sanitize_text_field( $_POST['phone'] ?? '' ),
            'email'      => sanitize_email( $_POST['email'] ?? '' ),
            'department' => sanitize_text_field( $_POST['department'] ?? '' ),
        ];

        // Auto-create WP user for new contacts (BE-11)
        if ( $id === 0 && empty( $data['user_id'] ) && $data['email'] ) {
            $existing_user = get_user_by( 'email', $data['email'] );
            if ( $existing_user ) {
                $data['user_id'] = $existing_user->ID;
            } else {
                $username = sanitize_user( strtolower( explode( '@', $data['email'] )[0] ), true );
                // Ensure unique username
                if ( username_exists( $username ) ) {
                    $username .= wp_rand( 100, 999 );
                }
                $role = ( $data['type'] === 'scm' ) ? 'eprocurement_scm_official' : 'eprocurement_unit_manager';
                $new_user_id = wp_insert_user( [
                    'user_login'   => $username,
                    'user_email'   => $data['email'],
                    'user_pass'    => wp_generate_password( 16 ),
                    'display_name' => $data['name'],
                    'first_name'   => explode( ' ', $data['name'] )[0],
                    'role'         => $role,
                ] );
                if ( ! is_wp_error( $new_user_id ) ) {
                    $data['user_id'] = $new_user_id;
                    // Send password reset email so user can set their own password
                    wp_new_user_notification( $new_user_id, null, 'user' );
                }
            }
        }

        if ( $id > 0 ) {
            $result = $contacts->update( $id, $data );
        } else {
            $result = $contacts->create( $data );
        }

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Failed to save contact.', 'eprocurement' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Contact saved.', 'eprocurement' ) ] );
    }

    /**
     * Delete a contact person.
     */
    public function ajax_delete_contact(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_manage_contacts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $contacts = new Eprocurement_Contact_Persons();
        $id       = absint( $_POST['id'] ?? 0 );

        if ( $contacts->delete( $id ) ) {
            wp_send_json_success( [ 'message' => __( 'Contact deleted.', 'eprocurement' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Cannot delete. Contact is assigned to active bids.', 'eprocurement' ) ] );
        }
    }

    /**
     * Reply to a message thread.
     */
    public function ajax_reply_message(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_reply_threads' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $messaging  = new Eprocurement_Messaging();
        $thread_id  = absint( $_POST['thread_id'] ?? 0 );
        $message    = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );

        if ( ! $message ) {
            wp_send_json_error( [ 'message' => __( 'Message cannot be empty.', 'eprocurement' ) ] );
        }

        $message_id = $messaging->add_message( $thread_id, get_current_user_id(), $message );

        if ( ! $message_id ) {
            wp_send_json_error( [ 'message' => __( 'Failed to send reply.', 'eprocurement' ) ] );
        }

        // Handle optional file attachment (max 5MB).
        if ( ! empty( $_FILES['attachment'] ) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK ) {
            $file       = $_FILES['attachment'];
            $max_size   = 5 * 1024 * 1024; // 5 MB
            $allowed    = [ 'application/pdf', 'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'image/jpeg', 'image/png' ];

            if ( $file['size'] > $max_size ) {
                // Message saved but attachment skipped — inform the user.
                do_action( 'eprocurement_reply_posted', $thread_id, $message_id );
                wp_send_json_success( [
                    'message' => __( 'Reply sent but attachment skipped — file exceeds 5 MB limit.', 'eprocurement' ),
                ] );
            }

            $finfo     = finfo_open( FILEINFO_MIME_TYPE );
            $mime_type = finfo_file( $finfo, $file['tmp_name'] );
            finfo_close( $finfo );

            if ( ! in_array( $mime_type, $allowed, true ) ) {
                do_action( 'eprocurement_reply_posted', $thread_id, $message_id );
                wp_send_json_success( [
                    'message' => __( 'Reply sent but attachment skipped — file type not allowed.', 'eprocurement' ),
                ] );
            }

            $storage = Eprocurement_Storage_Interface::get_active_provider();
            if ( $storage ) {
                try {
                    $result = $storage->upload( $file['tmp_name'], $file['name'], 'message-attachments' );

                    $messaging->add_attachment( $message_id, [
                        'file_name'      => $file['name'],
                        'file_size'      => $file['size'],
                        'file_type'      => $mime_type,
                        'cloud_provider' => $storage->get_provider_name(),
                        'cloud_key'      => $result['cloud_key'],
                        'cloud_url'      => $result['cloud_url'],
                    ] );
                } catch ( \Exception $e ) {
                    do_action( 'eprocurement_reply_posted', $thread_id, $message_id );
                    wp_send_json_success( [
                        'message' => __( 'Reply sent but attachment upload failed.', 'eprocurement' ),
                    ] );
                }
            }
        }

        do_action( 'eprocurement_reply_posted', $thread_id, $message_id );
        wp_send_json_success( [ 'message' => __( 'Reply sent.', 'eprocurement' ) ] );
    }

    /**
     * Save plugin settings.
     */
    public function ajax_save_settings(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! is_super_admin() ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied. Super Admin access required.', 'eprocurement' ) ] );
        }

        // Branding — only update if at least one branding key is present
        if ( isset( $_POST['brand_name'] )
          || isset( $_POST['brand_url'] )
          || isset( $_POST['support_email'] )
          || isset( $_POST['brand_logo'] )
          || isset( $_POST['login_title'] ) ) {

            if ( array_key_exists( 'brand_name', $_POST ) ) {
                update_option( 'eprocurement_brand_name', sanitize_text_field( $_POST['brand_name'] ) );
            }
            if ( array_key_exists( 'brand_url', $_POST ) ) {
                update_option( 'eprocurement_brand_url', esc_url_raw( $_POST['brand_url'] ) );
            }
            if ( array_key_exists( 'support_email', $_POST ) ) {
                update_option( 'eprocurement_support_email', sanitize_email( $_POST['support_email'] ) );
            }
            if ( array_key_exists( 'brand_logo', $_POST ) ) {
                update_option( 'eprocurement_brand_logo', sanitize_text_field( $_POST['brand_logo'] ) );
            }
            if ( array_key_exists( 'login_title', $_POST ) ) {
                update_option( 'eprocurement_login_title', sanitize_text_field( $_POST['login_title'] ) );
            }
        }

        // Brand colors — only update if at least one color key is present
        if ( isset( $_POST['color_primary'] ) || isset( $_POST['color_secondary'] ) ) {
            $existing_colors = get_option( 'eprocurement_brand_colors', '' );
            $colors          = [];

            if ( is_string( $existing_colors ) && $existing_colors !== '' ) {
                $decoded = json_decode( $existing_colors, true );
                if ( is_array( $decoded ) ) {
                    $colors = $decoded;
                }
            } elseif ( is_array( $existing_colors ) ) {
                $colors = $existing_colors;
            }

            if ( ! empty( $_POST['color_primary'] ) ) {
                $primary = sanitize_hex_color( $_POST['color_primary'] );
                if ( $primary ) {
                    $colors['primary'] = $primary;
                    // Auto-derive hover (darken by ~20%)
                    $colors['primary_hover'] = self::darken_hex( $primary, 20 );
                }
            }
            if ( ! empty( $_POST['color_secondary'] ) ) {
                $secondary = sanitize_hex_color( $_POST['color_secondary'] );
                if ( $secondary ) {
                    $colors['secondary'] = $secondary;
                    // Auto-derive lighter variant
                    $colors['secondary_light'] = self::lighten_hex( $secondary, 15 );
                }
            }

            update_option( 'eprocurement_brand_colors', wp_json_encode( $colors ) );
        }

        // Cloud provider — only update if key submitted
        if ( array_key_exists( 'cloud_provider', $_POST ) ) {
            $provider = sanitize_text_field( $_POST['cloud_provider'] );
            update_option( 'eprocurement_cloud_provider', $provider );
        }

        // Cloud credentials (encrypted via storage interface)
        if ( ! empty( $_POST['cloud_credentials'] ) && is_array( $_POST['cloud_credentials'] ) ) {
            $creds     = array_map( 'sanitize_text_field', $_POST['cloud_credentials'] );
            $json      = wp_json_encode( $creds );
            $encrypted = Eprocurement_Storage_Interface::encrypt( $json );

            update_option( 'eprocurement_cloud_credentials', $encrypted );
        }

        // Retention days — only update if key submitted
        if ( array_key_exists( 'closed_bid_retention_days', $_POST ) ) {
            $retention = sanitize_text_field( $_POST['closed_bid_retention_days'] );
            update_option( 'eprocurement_closed_bid_retention_days', $retention );
        }

        // SCM Documents section title
        if ( ! empty( $_POST['compliance_section_title'] ) ) {
            $compliance_title = sanitize_text_field( $_POST['compliance_section_title'] );
            Eprocurement_Compliance_Docs::set_section_title( $compliance_title );
        }

        // Compliance doc sort order
        if ( ! empty( $_POST['compliance_order'] ) && is_array( $_POST['compliance_order'] ) ) {
            global $wpdb;
            $table = Eprocurement_Database::table( 'compliance_docs' );
            $order = array_map( 'absint', $_POST['compliance_order'] );
            foreach ( $order as $index => $doc_id ) {
                $wpdb->update( $table, [ 'sort_order' => $index ], [ 'id' => $doc_id ], [ '%d' ], [ '%d' ] ); // phpcs:ignore
            }
        }

        // Frontend page slug — only update if key submitted
        if ( array_key_exists( 'frontend_page_slug', $_POST ) ) {
            $slug = sanitize_title( $_POST['frontend_page_slug'] ?: 'tenders' );
            update_option( 'eprocurement_frontend_page_slug', $slug );
        }

        // Main page heading (configurable per tenant)
        if ( ! empty( $_POST['bid_heading'] ) ) {
            $bid_heading = sanitize_text_field( $_POST['bid_heading'] );
            update_option( 'eprocurement_bid_heading', $bid_heading );
        }

        // Bid category toggles — only update if at least one category key is present
        if ( isset( $_POST['category_briefing_register'] )
          || isset( $_POST['category_closing_register'] )
          || isset( $_POST['category_appointments'] ) ) {
            $category_keys = [ 'briefing_register', 'closing_register', 'appointments' ];
            foreach ( $category_keys as $cat_key ) {
                $enabled = ! empty( $_POST[ "category_{$cat_key}" ] ) ? '1' : '0';
                update_option( "eprocurement_category_{$cat_key}", $enabled );
            }
        }

        // Notification settings — only update if at least one notify key is present
        if ( isset( $_POST['notify_new_bid'] )
          || isset( $_POST['notify_query'] )
          || isset( $_POST['notify_reply'] )
          || isset( $_POST['notify_status'] ) ) {
            $notifications = [
                'new_bid_notify_bidders'  => ! empty( $_POST['notify_new_bid'] ),
                'query_notify_contact'    => ! empty( $_POST['notify_query'] ),
                'reply_notify_bidder'     => ! empty( $_POST['notify_reply'] ),
                'status_change_notify'    => ! empty( $_POST['notify_status'] ),
            ];
            update_option( 'eprocurement_notification_settings', wp_json_encode( $notifications ) );
        }

        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'eprocurement' ) ] );
    }

    /**
     * Upload a bid document.
     */
    public function ajax_upload_supporting_doc(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_upload_documents' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No file provided.', 'eprocurement' ) ] );
        }

        $validation = Eprocurement_Storage_Interface::validate_file( $_FILES['file'] );
        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( [ 'message' => $validation->get_error_message() ] );
        }

        $storage = Eprocurement_Storage_Interface::get_active_provider();
        if ( ! $storage ) {
            wp_send_json_error( [ 'message' => __( 'Cloud storage not configured.', 'eprocurement' ) ] );
        }

        try {
            $result = $storage->upload(
                $_FILES['file']['tmp_name'],
                $_FILES['file']['name'],
                'documents'
            );

            $documents = new Eprocurement_Documents();
            $doc_id    = $documents->add_supporting_doc( [
                'document_id'    => absint( $_POST['document_id'] ?? 0 ),
                'file_name'      => $_FILES['file']['name'],
                'file_size'      => $_FILES['file']['size'],
                'file_type'      => $_FILES['file']['type'],
                'cloud_provider' => $storage->get_provider_name(),
                'cloud_key'      => $result['cloud_key'],
                'cloud_url'      => $result['cloud_url'],
                'label'          => sanitize_text_field( $_POST['label'] ?? '' ),
                'sort_order'     => absint( $_POST['sort_order'] ?? 0 ),
            ] );

            wp_send_json_success( [
                'message' => __( 'File uploaded.', 'eprocurement' ),
                'id'      => $doc_id,
            ] );
        } catch ( \Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /**
     * Remove a bid document.
     */
    public function ajax_remove_supporting_doc(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_upload_documents' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $documents = new Eprocurement_Documents();
        $id        = absint( $_POST['id'] ?? 0 );

        if ( $documents->remove_supporting_doc( $id ) ) {
            wp_send_json_success( [ 'message' => __( 'File removed.', 'eprocurement' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to remove file.', 'eprocurement' ) ] );
        }
    }

    /**
     * Upload an SCM document.
     */
    public function ajax_upload_compliance_doc(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_manage_compliance' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No file provided.', 'eprocurement' ) ] );
        }

        $validation = Eprocurement_Storage_Interface::validate_file( $_FILES['file'] );
        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( [ 'message' => $validation->get_error_message() ] );
        }

        $storage = Eprocurement_Storage_Interface::get_active_provider();
        if ( ! $storage ) {
            wp_send_json_error( [ 'message' => __( 'Cloud storage not configured.', 'eprocurement' ) ] );
        }

        try {
            $result = $storage->upload(
                $_FILES['file']['tmp_name'],
                $_FILES['file']['name'],
                'compliance'
            );

            $compliance = new Eprocurement_Compliance_Docs();
            $doc_id     = $compliance->add( [
                'file_name'      => $_FILES['file']['name'],
                'file_size'      => $_FILES['file']['size'],
                'file_type'      => $_FILES['file']['type'],
                'cloud_provider' => $storage->get_provider_name(),
                'cloud_key'      => $result['cloud_key'],
                'cloud_url'      => $result['cloud_url'],
                'label'          => sanitize_text_field( $_POST['label'] ?? '' ),
                'description'    => sanitize_textarea_field( $_POST['description'] ?? '' ),
            ] );

            wp_send_json_success( [
                'message' => __( 'SCM document uploaded.', 'eprocurement' ),
                'id'      => $doc_id,
            ] );
        } catch ( \Exception $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }

    /**
     * Delete an SCM document.
     */
    public function ajax_delete_compliance_doc(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_manage_compliance' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $compliance = new Eprocurement_Compliance_Docs();
        $id         = absint( $_POST['id'] ?? 0 );

        if ( $compliance->delete( $id ) ) {
            wp_send_json_success( [ 'message' => __( 'SCM document deleted.', 'eprocurement' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to delete.', 'eprocurement' ) ] );
        }
    }

    /**
     * Export download log as CSV.
     */
    public function ajax_export_downloads(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_view_downloads' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'eprocurement' ), 403 );
        }

        $downloads   = new Eprocurement_Downloads();
        $document_id = absint( $_GET['document_id'] ?? 0 );

        $downloads->export_csv( $document_id );
    }

    /**
     * Test cloud storage connection.
     */
    public function ajax_test_storage(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! is_super_admin() ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $storage = Eprocurement_Storage_Interface::get_active_provider();

        if ( ! $storage ) {
            wp_send_json_error( [ 'message' => __( 'No cloud storage provider configured.', 'eprocurement' ) ] );
        }

        if ( $storage->test_connection() ) {
            wp_send_json_success( [ 'message' => __( 'Connection successful!', 'eprocurement' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Connection failed. Please check your credentials.', 'eprocurement' ) ] );
        }
    }

    /**
     * Resolve (close) a message thread.
     */
    public function ajax_resolve_thread(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_reply_threads' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $messaging = new Eprocurement_Messaging();
        $thread_id = absint( $_POST['thread_id'] ?? 0 );

        if ( $messaging->close_thread( $thread_id ) ) {
            wp_send_json_success( [ 'message' => __( 'Thread marked as resolved.', 'eprocurement' ) ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to resolve thread.', 'eprocurement' ) ] );
        }
    }

    /**
     * Change thread visibility (private → public) with notification.
     */
    public function ajax_change_thread_visibility(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_reply_threads' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $messaging  = new Eprocurement_Messaging();
        $thread_id  = absint( $_POST['thread_id'] ?? 0 );
        $visibility = sanitize_text_field( $_POST['visibility'] ?? '' );
        $reason     = wp_kses_post( wp_unslash( $_POST['reason'] ?? '' ) );

        if ( ! $thread_id || ! in_array( $visibility, [ 'public', 'private' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'eprocurement' ) ] );
        }

        $thread = Eprocurement_Database::get_by_id( 'threads', $thread_id );
        if ( ! $thread ) {
            wp_send_json_error( [ 'message' => __( 'Thread not found.', 'eprocurement' ) ] );
        }

        $old_visibility = $thread->visibility;

        if ( $old_visibility === $visibility ) {
            wp_send_json_error( [ 'message' => __( 'Thread visibility is already set to this value.', 'eprocurement' ) ] );
        }

        if ( ! $messaging->update_visibility( $thread_id, $visibility ) ) {
            wp_send_json_error( [ 'message' => __( 'Failed to update thread visibility.', 'eprocurement' ) ] );
        }

        // Notify the original bidder about the visibility change.
        do_action( 'eprocurement_visibility_changed', $thread_id, $old_visibility, $visibility, $reason );

        $label = $visibility === 'public'
            ? __( 'Thread made public.', 'eprocurement' )
            : __( 'Thread made private.', 'eprocurement' );

        wp_send_json_success( [ 'message' => $label ] );
    }

    /**
     * Resend verification email to a bidder.
     */
    public function ajax_resend_verification(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_view_bidders' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $user_id = absint( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid user.', 'eprocurement' ) ] );
        }

        $bidder = new Eprocurement_Bidder();
        $result = $bidder->resend_verification( $user_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => __( 'Verification email resent.', 'eprocurement' ) ] );
    }

    /**
     * Handle OAuth callbacks from cloud providers.
     */
    public function handle_oauth_callback(): void {
        if ( empty( $_GET['eproc_oauth_callback'] ) || empty( $_GET['code'] ) ) {
            return;
        }

        if ( ! is_super_admin() ) {
            return;
        }

        $provider  = sanitize_text_field( $_GET['eproc_oauth_callback'] );
        $auth_code = sanitize_text_field( $_GET['code'] );

        try {
            $storage = match ( $provider ) {
                'google_drive' => new Eprocurement_Google_Drive(),
                'onedrive'     => new Eprocurement_Onedrive(),
                'dropbox'      => new Eprocurement_Dropbox(),
                default        => null,
            };

            if ( $storage && method_exists( $storage, 'handle_oauth_callback' ) ) {
                $storage->handle_oauth_callback( $auth_code );

                wp_safe_redirect( admin_url( 'admin.php?page=eprocurement-settings&oauth_success=1' ) );
                exit;
            }
        } catch ( \Exception $e ) {
            wp_safe_redirect(
                admin_url( 'admin.php?page=eprocurement-settings&oauth_error=' . urlencode( $e->getMessage() ) )
            );
            exit;
        }
    }

    /**
     * Add a department to the managed list.
     */
    public function ajax_add_department(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'eproc_manage_contacts' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        $department = sanitize_text_field( $_POST['department'] ?? '' );
        if ( ! $department ) {
            wp_send_json_error( [ 'message' => __( 'Department name is required.', 'eprocurement' ) ] );
        }

        $departments = json_decode( get_option( 'eprocurement_departments', '[]' ), true );
        if ( ! is_array( $departments ) ) {
            $departments = [];
        }

        // Case-insensitive check for duplicates
        $lower = strtolower( $department );
        foreach ( $departments as $existing ) {
            if ( strtolower( $existing ) === $lower ) {
                wp_send_json_success( [ 'message' => __( 'Department already exists.', 'eprocurement' ) ] );
            }
        }

        $departments[] = $department;
        sort( $departments );
        update_option( 'eprocurement_departments', wp_json_encode( $departments ) );

        wp_send_json_success( [ 'message' => __( 'Department added.', 'eprocurement' ) ] );
    }

    /**
     * Parse a dd/mm/yyyy HH:mm date input into MySQL datetime, or return null.
     */
    private static function parse_date_input( string $raw ): ?string {
        $raw = sanitize_text_field( $raw );
        if ( $raw === '' ) {
            return null;
        }
        // Accept dd/mm/yyyy HH:mm
        $dt = \DateTime::createFromFormat( 'd/m/Y H:i', $raw );
        if ( $dt ) {
            return $dt->format( 'Y-m-d H:i:s' );
        }
        // Fallback: accept ISO datetime-local (Y-m-d\TH:i) for backwards compat
        $dt = \DateTime::createFromFormat( 'Y-m-d\TH:i', $raw );
        if ( $dt ) {
            return $dt->format( 'Y-m-d H:i:s' );
        }
        return null;
    }

    /**
     * Seed demo data.
     */
    public function ajax_seed_demo_data(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! is_super_admin() ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        require_once EPROC_PLUGIN_DIR . 'includes/class-demo-data.php';
        $result = Eprocurement_Demo_Data::seed();

        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
    }

    /**
     * Remove demo data.
     */
    public function ajax_remove_demo_data(): void {
        check_ajax_referer( 'eproc_admin_nonce', 'nonce' );

        if ( ! is_super_admin() ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'eprocurement' ) ] );
        }

        require_once EPROC_PLUGIN_DIR . 'includes/class-demo-data.php';
        $result = Eprocurement_Demo_Data::remove();

        if ( $result['success'] ) {
            wp_send_json_success( [ 'message' => $result['message'] ] );
        } else {
            wp_send_json_error( [ 'message' => $result['message'] ] );
        }
    }

    /**
     * Darken a hex color by a percentage.
     *
     * @param string $hex     6-digit hex colour (e.g. #8b1a2b).
     * @param int    $percent 0-100.
     */
    private static function darken_hex( string $hex, int $percent ): string {
        $hex = ltrim( $hex, '#' );
        $r   = max( 0, (int) round( hexdec( substr( $hex, 0, 2 ) ) * ( 1 - $percent / 100 ) ) );
        $g   = max( 0, (int) round( hexdec( substr( $hex, 2, 2 ) ) * ( 1 - $percent / 100 ) ) );
        $b   = max( 0, (int) round( hexdec( substr( $hex, 4, 2 ) ) * ( 1 - $percent / 100 ) ) );

        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }

    /**
     * Lighten a hex color by a percentage.
     *
     * @param string $hex     6-digit hex colour (e.g. #1a1a5e).
     * @param int    $percent 0-100.
     */
    private static function lighten_hex( string $hex, int $percent ): string {
        $hex = ltrim( $hex, '#' );
        $r   = min( 255, (int) round( hexdec( substr( $hex, 0, 2 ) ) + ( 255 - hexdec( substr( $hex, 0, 2 ) ) ) * $percent / 100 ) );
        $g   = min( 255, (int) round( hexdec( substr( $hex, 2, 2 ) ) + ( 255 - hexdec( substr( $hex, 2, 2 ) ) ) * $percent / 100 ) );
        $b   = min( 255, (int) round( hexdec( substr( $hex, 4, 2 ) ) + ( 255 - hexdec( substr( $hex, 4, 2 ) ) ) * $percent / 100 ) );

        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }
}
