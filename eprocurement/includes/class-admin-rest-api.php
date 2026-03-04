<?php
/**
 * Admin REST API — mirrors all wp-admin AJAX handlers as REST endpoints.
 *
 * All endpoints under eprocurement/v1/admin/ require authentication.
 * The frontend admin panel (at /tenders/manage/) uses these endpoints
 * via fetch() with X-WP-Nonce header.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Admin_Rest_Api {

    private const NAMESPACE = 'eprocurement/v1';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( 'rest_api_init', [ $this, 'register_cors' ] );
    }

    /**
     * Register CORS headers for cross-origin REST API access.
     *
     * Reads allowed origins from the `eprocurement_cors_origins` option.
     * When empty, CORS headers are not added (same-origin only).
     */
    public function register_cors(): void {
        $origins = get_option( 'eprocurement_cors_origins', '' );
        if ( empty( $origins ) ) {
            return;
        }

        // Parse comma-separated origins.
        $allowed = array_map( 'trim', explode( ',', $origins ) );
        $allowed = array_filter( $allowed );

        if ( empty( $allowed ) ) {
            return;
        }

        $request_origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Only add headers if the request origin is in the allowed list or wildcard is set.
        if ( ! in_array( '*', $allowed, true ) && ! in_array( $request_origin, $allowed, true ) ) {
            return;
        }

        $origin_header = in_array( '*', $allowed, true ) ? '*' : $request_origin;

        // Set CORS headers for all eprocurement REST routes.
        add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) use ( $origin_header ) {
            $route = $request->get_route();
            if ( strpos( $route, '/eprocurement/v1/' ) === 0 ) {
                header( 'Access-Control-Allow-Origin: ' . $origin_header );
                header( 'Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS' );
                header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
                header( 'Access-Control-Allow-Credentials: true' );
            }
            return $served;
        }, 10, 3 );

        // Handle OPTIONS preflight requests.
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if ( strpos( $uri, '/wp-json/eprocurement/v1/' ) !== false || strpos( $uri, '?rest_route=/eprocurement/v1/' ) !== false ) {
                header( 'Access-Control-Allow-Origin: ' . $origin_header );
                header( 'Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS' );
                header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
                header( 'Access-Control-Allow-Credentials: true' );
                header( 'Access-Control-Max-Age: 86400' );
                header( 'Content-Length: 0' );
                header( 'Content-Type: text/plain' );
                status_header( 204 );
                exit;
            }
        }
    }

    public function register_routes(): void {
        // --- Dashboard ---
        register_rest_route( self::NAMESPACE, '/admin/dashboard', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_dashboard' ],
            'permission_callback' => fn() => current_user_can( 'eproc_view_dashboard' ),
        ] );

        // --- Bids ---
        register_rest_route( self::NAMESPACE, '/admin/bids', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_bids' ],
                'permission_callback' => fn() => current_user_can( 'eproc_view_dashboard' ),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_bid' ],
                'permission_callback' => fn() => current_user_can( 'eproc_create_bids' ),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_bid' ],
                'permission_callback' => fn() => current_user_can( 'eproc_view_dashboard' ),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_bid' ],
                'permission_callback' => fn() => current_user_can( 'eproc_delete_bids' ),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<id>\d+)/status', [
            'methods'             => 'PATCH',
            'callback'            => [ $this, 'change_status' ],
            'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<id>\d+)/documents', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'upload_supporting_doc' ],
            'permission_callback' => fn() => current_user_can( 'eproc_upload_documents' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/documents/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'remove_supporting_doc' ],
            'permission_callback' => fn() => current_user_can( 'eproc_upload_documents' ),
        ] );

        // --- Contacts ---
        register_rest_route( self::NAMESPACE, '/admin/contacts', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_contacts' ],
                'permission_callback' => fn() => current_user_can( 'eproc_manage_contacts' ),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_contact' ],
                'permission_callback' => fn() => current_user_can( 'eproc_manage_contacts' ),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/contacts/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_contact' ],
            'permission_callback' => fn() => current_user_can( 'eproc_manage_contacts' ),
        ] );

        // --- Threads / Messages ---
        register_rest_route( self::NAMESPACE, '/admin/threads', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'list_threads' ],
            'permission_callback' => fn() => current_user_can( 'eproc_view_threads' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/threads/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_thread' ],
            'permission_callback' => fn() => current_user_can( 'eproc_view_threads' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/threads/(?P<id>\d+)/reply', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'reply_thread' ],
            'permission_callback' => fn() => current_user_can( 'eproc_reply_threads' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/threads/(?P<id>\d+)/resolve', [
            'methods'             => 'PATCH',
            'callback'            => [ $this, 'resolve_thread' ],
            'permission_callback' => fn() => current_user_can( 'eproc_reply_threads' ),
        ] );

        // --- SCM Documents ---
        register_rest_route( self::NAMESPACE, '/admin/scm-docs', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_scm_docs' ],
                'permission_callback' => fn() => current_user_can( 'eproc_manage_compliance' ),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'upload_scm_doc' ],
                'permission_callback' => fn() => current_user_can( 'eproc_manage_compliance' ),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/scm-docs/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_scm_doc' ],
            'permission_callback' => fn() => current_user_can( 'eproc_manage_compliance' ),
        ] );

        // --- Bidders ---
        register_rest_route( self::NAMESPACE, '/admin/bidders', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'list_bidders' ],
            'permission_callback' => fn() => current_user_can( 'eproc_view_bidders' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/bidders/export', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'export_bidders' ],
            'permission_callback' => fn() => current_user_can( 'eproc_view_bidders' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/bidders/(?P<id>\d+)/resend', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'resend_verification' ],
            'permission_callback' => fn() => current_user_can( 'eproc_view_bidders' ),
        ] );

        // --- Downloads ---
        register_rest_route( self::NAMESPACE, '/admin/downloads', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'list_downloads' ],
            'permission_callback' => fn() => current_user_can( 'eproc_view_downloads' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/downloads/export', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'export_downloads' ],
            'permission_callback' => fn() => current_user_can( 'eproc_view_downloads' ),
        ] );

        // --- Departments ---
        register_rest_route( self::NAMESPACE, '/admin/departments', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_departments' ],
                'permission_callback' => fn() => current_user_can( 'eproc_manage_contacts' ),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'add_department' ],
                'permission_callback' => fn() => current_user_can( 'eproc_manage_contacts' ),
            ],
        ] );

        // --- Settings (Super Admin) ---
        register_rest_route( self::NAMESPACE, '/admin/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_settings' ],
                'permission_callback' => fn() => is_super_admin(),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_settings' ],
                'permission_callback' => fn() => is_super_admin(),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/settings/test-storage', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'test_storage' ],
            'permission_callback' => fn() => is_super_admin(),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/settings/test-smtp', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'test_smtp' ],
            'permission_callback' => fn() => is_super_admin(),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/settings/test-external-db', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'test_external_db' ],
            'permission_callback' => fn() => is_super_admin(),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/settings/sync-external-db', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'sync_external_db' ],
            'permission_callback' => fn() => is_super_admin(),
        ] );

        // --- User Management (Super Admin) ---
        register_rest_route( self::NAMESPACE, '/admin/users', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_users' ],
                'permission_callback' => fn() => is_super_admin(),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'create_user' ],
                'permission_callback' => fn() => is_super_admin(),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/users/(?P<id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [ $this, 'update_user' ],
                'permission_callback' => fn() => is_super_admin(),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_user' ],
                'permission_callback' => fn() => is_super_admin(),
            ],
        ] );
    }

    // =========================================================================
    // Dashboard
    // =========================================================================

    public function get_dashboard( \WP_REST_Request $request ): \WP_REST_Response {
        $documents = new Eprocurement_Documents();
        $messaging = new Eprocurement_Messaging();
        $downloads = new Eprocurement_Downloads();

        $counts = $documents->get_status_counts();

        // Today's downloads
        global $wpdb;
        $dl_table = Eprocurement_Database::table( 'downloads' );
        $today    = current_time( 'Y-m-d' );
        $today_downloads = (int) $wpdb->get_var(
            $wpdb->prepare( "SELECT COUNT(*) FROM {$dl_table} WHERE DATE(downloaded_at) = %s", $today )
        );

        // Most downloaded open document
        $doc_table = Eprocurement_Database::table( 'documents' );
        $most_downloaded = $wpdb->get_row(
            "SELECT d.title, COUNT(dl.id) as download_count
             FROM {$dl_table} dl
             JOIN {$doc_table} d ON dl.document_id = d.id
             WHERE d.status = 'open'
             GROUP BY dl.document_id
             ORDER BY download_count DESC
             LIMIT 1"
        );

        // Recent bids
        $recent_bids = $documents->list( [
            'per_page'             => 5,
            'page'                 => 1,
            'include_all_statuses' => true,
            'include_all_categories' => true,
            'orderby'              => 'created_at',
            'order'                => 'DESC',
        ] );

        // Unread messages
        $unread = $messaging->get_unread_count( get_current_user_id() );

        // Recent threads
        $recent_threads = $messaging->get_admin_inbox( [
            'per_page' => 5,
            'page'     => 1,
        ] );

        return new \WP_REST_Response( [
            'status_counts'    => $counts,
            'today_downloads'  => $today_downloads,
            'most_downloaded'  => $most_downloaded ? [
                'title' => $most_downloaded->title,
                'count' => (int) $most_downloaded->download_count,
            ] : null,
            'recent_bids'      => $recent_bids['items'],
            'unread_messages'  => $unread,
            'recent_threads'   => $recent_threads['items'],
            'total_bids'       => array_sum( $counts ),
        ] );
    }

    // =========================================================================
    // Bids
    // =========================================================================

    public function list_bids( \WP_REST_Request $request ): \WP_REST_Response {
        $documents = new Eprocurement_Documents();
        $result    = $documents->list( [
            'status'               => $request->get_param( 'status' ) ?? '',
            'category'             => $request->get_param( 'category' ) ?? 'bid',
            'search'               => $request->get_param( 'search' ) ?? '',
            'per_page'             => absint( $request->get_param( 'per_page' ) ?? 20 ),
            'page'                 => absint( $request->get_param( 'page' ) ?? 1 ),
            'orderby'              => $request->get_param( 'orderby' ) ?? 'created_at',
            'order'                => $request->get_param( 'order' ) ?? 'DESC',
            'include_all_statuses' => true,
        ] );

        return new \WP_REST_Response( $result );
    }

    public function get_bid( \WP_REST_Request $request ): \WP_REST_Response {
        $documents = new Eprocurement_Documents();
        $bid       = $documents->get( (int) $request['id'] );

        if ( ! $bid ) {
            return new \WP_REST_Response( [ 'message' => 'Bid not found.' ], 404 );
        }

        $bid->supporting_docs = $documents->get_supporting_docs( (int) $bid->id );

        // Contacts
        $contacts = new Eprocurement_Contact_Persons();
        $bid->scm_contact       = $bid->scm_contact_id ? $contacts->get( (int) $bid->scm_contact_id ) : null;
        $bid->technical_contact  = $bid->technical_contact_id ? $contacts->get( (int) $bid->technical_contact_id ) : null;

        return new \WP_REST_Response( $bid );
    }

    public function save_bid( \WP_REST_Request $request ): \WP_REST_Response {
        $documents = new Eprocurement_Documents();
        $id        = absint( $request->get_param( 'id' ) ?? 0 );

        $category = sanitize_text_field( $request->get_param( 'category' ) ?? 'bid' );
        if ( ! in_array( $category, [ 'bid', 'briefing_register', 'closing_register', 'appointments' ], true ) ) {
            $category = 'bid';
        }

        $data = [
            'bid_number'  => sanitize_text_field( $request->get_param( 'bid_number' ) ?? '' ),
            'title'       => sanitize_text_field( $request->get_param( 'title' ) ?? '' ),
            'description' => wp_kses_post( $request->get_param( 'description' ) ?? '' ),
            'category'    => $category,
        ];

        if ( $category === 'bid' ) {
            $data['scm_contact_id']       = absint( $request->get_param( 'scm_contact_id' ) ?? 0 ) ?: null;
            $data['technical_contact_id'] = absint( $request->get_param( 'technical_contact_id' ) ?? 0 ) ?: null;
            $data['opening_date']         = self::parse_date( $request->get_param( 'opening_date' ) ?? '' );
            $data['briefing_date']        = self::parse_date( $request->get_param( 'briefing_date' ) ?? '' );
            $data['closing_date']         = self::parse_date( $request->get_param( 'closing_date' ) ?? '' );
        }

        // Validate bid number uniqueness
        if ( $data['bid_number'] ) {
            $existing = $documents->get_by_bid_number( $data['bid_number'], $category );
            if ( $existing && (int) $existing->id !== $id ) {
                return new \WP_REST_Response( [
                    'message' => 'A bid with this number already exists in this category.',
                ], 400 );
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
            return new \WP_REST_Response( [ 'message' => 'Failed to save bid.' ], 500 );
        }

        // Associate pending docs
        $pending = $request->get_param( 'pending_doc_ids' );
        if ( $pending && $doc_id ) {
            $pending_ids = array_filter( array_map( 'absint', explode( ',', $pending ) ) );
            if ( ! empty( $pending_ids ) ) {
                global $wpdb;
                $table        = Eprocurement_Database::table( 'supporting_docs' );
                $placeholders = implode( ',', array_fill( 0, count( $pending_ids ), '%d' ) );
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET document_id = %d WHERE id IN ({$placeholders}) AND document_id = 0",
                        $doc_id,
                        ...$pending_ids
                    )
                );
            }
        }

        return new \WP_REST_Response( [ 'message' => 'Bid saved.', 'id' => $doc_id ] );
    }

    public function delete_bid( \WP_REST_Request $request ): \WP_REST_Response {
        $documents = new Eprocurement_Documents();
        if ( $documents->delete( (int) $request['id'] ) ) {
            return new \WP_REST_Response( [ 'message' => 'Bid deleted.' ] );
        }
        return new \WP_REST_Response( [ 'message' => 'Failed to delete bid.' ], 500 );
    }

    public function change_status( \WP_REST_Request $request ): \WP_REST_Response {
        $documents  = new Eprocurement_Documents();
        $new_status = sanitize_text_field( $request->get_param( 'status' ) ?? '' );

        if ( $documents->transition_status( (int) $request['id'], $new_status ) ) {
            return new \WP_REST_Response( [
                'message' => sprintf( 'Status changed to %s.', strtoupper( $new_status ) ),
            ] );
        }
        return new \WP_REST_Response( [ 'message' => 'Invalid status transition.' ], 400 );
    }

    public function upload_supporting_doc( \WP_REST_Request $request ): \WP_REST_Response {
        $files = $request->get_file_params();
        if ( empty( $files['file'] ) ) {
            return new \WP_REST_Response( [ 'message' => 'No file provided.' ], 400 );
        }

        $validation = Eprocurement_Storage_Interface::validate_file( $files['file'] );
        if ( is_wp_error( $validation ) ) {
            return new \WP_REST_Response( [ 'message' => $validation->get_error_message() ], 400 );
        }

        $storage = Eprocurement_Storage_Interface::get_active_provider();
        if ( ! $storage ) {
            return new \WP_REST_Response( [ 'message' => 'Cloud storage not configured.' ], 500 );
        }

        try {
            $result = $storage->upload( $files['file']['tmp_name'], $files['file']['name'], 'documents' );

            $documents = new Eprocurement_Documents();
            $doc_id    = $documents->add_supporting_doc( [
                'document_id'    => (int) $request['id'],
                'file_name'      => $files['file']['name'],
                'file_size'      => $files['file']['size'],
                'file_type'      => $files['file']['type'],
                'cloud_provider' => $storage->get_provider_name(),
                'cloud_key'      => $result['cloud_key'],
                'cloud_url'      => $result['cloud_url'],
                'label'          => sanitize_text_field( $request->get_param( 'label' ) ?? '' ),
                'sort_order'     => absint( $request->get_param( 'sort_order' ) ?? 0 ),
            ] );

            return new \WP_REST_Response( [ 'message' => 'File uploaded.', 'id' => $doc_id ] );
        } catch ( \Exception $e ) {
            return new \WP_REST_Response( [ 'message' => $e->getMessage() ], 500 );
        }
    }

    public function remove_supporting_doc( \WP_REST_Request $request ): \WP_REST_Response {
        $documents = new Eprocurement_Documents();
        if ( $documents->remove_supporting_doc( (int) $request['id'] ) ) {
            return new \WP_REST_Response( [ 'message' => 'File removed.' ] );
        }
        return new \WP_REST_Response( [ 'message' => 'Failed to remove file.' ], 500 );
    }

    // =========================================================================
    // Contacts
    // =========================================================================

    public function list_contacts( \WP_REST_Request $request ): \WP_REST_Response {
        $contacts = new Eprocurement_Contact_Persons();
        $type     = $request->get_param( 'type' ) ?? '';
        $all      = $contacts->get_all( $type );

        return new \WP_REST_Response( $all );
    }

    public function save_contact( \WP_REST_Request $request ): \WP_REST_Response {
        $contacts = new Eprocurement_Contact_Persons();
        $id       = absint( $request->get_param( 'id' ) ?? 0 );

        $data = [
            'user_id'    => absint( $request->get_param( 'user_id' ) ?? 0 ) ?: null,
            'type'       => sanitize_text_field( $request->get_param( 'type' ) ?? 'scm' ),
            'name'       => sanitize_text_field( $request->get_param( 'name' ) ?? '' ),
            'phone'      => sanitize_text_field( $request->get_param( 'phone' ) ?? '' ),
            'email'      => sanitize_email( $request->get_param( 'email' ) ?? '' ),
            'department' => sanitize_text_field( $request->get_param( 'department' ) ?? '' ),
        ];

        // Auto-create WP user for new contacts
        if ( $id === 0 && empty( $data['user_id'] ) && $data['email'] ) {
            $existing_user = get_user_by( 'email', $data['email'] );
            if ( $existing_user ) {
                $data['user_id'] = $existing_user->ID;
            } else {
                $username = sanitize_user( strtolower( explode( '@', $data['email'] )[0] ), true );
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
            return new \WP_REST_Response( [ 'message' => 'Failed to save contact.' ], 500 );
        }

        return new \WP_REST_Response( [ 'message' => 'Contact saved.' ] );
    }

    public function delete_contact( \WP_REST_Request $request ): \WP_REST_Response {
        $contacts = new Eprocurement_Contact_Persons();
        if ( $contacts->delete( (int) $request['id'] ) ) {
            return new \WP_REST_Response( [ 'message' => 'Contact deleted.' ] );
        }
        return new \WP_REST_Response( [
            'message' => 'Cannot delete. Contact is assigned to active bids.',
        ], 400 );
    }

    // =========================================================================
    // Threads / Messages
    // =========================================================================

    public function list_threads( \WP_REST_Request $request ): \WP_REST_Response {
        $messaging = new Eprocurement_Messaging();
        $result    = $messaging->get_admin_inbox( [
            'per_page' => absint( $request->get_param( 'per_page' ) ?? 20 ),
            'page'     => absint( $request->get_param( 'page' ) ?? 1 ),
        ] );

        return new \WP_REST_Response( $result );
    }

    public function get_thread( \WP_REST_Request $request ): \WP_REST_Response {
        $messaging = new Eprocurement_Messaging();
        $thread    = $messaging->get_thread( (int) $request['id'], get_current_user_id() );

        if ( ! $thread ) {
            return new \WP_REST_Response( [ 'message' => 'Thread not found.' ], 404 );
        }

        $thread->messages = $messaging->get_messages( (int) $request['id'] );

        // Load attachments for each message
        foreach ( $thread->messages as $msg ) {
            $msg->attachments = $messaging->get_attachments( (int) $msg->id );
        }

        // Mark as read
        $messaging->mark_thread_read( (int) $request['id'], get_current_user_id() );

        return new \WP_REST_Response( $thread );
    }

    public function reply_thread( \WP_REST_Request $request ): \WP_REST_Response {
        $messaging  = new Eprocurement_Messaging();
        $thread_id  = (int) $request['id'];
        $message    = wp_kses_post( $request->get_param( 'message' ) ?? '' );

        if ( ! $message ) {
            return new \WP_REST_Response( [ 'message' => 'Message cannot be empty.' ], 400 );
        }

        $message_id = $messaging->add_message( $thread_id, get_current_user_id(), $message );
        if ( ! $message_id ) {
            return new \WP_REST_Response( [ 'message' => 'Failed to send reply.' ], 500 );
        }

        // Handle file attachment
        $files = $request->get_file_params();
        if ( ! empty( $files['attachment'] ) && $files['attachment']['error'] === UPLOAD_ERR_OK ) {
            $file     = $files['attachment'];
            $max_size = 5 * 1024 * 1024;
            $allowed  = [ 'application/pdf', 'application/msword',
                          'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                          'image/jpeg', 'image/png' ];

            if ( $file['size'] <= $max_size ) {
                $finfo     = finfo_open( FILEINFO_MIME_TYPE );
                $mime_type = finfo_file( $finfo, $file['tmp_name'] );
                finfo_close( $finfo );

                if ( in_array( $mime_type, $allowed, true ) ) {
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
                            // Reply sent, attachment failed
                        }
                    }
                }
            }
        }

        do_action( 'eprocurement_reply_posted', $thread_id, $message_id );

        return new \WP_REST_Response( [ 'message' => 'Reply sent.' ] );
    }

    public function resolve_thread( \WP_REST_Request $request ): \WP_REST_Response {
        $messaging = new Eprocurement_Messaging();
        if ( $messaging->close_thread( (int) $request['id'] ) ) {
            return new \WP_REST_Response( [ 'message' => 'Thread resolved.' ] );
        }
        return new \WP_REST_Response( [ 'message' => 'Failed to resolve thread.' ], 500 );
    }

    // =========================================================================
    // SCM Documents
    // =========================================================================

    public function list_scm_docs( \WP_REST_Request $request ): \WP_REST_Response {
        $compliance = new Eprocurement_Compliance_Docs();
        return new \WP_REST_Response( $compliance->get_all() );
    }

    public function upload_scm_doc( \WP_REST_Request $request ): \WP_REST_Response {
        $files = $request->get_file_params();
        if ( empty( $files['file'] ) ) {
            return new \WP_REST_Response( [ 'message' => 'No file provided.' ], 400 );
        }

        $validation = Eprocurement_Storage_Interface::validate_file( $files['file'] );
        if ( is_wp_error( $validation ) ) {
            return new \WP_REST_Response( [ 'message' => $validation->get_error_message() ], 400 );
        }

        $storage = Eprocurement_Storage_Interface::get_active_provider();
        if ( ! $storage ) {
            return new \WP_REST_Response( [ 'message' => 'Cloud storage not configured.' ], 500 );
        }

        try {
            $result = $storage->upload( $files['file']['tmp_name'], $files['file']['name'], 'compliance' );

            $compliance = new Eprocurement_Compliance_Docs();
            $doc_id     = $compliance->add( [
                'file_name'      => $files['file']['name'],
                'file_size'      => $files['file']['size'],
                'file_type'      => $files['file']['type'],
                'cloud_provider' => $storage->get_provider_name(),
                'cloud_key'      => $result['cloud_key'],
                'cloud_url'      => $result['cloud_url'],
                'label'          => sanitize_text_field( $request->get_param( 'label' ) ?? '' ),
                'description'    => sanitize_textarea_field( $request->get_param( 'description' ) ?? '' ),
            ] );

            return new \WP_REST_Response( [ 'message' => 'SCM document uploaded.', 'id' => $doc_id ] );
        } catch ( \Exception $e ) {
            return new \WP_REST_Response( [ 'message' => $e->getMessage() ], 500 );
        }
    }

    public function delete_scm_doc( \WP_REST_Request $request ): \WP_REST_Response {
        $compliance = new Eprocurement_Compliance_Docs();
        if ( $compliance->delete( (int) $request['id'] ) ) {
            return new \WP_REST_Response( [ 'message' => 'SCM document deleted.' ] );
        }
        return new \WP_REST_Response( [ 'message' => 'Failed to delete.' ], 500 );
    }

    // =========================================================================
    // Bidders
    // =========================================================================

    public function list_bidders( \WP_REST_Request $request ): \WP_REST_Response {
        $bidder = new Eprocurement_Bidder();
        $result = $bidder->get_all_bidders( [
            'per_page' => absint( $request->get_param( 'per_page' ) ?? 20 ),
            'page'     => absint( $request->get_param( 'page' ) ?? 1 ),
            'search'   => $request->get_param( 'search' ) ?? '',
        ] );

        return new \WP_REST_Response( $result );
    }

    public function export_bidders( \WP_REST_Request $request ): \WP_REST_Response {
        // CSV export needs to write headers directly
        $bidder = new Eprocurement_Bidder();
        $all    = $bidder->get_all_bidders( [ 'per_page' => 9999 ] );

        $rows = [];
        foreach ( $all['items'] as $b ) {
            $user = get_userdata( $b->user_id );
            $rows[] = [
                'email'        => $user ? $user->user_email : '',
                'display_name' => $user ? $user->display_name : '',
                'company'      => $b->company_name ?? '',
                'reg_number'   => $b->company_reg ?? '',
                'phone'        => $b->phone ?? '',
                'verified'     => $b->verified ? 'Yes' : 'No',
                'registered'   => $b->created_at ?? '',
            ];
        }

        return new \WP_REST_Response( [ 'data' => $rows ] );
    }

    public function resend_verification( \WP_REST_Request $request ): \WP_REST_Response {
        $bidder = new Eprocurement_Bidder();
        $result = $bidder->resend_verification( (int) $request['id'] );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return new \WP_REST_Response( [ 'message' => 'Verification email resent.' ] );
    }

    // =========================================================================
    // Downloads
    // =========================================================================

    public function list_downloads( \WP_REST_Request $request ): \WP_REST_Response {
        $downloads   = new Eprocurement_Downloads();
        $document_id = absint( $request->get_param( 'document_id' ) ?? 0 );

        $result = $downloads->get_log( $document_id, [
            'per_page' => absint( $request->get_param( 'per_page' ) ?? 20 ),
            'page'     => absint( $request->get_param( 'page' ) ?? 1 ),
        ] );

        return new \WP_REST_Response( $result );
    }

    public function export_downloads( \WP_REST_Request $request ): \WP_REST_Response {
        $downloads   = new Eprocurement_Downloads();
        $document_id = absint( $request->get_param( 'document_id' ) ?? 0 );
        $all         = $downloads->get_log( $document_id, [ 'per_page' => 99999 ] );

        return new \WP_REST_Response( [ 'data' => $all['items'] ] );
    }

    // =========================================================================
    // Departments
    // =========================================================================

    public function list_departments( \WP_REST_Request $request ): \WP_REST_Response {
        $departments = json_decode( get_option( 'eprocurement_departments', '[]' ), true );
        return new \WP_REST_Response( is_array( $departments ) ? $departments : [] );
    }

    public function add_department( \WP_REST_Request $request ): \WP_REST_Response {
        $department = sanitize_text_field( $request->get_param( 'department' ) ?? '' );
        if ( ! $department ) {
            return new \WP_REST_Response( [ 'message' => 'Department name is required.' ], 400 );
        }

        $departments = json_decode( get_option( 'eprocurement_departments', '[]' ), true );
        if ( ! is_array( $departments ) ) {
            $departments = [];
        }

        $lower = strtolower( $department );
        foreach ( $departments as $existing ) {
            if ( strtolower( $existing ) === $lower ) {
                return new \WP_REST_Response( [ 'message' => 'Department already exists.' ] );
            }
        }

        $departments[] = $department;
        sort( $departments );
        update_option( 'eprocurement_departments', wp_json_encode( $departments ) );

        return new \WP_REST_Response( [ 'message' => 'Department added.' ] );
    }

    // =========================================================================
    // Settings
    // =========================================================================

    public function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $notifications = json_decode( get_option( 'eprocurement_notification_settings', '{}' ), true );

        return new \WP_REST_Response( [
            'cloud_provider'              => get_option( 'eprocurement_cloud_provider', '' ),
            'closed_bid_retention_days'   => get_option( 'eprocurement_closed_bid_retention_days', '' ),
            'compliance_section_title'    => Eprocurement_Compliance_Docs::get_section_title(),
            'frontend_page_slug'          => get_option( 'eprocurement_frontend_page_slug', 'tenders' ),
            'category_briefing_register'  => get_option( 'eprocurement_category_briefing_register', '0' ),
            'category_closing_register'   => get_option( 'eprocurement_category_closing_register', '0' ),
            'category_appointments'       => get_option( 'eprocurement_category_appointments', '0' ),
            'notifications'               => $notifications ?: [],
            'smtp_configured'             => ! empty( get_option( 'eprocurement_smtp_settings' ) ),
            'external_db_configured'      => ! empty( get_option( 'eprocurement_external_db_settings' ) ),
        ] );
    }

    public function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $body = $request->get_json_params();

        if ( isset( $body['cloud_provider'] ) ) {
            update_option( 'eprocurement_cloud_provider', sanitize_text_field( $body['cloud_provider'] ) );
        }

        if ( ! empty( $body['cloud_credentials'] ) && is_array( $body['cloud_credentials'] ) ) {
            $creds     = array_map( 'sanitize_text_field', $body['cloud_credentials'] );
            $encrypted = Eprocurement_Storage_Interface::encrypt( wp_json_encode( $creds ) );
            update_option( 'eprocurement_cloud_credentials', $encrypted );
        }

        if ( isset( $body['closed_bid_retention_days'] ) ) {
            update_option( 'eprocurement_closed_bid_retention_days', sanitize_text_field( $body['closed_bid_retention_days'] ) );
        }

        if ( isset( $body['compliance_section_title'] ) ) {
            Eprocurement_Compliance_Docs::set_section_title( sanitize_text_field( $body['compliance_section_title'] ) );
        }

        if ( isset( $body['frontend_page_slug'] ) ) {
            update_option( 'eprocurement_frontend_page_slug', sanitize_title( $body['frontend_page_slug'] ) );
        }

        $category_keys = [ 'briefing_register', 'closing_register', 'appointments' ];
        foreach ( $category_keys as $cat_key ) {
            if ( isset( $body[ "category_{$cat_key}" ] ) ) {
                update_option( "eprocurement_category_{$cat_key}", $body[ "category_{$cat_key}" ] ? '1' : '0' );
            }
        }

        if ( isset( $body['notifications'] ) && is_array( $body['notifications'] ) ) {
            update_option( 'eprocurement_notification_settings', wp_json_encode( $body['notifications'] ) );
        }

        // SMTP settings
        if ( isset( $body['smtp'] ) && is_array( $body['smtp'] ) ) {
            $smtp_data = array_map( 'sanitize_text_field', $body['smtp'] );
            $encrypted = Eprocurement_Storage_Interface::encrypt( wp_json_encode( $smtp_data ) );
            update_option( 'eprocurement_smtp_settings', $encrypted );
        }

        // External DB settings
        if ( isset( $body['external_db'] ) && is_array( $body['external_db'] ) ) {
            $db_data   = array_map( 'sanitize_text_field', $body['external_db'] );
            $encrypted = Eprocurement_Storage_Interface::encrypt( wp_json_encode( $db_data ) );
            update_option( 'eprocurement_external_db_settings', $encrypted );
        }

        // CORS allowed origins
        if ( isset( $body['cors_origins'] ) ) {
            update_option( 'eprocurement_cors_origins', sanitize_text_field( $body['cors_origins'] ) );
        }

        return new \WP_REST_Response( [ 'message' => 'Settings saved.' ] );
    }

    public function test_storage( \WP_REST_Request $request ): \WP_REST_Response {
        $storage = Eprocurement_Storage_Interface::get_active_provider();
        if ( ! $storage ) {
            return new \WP_REST_Response( [ 'message' => 'No cloud storage configured.' ], 400 );
        }

        if ( $storage->test_connection() ) {
            return new \WP_REST_Response( [ 'message' => 'Connection successful!' ] );
        }
        return new \WP_REST_Response( [ 'message' => 'Connection failed.' ], 400 );
    }

    public function test_smtp( \WP_REST_Request $request ): \WP_REST_Response {
        $smtp = new Eprocurement_Smtp();
        $result = $smtp->send_test_email( $request->get_param( 'to' ) ?? '' );

        if ( $result === true ) {
            return new \WP_REST_Response( [ 'message' => 'Test email sent successfully!' ] );
        }
        return new \WP_REST_Response( [ 'message' => $result ], 400 );
    }

    public function test_external_db( \WP_REST_Request $request ): \WP_REST_Response {
        $ext_db = new Eprocurement_External_Db();
        $result = $ext_db->test_connection();

        if ( $result === true ) {
            return new \WP_REST_Response( [ 'message' => 'Connection successful!' ] );
        }
        return new \WP_REST_Response( [ 'message' => $result ], 400 );
    }

    public function sync_external_db( \WP_REST_Request $request ): \WP_REST_Response {
        $ext_db = new Eprocurement_External_Db();
        $result = $ext_db->sync_users();

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [ 'message' => $result->get_error_message() ], 400 );
        }

        return new \WP_REST_Response( [
            'message'  => 'Sync complete.',
            'created'  => $result['created'],
            'updated'  => $result['updated'],
            'skipped'  => $result['skipped'],
        ] );
    }

    // =========================================================================
    // User Management
    // =========================================================================

    public function list_users( \WP_REST_Request $request ): \WP_REST_Response {
        $eproc_roles = [
            'eprocurement_scm_manager',
            'eprocurement_scm_official',
            'eprocurement_unit_manager',
        ];

        $args = [
            'role__in'   => $eproc_roles,
            'number'     => absint( $request->get_param( 'per_page' ) ?? 50 ),
            'paged'      => absint( $request->get_param( 'page' ) ?? 1 ),
            'orderby'    => 'display_name',
            'order'      => 'ASC',
        ];

        $search = $request->get_param( 'search' ) ?? '';
        if ( $search ) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
        }

        $query = new \WP_User_Query( $args );
        $users = [];

        foreach ( $query->get_results() as $user ) {
            $users[] = [
                'id'           => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'role'         => array_values( array_intersect( $user->roles, $eproc_roles ) )[0] ?? '',
                'registered'   => $user->user_registered,
            ];
        }

        return new \WP_REST_Response( [
            'items' => $users,
            'total' => $query->get_total(),
        ] );
    }

    public function create_user( \WP_REST_Request $request ): \WP_REST_Response {
        $email = sanitize_email( $request->get_param( 'email' ) ?? '' );
        $name  = sanitize_text_field( $request->get_param( 'display_name' ) ?? '' );
        $role  = sanitize_text_field( $request->get_param( 'role' ) ?? '' );

        $valid_roles = [ 'eprocurement_scm_manager', 'eprocurement_scm_official', 'eprocurement_unit_manager' ];
        if ( ! in_array( $role, $valid_roles, true ) ) {
            return new \WP_REST_Response( [ 'message' => 'Invalid role.' ], 400 );
        }

        if ( ! $email || ! is_email( $email ) ) {
            return new \WP_REST_Response( [ 'message' => 'Valid email required.' ], 400 );
        }

        if ( email_exists( $email ) ) {
            return new \WP_REST_Response( [ 'message' => 'A user with this email already exists.' ], 400 );
        }

        $username = sanitize_user( strtolower( explode( '@', $email )[0] ), true );
        if ( username_exists( $username ) ) {
            $username .= wp_rand( 100, 999 );
        }

        $user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password( 16 ),
            'display_name' => $name ?: $username,
            'first_name'   => explode( ' ', $name )[0],
            'role'         => $role,
        ] );

        if ( is_wp_error( $user_id ) ) {
            return new \WP_REST_Response( [ 'message' => $user_id->get_error_message() ], 400 );
        }

        wp_new_user_notification( $user_id, null, 'user' );

        return new \WP_REST_Response( [ 'message' => 'User created.', 'id' => $user_id ] );
    }

    public function update_user( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id = (int) $request['id'];
        $user    = get_userdata( $user_id );

        if ( ! $user ) {
            return new \WP_REST_Response( [ 'message' => 'User not found.' ], 404 );
        }

        $role = sanitize_text_field( $request->get_param( 'role' ) ?? '' );
        $valid_roles = [ 'eprocurement_scm_manager', 'eprocurement_scm_official', 'eprocurement_unit_manager' ];

        if ( $role && in_array( $role, $valid_roles, true ) ) {
            $user->set_role( $role );
        }

        $name = sanitize_text_field( $request->get_param( 'display_name' ) ?? '' );
        if ( $name ) {
            wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );
        }

        return new \WP_REST_Response( [ 'message' => 'User updated.' ] );
    }

    public function delete_user( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id = (int) $request['id'];

        if ( is_super_admin( $user_id ) ) {
            return new \WP_REST_Response( [ 'message' => 'Cannot delete Super Admin.' ], 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        if ( wp_delete_user( $user_id ) ) {
            return new \WP_REST_Response( [ 'message' => 'User deleted.' ] );
        }

        return new \WP_REST_Response( [ 'message' => 'Failed to delete user.' ], 500 );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private static function parse_date( string $raw ): ?string {
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
        // Accept ISO format
        $dt = \DateTime::createFromFormat( 'Y-m-d H:i:s', $raw );
        if ( $dt ) {
            return $dt->format( 'Y-m-d H:i:s' );
        }
        return null;
    }
}
