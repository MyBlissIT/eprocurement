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
     *
     * Security notes (fix H-05 + M-06):
     *   - Wildcard `*` cannot be combined with credentials per CORS spec.
     *     When `*` is configured, we echo the request Origin back and skip
     *     the Allow-Credentials header, forcing unauthenticated CORS only.
     *   - The Origin header is sanitised and validated as a URL before use
     *     to prevent CRLF/header-injection attacks.
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

        $has_wildcard = in_array( '*', $allowed, true );

        // Sanitise and validate the Origin header (fix M-06 — header injection).
        $request_origin = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ?? '' ) );
        if ( $request_origin && ! filter_var( $request_origin, FILTER_VALIDATE_URL ) ) {
            $request_origin = '';
        }

        // Determine which origin to echo back.
        if ( $has_wildcard ) {
            // Wildcard mode: reflect the request origin (or send * if none).
            $origin_header   = $request_origin ?: '*';
            $allow_credentials = false; // Spec forbids credentials with wildcard.
        } else {
            if ( ! $request_origin || ! in_array( $request_origin, $allowed, true ) ) {
                return;
            }
            $origin_header     = $request_origin;
            $allow_credentials = true;
        }

        // Set CORS headers for all eprocurement REST routes.
        add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) use ( $origin_header, $allow_credentials ) {
            $route = $request->get_route();
            if ( strpos( $route, '/eprocurement/v1/' ) === 0 ) {
                header( 'Access-Control-Allow-Origin: ' . $origin_header );
                header( 'Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS' );
                header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
                if ( $allow_credentials ) {
                    header( 'Access-Control-Allow-Credentials: true' );
                }
                header( 'Vary: Origin' );
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
                if ( $allow_credentials ) {
                    header( 'Access-Control-Allow-Credentials: true' );
                }
                header( 'Access-Control-Max-Age: 86400' );
                header( 'Vary: Origin' );
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

        // --- Bid Submissions (Admin) ---
        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<bid_id>\d+)/submissions', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'list_submissions' ],
            'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<bid_id>\d+)/submissions/download', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'download_submissions_zip' ],
            'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/submissions/(?P<id>\d+)/download', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'download_single_submission' ],
            'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/submissions/(?P<id>\d+)/backdate', [
            'methods'             => 'PATCH',
            'callback'            => [ $this, 'backdate_submission' ],
            'permission_callback' => fn() => is_super_admin(),
        ] );

        // --- Evaluation Criteria (per-tender scoring rubric) ---
        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<bid_id>\d+)/criteria', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_criteria' ],
                'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_criterion' ],
                'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/criteria/(?P<id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [ $this, 'update_criterion' ],
                'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'delete_criterion' ],
                'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
            ],
        ] );

        // --- Scoring ---
        register_rest_route( self::NAMESPACE, '/admin/submissions/(?P<id>\d+)/scores', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_scores' ],
                'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'set_score' ],
                'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
            ],
        ] );

        // --- Ranked comparison ---
        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<bid_id>\d+)/comparison', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_comparison' ],
            'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
        ] );

        // --- Comparison CSV Export ---
        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<bid_id>\d+)/comparison/export', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'export_comparison_csv' ],
            'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
        ] );

        // --- Award ---
        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<bid_id>\d+)/award', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'award_bid' ],
                'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'withdraw_award' ],
                'permission_callback' => fn() => is_super_admin(),
            ],
        ] );

        // --- Submission Requirements (per-document upload fields) ---
        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<bid_id>\d+)/requirements', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_requirements' ],
                'permission_callback' => fn() => current_user_can( 'eproc_create_bids' ),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'add_requirement' ],
                'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/requirements/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_requirement' ],
            'permission_callback' => fn() => current_user_can( 'eproc_publish_bids' ),
        ] );

        // --- Briefing Attendees (Admin) ---
        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<bid_id>\d+)/attendees', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'list_attendees' ],
                'permission_callback' => fn() => current_user_can( 'eproc_view_dashboard' ),
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'add_attendee' ],
                'permission_callback' => fn() => current_user_can( 'eproc_edit_bids' ),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/admin/attendees/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'remove_attendee' ],
            'permission_callback' => fn() => current_user_can( 'eproc_edit_bids' ),
        ] );

        register_rest_route( self::NAMESPACE, '/admin/bids/(?P<bid_id>\d+)/attendees/invite', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'send_attendee_invites' ],
            'permission_callback' => fn() => current_user_can( 'eproc_edit_bids' ),
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

        // Most downloaded documents (top 4)
        $most_downloaded_rows = Eprocurement_Downloads::get_most_downloaded_documents( 4 );

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
            'most_downloaded'  => array_map( function( $row ) {
                return [
                    'title' => $row->title,
                    'count' => (int) $row->dl_count,
                ];
            }, $most_downloaded_rows ),
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

            // Submission settings
            $data['accept_online_submissions'] = absint( $request->get_param( 'accept_online_submissions' ) ?? 0 ) ? 1 : 0;
            $data['allow_late_submissions']    = absint( $request->get_param( 'allow_late_submissions' ) ?? 0 ) ? 1 : 0;
            $data['briefing_compulsory']       = absint( $request->get_param( 'briefing_compulsory' ) ?? 0 ) ? 1 : 0;
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
            // Security fix M-03: sanitise filename before cloud upload.
            $safe_name = sanitize_file_name( $files['file']['name'] );
            $result = $storage->upload( $files['file']['tmp_name'], $safe_name, 'documents' );

            $documents = new Eprocurement_Documents();
            $doc_id    = $documents->add_supporting_doc( [
                'document_id'    => (int) $request['id'],
                'file_name'      => $safe_name,
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
            return new \WP_REST_Response( [ 'message' => esc_html( $e->getMessage() ) ], 500 );
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
                            // Security fix M-03: sanitise filename before cloud upload.
                            $safe_name = sanitize_file_name( $file['name'] );
                            $result = $storage->upload( $file['tmp_name'], $safe_name, 'message-attachments' );
                            $messaging->add_attachment( $message_id, [
                                'file_name'      => $safe_name,
                                'file_size'      => $file['size'],
                                'file_type'      => $mime_type,
                                'cloud_provider' => $storage->get_provider_name(),
                                'cloud_key'      => $result['cloud_key'],
                                'cloud_url'      => $result['cloud_url'],
                            ] );
                        } catch ( \Exception $e ) {
                            // Reply sent, attachment failed — log for admin visibility.
                            error_log( 'eProcurement: attachment upload failed: ' . $e->getMessage() );
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
            // Security fix M-03: sanitise filename before cloud upload.
            $safe_name = sanitize_file_name( $files['file']['name'] );
            $result = $storage->upload( $files['file']['tmp_name'], $safe_name, 'compliance' );

            $compliance = new Eprocurement_Compliance_Docs();
            $doc_id     = $compliance->add( [
                'file_name'      => $safe_name,
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
            return new \WP_REST_Response( [ 'message' => esc_html( $e->getMessage() ) ], 500 );
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

    // =========================================================================
    // Bid Submissions (Admin)
    // =========================================================================

    /**
     * GET /admin/bids/{bid_id}/submissions — List all submissions for a bid.
     */
    public function list_submissions( \WP_REST_Request $request ): \WP_REST_Response {
        $bid_id = (int) $request->get_param( 'bid_id' );

        $bid = Eprocurement_Database::get_by_id( 'documents', $bid_id );
        if ( ! $bid ) {
            return new \WP_REST_Response( [ 'error' => __( 'Bid not found.', 'eprocurement' ) ], 404 );
        }

        $submissions = new Eprocurement_Bid_Submissions();
        $rows        = $submissions->get_submissions_for_document( $bid_id );

        $items = array_map( function ( $sub ) {
            $backdated_visible = ! empty( $sub->backdated_by );

            return [
                'id'            => (int) $sub->id,
                'user_id'       => (int) $sub->user_id,
                'company_name'  => $sub->company_name ?? '',
                'display_name'  => $sub->display_name ?? '',
                'user_email'    => $sub->user_email ?? '',
                'file_name'     => $sub->file_name,
                'file_size'     => (int) $sub->file_size,
                'file_type'     => $sub->file_type,
                'status'        => $sub->status,
                'is_late'       => (bool) $sub->is_late,
                'submitted_at'  => $sub->submitted_at,
                'is_backdated'  => $backdated_visible,
                'original_submitted_at' => $backdated_visible ? $sub->original_submitted_at : null,
                'created_at'    => $sub->created_at,
            ];
        }, $rows );

        return new \WP_REST_Response( [
            'items' => $items,
            'total' => count( $items ),
            'bid'   => [
                'id'            => (int) $bid->id,
                'bid_number'    => $bid->bid_number,
                'title'         => $bid->title,
                'status'        => $bid->status,
                'closing_date'  => $bid->closing_date,
                'allow_late_submissions' => (bool) ( $bid->allow_late_submissions ?? 0 ),
                'briefing_compulsory'    => (bool) ( $bid->briefing_compulsory ?? 0 ),
            ],
        ] );
    }

    /**
     * GET /admin/bids/{bid_id}/submissions/download — Download all submissions as ZIP.
     */
    public function download_submissions_zip( \WP_REST_Request $request ): \WP_REST_Response {
        $bid_id = (int) $request->get_param( 'bid_id' );

        $submissions = new Eprocurement_Bid_Submissions();
        $zip_path    = $submissions->generate_submissions_zip( $bid_id );

        if ( is_wp_error( $zip_path ) ) {
            $status = $zip_path->get_error_data()['status'] ?? 400;
            return new \WP_REST_Response( [ 'error' => $zip_path->get_error_message() ], $status );
        }

        // Serve the ZIP file as a download.
        $zip_name = basename( $zip_path );
        $zip_size = filesize( $zip_path );

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . $zip_name . '"' );
        header( 'Content-Length: ' . $zip_size );
        header( 'Pragma: public' );
        header( 'Cache-Control: no-store' );

        readfile( $zip_path ); // phpcs:ignore
        @unlink( $zip_path ); // phpcs:ignore
        exit;
    }

    /**
     * GET /admin/submissions/{id}/download — Download a single submission file.
     */
    public function download_single_submission( \WP_REST_Request $request ): \WP_REST_Response {
        $submission_id = (int) $request->get_param( 'id' );

        $submissions = new Eprocurement_Bid_Submissions();
        $submission  = $submissions->get_submission( $submission_id );

        if ( ! $submission ) {
            return new \WP_REST_Response( [ 'error' => __( 'Submission not found.', 'eprocurement' ) ], 404 );
        }

        $provider = Eprocurement_Storage_Interface::get_active_provider();
        if ( ! $provider ) {
            return new \WP_REST_Response( [ 'error' => __( 'No storage provider configured.', 'eprocurement' ) ], 500 );
        }

        try {
            $download_url = $provider->get_download_url( $submission->cloud_key );
            return new \WP_REST_Response( [
                'download_url' => $download_url,
                'file_name'    => $submission->file_name,
            ] );
        } catch ( \RuntimeException $e ) {
            return new \WP_REST_Response( [ 'error' => __( 'Failed to generate download link.', 'eprocurement' ) ], 500 );
        }
    }

    /**
     * PATCH /admin/submissions/{id}/backdate — Backdate a submission (Super Admin).
     *
     * Body:
     * - submitted_at (string) — New datetime (Y-m-d H:i:s or Y-m-d\TH:i)
     * - visible (bool)        — true = show "Backdated" indicator, false = hide
     */
    public function backdate_submission( \WP_REST_Request $request ): \WP_REST_Response {
        $submission_id = (int) $request->get_param( 'id' );
        $new_datetime  = $request->get_param( 'submitted_at' );
        $visible       = (bool) $request->get_param( 'visible' );

        if ( empty( $new_datetime ) ) {
            return new \WP_REST_Response( [ 'error' => __( 'New datetime is required.', 'eprocurement' ) ], 400 );
        }

        $submissions = new Eprocurement_Bid_Submissions();
        $result      = $submissions->backdate( $submission_id, $new_datetime, get_current_user_id(), $visible );

        if ( is_wp_error( $result ) ) {
            $status = $result->get_error_data()['status'] ?? 400;
            return new \WP_REST_Response( [
                'error' => $result->get_error_message(),
                'code'  => $result->get_error_code(),
            ], $status );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => $visible
                ? __( 'Submission backdated. "Backdated" indicator will be visible.', 'eprocurement' )
                : __( 'Submission backdated. Change is hidden from audit.', 'eprocurement' ),
        ] );
    }

    // =========================================================================
    // Briefing Attendees (Admin)
    // =========================================================================

    /**
     * GET /admin/bids/{bid_id}/attendees — List briefing attendees.
     */
    public function list_attendees( \WP_REST_Request $request ): \WP_REST_Response {
        $bid_id = (int) $request->get_param( 'bid_id' );

        $bid = Eprocurement_Database::get_by_id( 'documents', $bid_id );
        if ( ! $bid ) {
            return new \WP_REST_Response( [ 'error' => __( 'Bid not found.', 'eprocurement' ) ], 404 );
        }

        $submissions = new Eprocurement_Bid_Submissions();
        $attendees   = $submissions->get_attendees( $bid_id );

        $items = array_map( function ( $att ) {
            return [
                'id'           => (int) $att->id,
                'bidder_email' => $att->bidder_email,
                'company_name' => $att->company_name,
                'user_id'      => $att->user_id ? (int) $att->user_id : null,
                'invited_at'   => $att->invited_at,
                'used_at'      => $att->used_at,
            ];
        }, $attendees );

        return new \WP_REST_Response( [
            'attendees'           => $items,
            'total'               => count( $items ),
            'briefing_compulsory' => (bool) ( $bid->briefing_compulsory ?? 0 ),
        ] );
    }

    /**
     * POST /admin/bids/{bid_id}/attendees — Add a briefing attendee.
     *
     * Body:
     * - email (string)        — Bidder email address
     * - company_name (string) — Company name
     */
    public function add_attendee( \WP_REST_Request $request ): \WP_REST_Response {
        $bid_id       = (int) $request->get_param( 'bid_id' );
        $email        = sanitize_email( $request->get_param( 'email' ) ?? '' );
        $company_name = sanitize_text_field( $request->get_param( 'company_name' ) ?? '' );

        if ( empty( $email ) ) {
            return new \WP_REST_Response( [ 'error' => __( 'Email is required.', 'eprocurement' ) ], 400 );
        }

        if ( empty( $company_name ) ) {
            return new \WP_REST_Response( [ 'error' => __( 'Company name is required.', 'eprocurement' ) ], 400 );
        }

        // Check if email belongs to an existing WP user.
        $user    = get_user_by( 'email', $email );
        $user_id = $user ? $user->ID : null;

        $submissions = new Eprocurement_Bid_Submissions();
        $result      = $submissions->add_attendee( $bid_id, $email, $company_name, $user_id );

        if ( is_wp_error( $result ) ) {
            $status = $result->get_error_data()['status'] ?? 400;
            return new \WP_REST_Response( [
                'error' => $result->get_error_message(),
                'code'  => $result->get_error_code(),
            ], $status );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'id'      => $result,
            'message' => __( 'Attendee added successfully.', 'eprocurement' ),
        ], 201 );
    }

    /**
     * DELETE /admin/attendees/{id} — Remove a briefing attendee.
     */
    public function remove_attendee( \WP_REST_Request $request ): \WP_REST_Response {
        $attendee_id = (int) $request->get_param( 'id' );

        $submissions = new Eprocurement_Bid_Submissions();
        $result      = $submissions->remove_attendee( $attendee_id );

        if ( is_wp_error( $result ) ) {
            $status = $result->get_error_data()['status'] ?? 400;
            return new \WP_REST_Response( [
                'error' => $result->get_error_message(),
                'code'  => $result->get_error_code(),
            ], $status );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => __( 'Attendee removed.', 'eprocurement' ),
        ] );
    }

    /**
     * POST /admin/bids/{bid_id}/attendees/invite — Send briefing invite emails.
     *
     * Sends an email to each attendee with their unique submission link.
     */
    public function send_attendee_invites( \WP_REST_Request $request ): \WP_REST_Response {
        $bid_id = (int) $request->get_param( 'bid_id' );

        $bid = Eprocurement_Database::get_by_id( 'documents', $bid_id );
        if ( ! $bid ) {
            return new \WP_REST_Response( [ 'error' => __( 'Bid not found.', 'eprocurement' ) ], 404 );
        }

        $submissions = new Eprocurement_Bid_Submissions();
        $attendees   = $submissions->get_attendees( $bid_id );

        if ( empty( $attendees ) ) {
            return new \WP_REST_Response( [ 'error' => __( 'No attendees to invite.', 'eprocurement' ) ], 400 );
        }

        $slug        = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        $sent_count  = 0;

        foreach ( $attendees as $att ) {
            $submit_url = home_url( "/{$slug}/bid/{$bid_id}/?token=" . urlencode( $att->token ) );

            $subject = sprintf(
                /* translators: 1: Bid number, 2: Bid title */
                __( 'Briefing Invite: %1$s — %2$s', 'eprocurement' ),
                $bid->bid_number,
                $bid->title
            );

            // Try to load the email template, fall back to plain text.
            // Themes can override by placing /wp-content/themes/{theme}/eprocurement/email/briefing-invite.php
            $template_path = EPROC_PLUGIN_DIR . 'templates/email/briefing-invite.php';
            $theme_override = locate_template( 'eprocurement/email/briefing-invite.php' );
            $template_path = $theme_override ?: $template_path;

            if ( file_exists( $template_path ) ) {
                $bidder_name   = $att->company_name ?: $att->bidder_email;
                $bid_number    = $bid->bid_number;
                $bid_title     = $bid->title;
                $briefing_date = $bid->briefing_date
                    ? wp_date( 'j F Y, H:i', strtotime( $bid->briefing_date ) )
                    : __( 'TBC', 'eprocurement' );
                $register_url  = home_url( "/{$slug}/register/" );

                ob_start();
                include $template_path;
                $body = ob_get_clean();

                // Send as HTML — use a stored closure reference so we can
                // remove the filter again (security fix H-08). Previously,
                // the add_filter and remove_filter calls each created a new
                // closure instance, so the filter was never removed and
                // leaked into subsequent wp_mail() calls in the same request.
                $set_html = static fn( $ct ) => 'text/html';
                add_filter( 'wp_mail_content_type', $set_html );
                $sent = wp_mail( $att->bidder_email, $subject, $body );
                remove_filter( 'wp_mail_content_type', $set_html );
            } else {
                $body = sprintf(
                    __(
                        "Hello %1\$s,\n\n" .
                        "You are invited to submit a bid for:\n\n" .
                        "Bid Number: %2\$s\n" .
                        "Title: %3\$s\n" .
                        "Briefing Date: %4\$s\n\n" .
                        "Submit your bid here:\n%5\$s\n\n" .
                        "If you don't have an account yet, register here:\n%6\$s\n\n" .
                        "Regards,\neProcurement System",
                        'eprocurement'
                    ),
                    $att->company_name ?: $att->bidder_email,
                    $bid->bid_number,
                    $bid->title,
                    $bid->briefing_date ? wp_date( 'j F Y, H:i', strtotime( $bid->briefing_date ) ) : __( 'TBC', 'eprocurement' ),
                    $submit_url,
                    home_url( "/{$slug}/register/" )
                );
                $sent = wp_mail( $att->bidder_email, $subject, $body );
            }

            if ( $sent ) {
                $sent_count++;
            }
        }

        return new \WP_REST_Response( [
            'success' => true,
            'sent'    => $sent_count,
            'total'   => count( $attendees ),
            'message' => sprintf(
                /* translators: 1: sent count, 2: total count */
                __( 'Sent %1$d of %2$d invite emails.', 'eprocurement' ),
                $sent_count,
                count( $attendees )
            ),
        ] );
    }

    // =========================================================================
    // Evaluation Criteria
    // =========================================================================

    public function list_criteria( \WP_REST_Request $request ): \WP_REST_Response {
        $evaluation = new Eprocurement_Evaluation();
        $criteria = $evaluation->get_criteria( (int) $request['bid_id'] );

        return new \WP_REST_Response( [
            'criteria' => $criteria,
            'count'    => count( $criteria ),
        ] );
    }

    public function save_criterion( \WP_REST_Request $request ): \WP_REST_Response {
        $evaluation = new Eprocurement_Evaluation();
        $bid_id = (int) $request['bid_id'];

        $id = $evaluation->add_criterion( [
            'document_id' => $bid_id,
            'name'        => $request->get_param( 'name' ),
            'description' => $request->get_param( 'description' ) ?? '',
            'weight'      => $request->get_param( 'weight' ) ?? 1,
            'max_score'   => $request->get_param( 'max_score' ) ?? 10,
        ] );

        if ( ! $id ) {
            return new \WP_REST_Response( [ 'message' => 'Failed to create criterion.' ], 500 );
        }

        return new \WP_REST_Response( [ 'message' => 'Criterion added.', 'id' => $id ], 201 );
    }

    public function update_criterion( \WP_REST_Request $request ): \WP_REST_Response {
        $evaluation = new Eprocurement_Evaluation();
        $result = $evaluation->update_criterion( (int) $request['id'], [
            'name'        => $request->get_param( 'name' ),
            'description' => $request->get_param( 'description' ),
            'weight'      => $request->get_param( 'weight' ),
            'max_score'   => $request->get_param( 'max_score' ),
        ] );

        if ( $result === false ) {
            return new \WP_REST_Response( [ 'message' => 'Failed to update criterion.' ], 500 );
        }

        return new \WP_REST_Response( [ 'message' => 'Criterion updated.' ] );
    }

    public function delete_criterion( \WP_REST_Request $request ): \WP_REST_Response {
        $evaluation = new Eprocurement_Evaluation();
        if ( $evaluation->delete_criterion( (int) $request['id'] ) ) {
            return new \WP_REST_Response( [ 'message' => 'Criterion deleted.' ] );
        }
        return new \WP_REST_Response( [ 'message' => 'Failed to delete criterion.' ], 500 );
    }

    // =========================================================================
    // Scoring
    // =========================================================================

    public function get_scores( \WP_REST_Request $request ): \WP_REST_Response {
        $evaluation = new Eprocurement_Evaluation();
        $sub_id = (int) $request['id'];

        $scores = $evaluation->get_scores_for_submission( $sub_id );
        $computed = $evaluation->compute_submission_score( $sub_id );

        return new \WP_REST_Response( [
            'scores'   => $scores,
            'computed' => $computed,
        ] );
    }

    public function set_score( \WP_REST_Request $request ): \WP_REST_Response {
        $evaluation = new Eprocurement_Evaluation();
        $sub_id      = (int) $request['id'];
        $criterion_id = absint( $request->get_param( 'criterion_id' ) );
        $score       = (float) $request->get_param( 'score' );
        $notes       = $request->get_param( 'notes' ) ?? '';

        if ( ! $criterion_id ) {
            return new \WP_REST_Response( [ 'message' => 'Missing criterion_id.' ], 400 );
        }

        $id = $evaluation->set_score( $sub_id, $criterion_id, $score, $notes );
        if ( ! $id ) {
            return new \WP_REST_Response( [ 'message' => 'Failed to save score.' ], 500 );
        }

        // Return the recomputed totals so the UI can update in real-time.
        $computed = $evaluation->compute_submission_score( $sub_id );

        return new \WP_REST_Response( [
            'message'  => 'Score saved.',
            'id'       => $id,
            'computed' => $computed,
        ] );
    }

    // =========================================================================
    // Ranked comparison
    // =========================================================================

    public function get_comparison( \WP_REST_Request $request ): \WP_REST_Response {
        $evaluation = new Eprocurement_Evaluation();
        $bid_id = (int) $request['bid_id'];

        $criteria = $evaluation->get_criteria( $bid_id );
        $ranked   = $evaluation->get_ranked_comparison( $bid_id );
        $award    = ( new Eprocurement_Documents() )->get_award( $bid_id );

        return new \WP_REST_Response( [
            'criteria' => $criteria,
            'ranked'   => $ranked,
            'award'    => $award,
        ] );
    }

    /**
     * Export the evaluation comparison as a CSV file download.
     *
     * Generates a side-by-side CSV with criteria as rows, submissions as
     * columns, weighted totals, rank, late status, and award info.
     * Suitable for audit trails and procurement committee review.
     */
    public function export_comparison_csv( \WP_REST_Request $request ): void {
        $bid_id = (int) $request['bid_id'];
        $evaluation = new Eprocurement_Evaluation();
        $documents = new Eprocurement_Documents();

        $bid       = $documents->get( $bid_id );
        $criteria  = $evaluation->get_criteria( $bid_id );
        $ranked    = $evaluation->get_ranked_comparison( $bid_id );
        $award     = $documents->get_award( $bid_id );

        // Build CSV filename.
        $filename = sanitize_file_name(
            ( $bid ? $bid->bid_number : 'tender' ) . '_evaluation_' . gmdate( 'Y-m-d' ) . '.csv'
        );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // ── Header section: tender info ──
        fputcsv( $output, [ 'eProcurement Evaluation Report' ] );
        fputcsv( $output, [ 'Bid Number', $bid ? $bid->bid_number : '' ] );
        fputcsv( $output, [ 'Title', $bid ? $bid->title : '' ] );
        fputcsv( $output, [ 'Status', $bid ? ucfirst( $bid->status ) : '' ] );
        fputcsv( $output, [ 'Closing Date', $bid && $bid->closing_date ? wp_date( 'j F Y, H:i', strtotime( $bid->closing_date ) ) : '' ] );
        fputcsv( $output, [ 'Export Date', wp_date( 'j F Y, H:i' ) ] );

        if ( $award ) {
            fputcsv( $output, [ 'Awarded To', $award->company_name ?: $award->display_name ] );
            fputcsv( $output, [ 'Award Amount', $award->award_amount !== null ? number_format_i18n( $award->award_amount, 2 ) : 'Not disclosed' ] );
            fputcsv( $output, [ 'Award Date', wp_date( 'j F Y', strtotime( $award->award_date ) ) ] );
        }

        fputcsv( $output, [] ); // Blank line.

        // ── Side-by-side comparison matrix ──
        if ( empty( $ranked ) ) {
            fputcsv( $output, [ 'No submissions received.' ] );
            fclose( $output );
            exit;
        }

        // Build header row: Criterion | Weight | Max | Sub1 | Sub2 | ...
        $header = [ 'Criterion', 'Weight', 'Max Score' ];
        foreach ( $ranked as $r ) {
            $company = $r['company_name'] ?: $r['bidder_name'];
            $header[] = '#' . $r['rank'] . ' ' . $company;
        }
        fputcsv( $output, $header );

        // One row per criterion.
        if ( ! empty( $criteria ) ) {
            foreach ( $criteria as $c ) {
                $row = [ $c->name, number_format_i18n( (float) $c->weight, 1 ), $c->max_score ];
                foreach ( $ranked as $r ) {
                    $crit_data = $r['scores_by_criterion'][ (int) $c->id ] ?? null;
                    if ( $crit_data && $crit_data['avg_score'] > 0 ) {
                        $row[] = number_format_i18n( (float) $crit_data['avg_score'], 1 ) . '/' . $c->max_score;
                    } else {
                        $row[] = '—';
                    }
                }
                fputcsv( $output, $row );
            }
        }

        // Weighted total row.
        $total_row = [ 'Weighted Total', '', '' ];
        foreach ( $ranked as $r ) {
            $total_row[] = $r['criteria_scored'] > 0
                ? number_format_i18n( $r['score_total'], 1 ) . '/100'
                : '—';
        }
        fputcsv( $output, $total_row );

        // Rank row.
        $rank_row = [ 'Rank', '', '' ];
        foreach ( $ranked as $r ) {
            $rank_row[] = '#' . $r['rank'];
        }
        fputcsv( $output, $rank_row );

        // Late submission row.
        $late_row = [ 'Late Submission', '', '' ];
        foreach ( $ranked as $r ) {
            $late_row[] = $r['is_late'] ? 'Yes' : 'No';
        }
        fputcsv( $output, $late_row );

        // Submitted at row.
        $submitted_row = [ 'Submitted At', '', '' ];
        foreach ( $ranked as $r ) {
            $submitted_row[] = $r['submitted_at'];
        }
        fputcsv( $output, $submitted_row );

        // Bidder email row.
        $email_row = [ 'Bidder Email', '', '' ];
        foreach ( $ranked as $r ) {
            $email_row[] = $r['bidder_email'];
        }
        fputcsv( $output, $email_row );

        // Award row.
        $award_row = [ 'Award Status', '', '' ];
        foreach ( $ranked as $r ) {
            if ( $award && $award->user_id === (int) $r['submission']->user_id ) {
                $award_row[] = 'AWARDED';
            } else {
                $award_row[] = '';
            }
        }
        fputcsv( $output, $award_row );

        fputcsv( $output, [] ); // Blank line.
        fputcsv( $output, [ 'Generated by eProcurement Plugin v' . EPROC_VERSION ] );

        fclose( $output );
        exit;
    }

    // =========================================================================
    // Award
    // =========================================================================

    public function award_bid( \WP_REST_Request $request ): \WP_REST_Response {
        $documents = new Eprocurement_Documents();
        $bid_id = (int) $request['bid_id'];

        $winner_user_id = absint( $request->get_param( 'winner_user_id' ) );
        $award_amount   = (float) ( $request->get_param( 'award_amount' ) ?? 0 );
        $award_notes    = sanitize_textarea_field( $request->get_param( 'award_notes' ) ?? '' );

        if ( ! $winner_user_id ) {
            return new \WP_REST_Response( [ 'message' => 'Missing winner_user_id.' ], 400 );
        }

        $result = $documents->award( $bid_id, $winner_user_id, $award_amount, $award_notes );

        if ( is_wp_error( $result ) ) {
            $status = $result->get_error_data()['status'] ?? 400;
            return new \WP_REST_Response( [ 'message' => $result->get_error_message() ], $status );
        }

        return new \WP_REST_Response( [
            'message' => 'Tender awarded. Notifications sent to all bidders.',
        ] );
    }

    public function withdraw_award( \WP_REST_Request $request ): \WP_REST_Response {
        $documents = new Eprocurement_Documents();
        if ( $documents->withdraw_award( (int) $request['bid_id'] ) ) {
            return new \WP_REST_Response( [ 'message' => 'Award withdrawn.' ] );
        }
        return new \WP_REST_Response( [ 'message' => 'Failed to withdraw award.' ], 500 );
    }

    // =========================================================================
    // Submission Requirements
    // =========================================================================

    public function list_requirements( \WP_REST_Request $request ): \WP_REST_Response {
        $model = new Eprocurement_Submission_Requirements();
        $requirements = $model->get_requirements( (int) $request['bid_id'] );

        return new \WP_REST_Response( [
            'requirements' => $requirements,
            'count'        => count( $requirements ),
            'presets'      => Eprocurement_Submission_Requirements::PRESETS,
        ] );
    }

    public function add_requirement( \WP_REST_Request $request ): \WP_REST_Response {
        $model = new Eprocurement_Submission_Requirements();
        $bid_id = (int) $request['bid_id'];

        $id = $model->add_requirement( [
            'document_id'         => $bid_id,
            'field_key'           => $request->get_param( 'field_key' ),
            'field_label'         => $request->get_param( 'field_label' ),
            'description'         => $request->get_param( 'description' ) ?? '',
            'is_required'         => $request->get_param( 'is_required' ) ?? 1,
            'accepted_extensions' => $request->get_param( 'accepted_extensions' ) ?? '',
            'max_file_size'       => $request->get_param( 'max_file_size' ) ?? 0,
        ] );

        if ( ! $id ) {
            return new \WP_REST_Response( [ 'message' => 'Failed to create requirement.' ], 500 );
        }

        return new \WP_REST_Response( [ 'message' => 'Requirement added.', 'id' => $id ], 201 );
    }

    public function delete_requirement( \WP_REST_Request $request ): \WP_REST_Response {
        $model = new Eprocurement_Submission_Requirements();
        if ( $model->delete_requirement( (int) $request['id'] ) ) {
            return new \WP_REST_Response( [ 'message' => 'Requirement deleted.' ] );
        }
        return new \WP_REST_Response( [ 'message' => 'Failed to delete requirement.' ], 500 );
    }
}
