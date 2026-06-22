<?php
/**
 * REST API endpoints for the eProcurement plugin.
 *
 * Namespace: eprocurement/v1
 *
 * Endpoints:
 * - GET    /documents          List documents (public)
 * - GET    /documents/{id}     Single document (public)
 * - POST   /register           Bidder registration (public)
 * - POST   /query              Submit a query (authenticated bidder)
 * - POST   /reply              Reply to a thread (authenticated staff)
 * - GET    /threads            List threads (authenticated)
 * - GET    /threads/{id}       Thread with messages (authenticated)
 * - GET    /compliance-docs    List SCM docs (public)
 * - POST   /upload             Upload file to cloud (authenticated staff)
 * - POST   /profile            Update bidder profile (authenticated bidder)
 * - POST   /submissions        Submit bid document (verified bidder)
 * - DELETE /submissions/{id}   Cancel bid submission (verified bidder)
 * - GET    /submissions        List bidder's own submissions (verified bidder)
 * - GET    /submissions/check  Check submission eligibility (verified bidder)
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Rest_Api {

    private const NAMESPACE = 'eprocurement/v1';

    /**
     * Register REST routes on init.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register all REST API routes.
     */
    public function register_routes(): void {
        // Public: List documents
        register_rest_route( self::NAMESPACE, '/documents', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_documents' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'status'  => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'search'  => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
                'page'    => [ 'type' => 'integer', 'default' => 1 ],
                'per_page' => [ 'type' => 'integer', 'default' => 12 ],
            ],
        ] );

        // Public: Single document
        register_rest_route( self::NAMESPACE, '/documents/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_document' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [ 'type' => 'integer', 'required' => true ],
            ],
        ] );

        // Public: Bidder registration
        register_rest_route( self::NAMESPACE, '/register', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'register_bidder' ],
            'permission_callback' => '__return_true',
        ] );

        // Authenticated: Submit a query
        register_rest_route( self::NAMESPACE, '/query', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'submit_query' ],
            'permission_callback' => [ $this, 'is_verified_bidder' ],
        ] );

        // Authenticated: Reply to a thread
        register_rest_route( self::NAMESPACE, '/reply', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'post_reply' ],
            'permission_callback' => [ $this, 'can_reply' ],
        ] );

        // Authenticated: List threads
        register_rest_route( self::NAMESPACE, '/threads', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_threads' ],
            'permission_callback' => 'is_user_logged_in',
        ] );

        // Authenticated: Single thread with messages
        register_rest_route( self::NAMESPACE, '/threads/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_thread' ],
            'permission_callback' => 'is_user_logged_in',
        ] );

        // Public: SCM documents
        register_rest_route( self::NAMESPACE, '/compliance-docs', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_compliance_docs' ],
            'permission_callback' => '__return_true',
        ] );

        // Staff: Upload file to cloud
        register_rest_route( self::NAMESPACE, '/upload', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'upload_file' ],
            'permission_callback' => [ $this, 'can_upload' ],
        ] );

        // Bidder: Update profile
        register_rest_route( self::NAMESPACE, '/profile', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'update_profile' ],
            'permission_callback' => [ $this, 'is_bidder' ],
        ] );

        // --- Bid Submissions (bidder-facing) ---

        // Bidder: Submit a bid document / List own submissions
        register_rest_route( self::NAMESPACE, '/submissions', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'submit_bid' ],
                'permission_callback' => [ $this, 'is_verified_bidder' ],
            ],
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_my_submissions' ],
                'permission_callback' => [ $this, 'is_verified_bidder' ],
            ],
        ] );

        // Bidder: Cancel own submission
        register_rest_route( self::NAMESPACE, '/submissions/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'cancel_submission' ],
            'permission_callback' => [ $this, 'is_verified_bidder' ],
        ] );

        // Bidder: Check if eligible to submit for a bid
        register_rest_route( self::NAMESPACE, '/submissions/check', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'check_submission_eligibility' ],
            'permission_callback' => [ $this, 'is_verified_bidder' ],
            'args'                => [
                'document_id' => [ 'type' => 'integer', 'required' => true ],
            ],
        ] );
    }

    /**
     * GET /documents — List open/closed documents.
     */
    public function get_documents( \WP_REST_Request $request ): \WP_REST_Response {
        $documents = new Eprocurement_Documents();

        $args = [
            'status'   => $request->get_param( 'status' ),
            'search'   => $request->get_param( 'search' ),
            'page'     => $request->get_param( 'page' ),
            'per_page' => $request->get_param( 'per_page' ),
            'category' => $request->get_param( 'category' ),
        ];

        // Public listing: only show open, closed (not draft/cancelled/archived)
        if ( empty( $args['status'] ) ) {
            // Will be handled in the list method by excluding archived
        }

        $result = $documents->list( $args );

        // Batch-fetch contacts and doc counts to avoid N+1 queries.
        global $wpdb;
        $doc_ids     = wp_list_pluck( $result['items'], 'id' );
        $contact_ids = [];
        foreach ( $result['items'] as $doc ) {
            if ( ! empty( $doc->scm_contact_id ) ) {
                $contact_ids[] = (int) $doc->scm_contact_id;
            }
            if ( ! empty( $doc->technical_contact_id ) ) {
                $contact_ids[] = (int) $doc->technical_contact_id;
            }
        }
        $contact_ids = array_unique( $contact_ids );

        // Batch-fetch contacts (single query).
        $contacts_map = [];
        if ( ! empty( $contact_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );
            $contacts_table = Eprocurement_Database::table( 'contact_persons' );
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, name FROM {$contacts_table} WHERE id IN ({$placeholders})", // phpcs:ignore
                ...$contact_ids
            ) );
            foreach ( $rows as $row ) {
                $contacts_map[ (int) $row->id ] = $row->name;
            }
        }

        // Batch-fetch doc counts (single query).
        $doc_counts = [];
        if ( ! empty( $doc_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $doc_ids ), '%d' ) );
            $supporting_table = Eprocurement_Database::table( 'supporting_docs' );
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT document_id, COUNT(*) AS cnt FROM {$supporting_table} WHERE document_id IN ({$placeholders}) GROUP BY document_id", // phpcs:ignore
                ...$doc_ids
            ) );
            foreach ( $rows as $row ) {
                $doc_counts[ (int) $row->document_id ] = (int) $row->cnt;
            }
        }

        $items = array_map( function ( $doc ) use ( $contacts_map, $doc_counts ) {
            return [
                'id'           => (int) $doc->id,
                'bid_number'   => $doc->bid_number,
                'title'        => $doc->title,
                'description'  => $doc->description,
                'status'       => $doc->status,
                'opening_date' => $doc->opening_date,
                'briefing_date' => $doc->briefing_date,
                'closing_date' => $doc->closing_date,
                'contacts'     => [
                    'scm'       => ! empty( $doc->scm_contact_id ) ? ( $contacts_map[ (int) $doc->scm_contact_id ] ?? null ) : null,
                    'technical' => ! empty( $doc->technical_contact_id ) ? ( $contacts_map[ (int) $doc->technical_contact_id ] ?? null ) : null,
                ],
                'doc_count'    => $doc_counts[ (int) $doc->id ] ?? 0,
                'created_at'   => $doc->created_at,
            ];
        }, $result['items'] );

        return new \WP_REST_Response( [
            'items' => $items,
            'total' => $result['total'],
            'pages' => $result['pages'],
        ] );
    }

    /**
     * GET /documents/{id} — Single document with full details.
     */
    public function get_document( \WP_REST_Request $request ): \WP_REST_Response {
        $id = (int) $request->get_param( 'id' );

        $documents  = new Eprocurement_Documents();
        $document   = $documents->get( $id );

        if ( ! $document || in_array( $document->status, [ 'draft', 'cancelled', 'archived' ], true ) ) {
            return new \WP_REST_Response( [ 'error' => 'Document not found.' ], 404 );
        }

        $contacts   = new Eprocurement_Contact_Persons();
        $doc_contacts = $contacts->get_for_document( $id );
        $supporting = $documents->get_supporting_docs( $id );

        // Format bid docs with download links
        $files = array_map( function ( $file ) {
            return [
                'id'         => (int) $file->id,
                'label'      => $file->label ?: $file->file_name,
                'file_name'  => $file->file_name,
                'file_size'  => (int) $file->file_size,
                'file_type'  => $file->file_type,
                'download_url' => Eprocurement_Downloads::get_download_link( (int) $file->id, 'supporting' ),
            ];
        }, $supporting );

        // Public Q&A threads
        $messaging  = new Eprocurement_Messaging();
        $public_threads = $messaging->get_threads_for_document( $id, 'public' );

        $qa = [];
        foreach ( $public_threads as $thread ) {
            $messages = $messaging->get_messages( (int) $thread->id );
            $qa[] = [
                'id'         => (int) $thread->id,
                'subject'    => $thread->subject,
                'messages'   => array_map( function ( $msg ) {
                    $sender = get_userdata( (int) $msg->sender_id );
                    return [
                        'sender'     => $sender ? $sender->display_name : 'Unknown',
                        'is_staff'   => Eprocurement_Roles::is_staff( (int) $msg->sender_id ),
                        'message'    => $msg->message,
                        'created_at' => $msg->created_at,
                    ];
                }, $messages ),
                'created_at' => $thread->created_at,
            ];
        }

        // Format contacts
        $contact_data = [];
        foreach ( $doc_contacts as $type => $contact ) {
            $contact_data[] = [
                'id'         => (int) $contact->id,
                'type'       => $type,
                'name'       => $contact->name,
                'phone'      => $contact->phone,
                'email'      => $contact->email,
                'department' => $contact->department,
            ];
        }

        return new \WP_REST_Response( [
            'id'            => (int) $document->id,
            'bid_number'    => $document->bid_number,
            'title'         => $document->title,
            'description'   => $document->description,
            'status'        => $document->status,
            'opening_date'  => $document->opening_date,
            'briefing_date' => $document->briefing_date,
            'closing_date'  => $document->closing_date,
            'contacts'      => $contact_data,
            'files'         => $files,
            'public_qa'     => $qa,
            'created_at'    => $document->created_at,
        ] );
    }

    /**
     * POST /register — Bidder registration.
     *
     * Rate-limited (security fix M-08): max 5 registrations per IP per hour,
     * configurable via the `eprocurement_registration_rate_limit` filter.
     */
    public function register_bidder( \WP_REST_Request $request ): \WP_REST_Response {
        // IP-based rate limiting (fix M-08).
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
        $rate_key   = 'eproc_reg_' . md5( $ip );
        $rate_limit = apply_filters( 'eprocurement_registration_rate_limit', 5 );
        $count      = (int) get_transient( $rate_key );

        if ( $count >= $rate_limit ) {
            return new \WP_REST_Response( [
                'error' => __( 'Too many registration attempts from this IP. Please try again later.', 'eprocurement' ),
                'code'  => 'rate_limited',
            ], 429 );
        }

        $bidder = new Eprocurement_Bidder();

        $result = $bidder->register( [
            'email'        => $request->get_param( 'email' ),
            'first_name'   => $request->get_param( 'first_name' ),
            'last_name'    => $request->get_param( 'last_name' ),
            'password'     => $request->get_param( 'password' ),
            'company_name' => $request->get_param( 'company_name' ),
            'company_reg'  => $request->get_param( 'company_reg' ),
            'phone'        => $request->get_param( 'phone' ),
        ] );

        if ( is_wp_error( $result ) ) {
            return new \WP_REST_Response( [
                'error'   => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ], 400 );
        }

        // Successful registration — increment counter.
        set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

        return new \WP_REST_Response( [
            'success' => true,
            'message' => __( 'Registration successful! Please check your email to verify your account.', 'eprocurement' ),
            'user_id' => $result,
        ], 201 );
    }

    /**
     * POST /query — Submit a query (bidder must be verified).
     */
    public function submit_query( \WP_REST_Request $request ): \WP_REST_Response {
        $document_id = absint( $request->get_param( 'document_id' ) );
        $message     = $request->get_param( 'message' );

        if ( ! $document_id ) {
            return new \WP_REST_Response( [ 'error' => __( 'Missing document ID.', 'eprocurement' ) ], 400 );
        }

        if ( empty( trim( $message ?? '' ) ) ) {
            return new \WP_REST_Response( [ 'error' => __( 'Message is required.', 'eprocurement' ) ], 400 );
        }

        // Verify the document exists and is open
        $document = Eprocurement_Database::get_by_id( 'documents', $document_id );
        if ( ! $document ) {
            return new \WP_REST_Response( [ 'error' => __( 'Document not found.', 'eprocurement' ) ], 404 );
        }

        if ( $document->status !== 'open' ) {
            return new \WP_REST_Response( [ 'error' => __( 'Queries can only be submitted for open bids.', 'eprocurement' ) ], 400 );
        }

        // Enforce Q&A deadline — if set and past, reject new queries.
        if ( ! empty( $document->qa_deadline ) && $document->qa_deadline !== '0000-00-00 00:00:00' ) {
            if ( strtotime( $document->qa_deadline ) < current_time( 'timestamp' ) ) {
                return new \WP_REST_Response( [
                    'error' => __( 'The Q&A deadline has passed. New queries can no longer be submitted for this tender.', 'eprocurement' ),
                    'code'  => 'qa_deadline_passed',
                ], 400 );
            }
        }

        $messaging = new Eprocurement_Messaging();

        $thread_id = $messaging->create_thread( [
            'document_id' => $request->get_param( 'document_id' ),
            'contact_id'  => $request->get_param( 'contact_id' ),
            'bidder_id'   => get_current_user_id(),
            'message'     => $request->get_param( 'message' ),
            'visibility'  => $request->get_param( 'visibility' ),
        ] );

        if ( ! $thread_id ) {
            return new \WP_REST_Response( [ 'error' => 'Failed to create query.' ], 500 );
        }

        // Handle file attachment if present
        $files = $request->get_file_params();
        if ( ! empty( $files['attachment'] ) ) {
            $validation = Eprocurement_Storage_Interface::validate_file( $files['attachment'], 5242880 ); // 5MB max
            if ( is_wp_error( $validation ) ) {
                return new \WP_REST_Response( [ 'error' => $validation->get_error_message() ], 400 );
            }

            $storage = Eprocurement_Storage_Interface::get_active_provider();
            if ( $storage ) {
                try {
                    // Security fix M-03: sanitise filename before passing to
                    // cloud storage to prevent path injection in cloud APIs.
                    $safe_name = sanitize_file_name( $files['attachment']['name'] );
                    $upload_result = $storage->upload(
                        $files['attachment']['tmp_name'],
                        $safe_name,
                        'messages'
                    );

                    // Get the first message in this thread
                    $messages = $messaging->get_messages( $thread_id );
                    if ( ! empty( $messages ) ) {
                        $messaging->add_attachment( (int) $messages[0]->id, [
                            'file_name'      => $safe_name,
                            'file_size'      => $files['attachment']['size'],
                            'file_type'      => $files['attachment']['type'],
                            'cloud_provider' => $storage->get_provider_name(),
                            'cloud_key'      => $upload_result['cloud_key'],
                            'cloud_url'      => $upload_result['cloud_url'],
                        ] );
                    }
                } catch ( \Exception $e ) {
                    // Log but don't fail the entire query
                    error_log( 'eProcurement: Attachment upload failed: ' . $e->getMessage() );
                }
            }
        }

        // Save notify_replies preference if provided
        $notify_replies = $request->get_param( 'notify_replies' );
        if ( $notify_replies !== null ) {
            global $wpdb;
            $bp_table = Eprocurement_Database::table( 'bidder_profiles' );
            $wpdb->update(
                $bp_table,
                [ 'notify_replies' => absint( $notify_replies ) ? 1 : 0 ],
                [ 'user_id' => get_current_user_id() ],
                [ '%d' ],
                [ '%d' ]
            ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }

        // Fire notification hook
        $messages = $messaging->get_messages( $thread_id );
        $message_id = ! empty( $messages ) ? (int) $messages[0]->id : 0;
        do_action( 'eprocurement_query_created', $thread_id, $message_id );

        return new \WP_REST_Response( [
            'success'   => true,
            'thread_id' => $thread_id,
            'message'   => __( 'Your query has been submitted. You will receive a notification when a reply is posted.', 'eprocurement' ),
        ], 201 );
    }

    /**
     * POST /reply — Reply to a thread.
     */
    public function post_reply( \WP_REST_Request $request ): \WP_REST_Response {
        $messaging = new Eprocurement_Messaging();
        $thread_id = absint( $request->get_param( 'thread_id' ) );
        $message   = $request->get_param( 'message' );

        $thread = $messaging->get_thread( $thread_id, get_current_user_id() );
        if ( ! $thread ) {
            return new \WP_REST_Response( [ 'error' => 'Thread not found or access denied.' ], 404 );
        }

        $message_id = $messaging->add_message( $thread_id, get_current_user_id(), $message );
        if ( ! $message_id ) {
            return new \WP_REST_Response( [ 'error' => 'Failed to post reply.' ], 500 );
        }

        // Fire notification hook
        do_action( 'eprocurement_reply_posted', $thread_id, $message_id );

        return new \WP_REST_Response( [
            'success'    => true,
            'message_id' => $message_id,
        ] );
    }

    /**
     * GET /threads — List threads for current user.
     */
    public function get_threads( \WP_REST_Request $request ): \WP_REST_Response {
        $messaging = new Eprocurement_Messaging();
        $user_id   = get_current_user_id();

        if ( Eprocurement_Roles::is_bidder( $user_id ) ) {
            $threads = $messaging->get_threads_for_bidder( $user_id );
        } else {
            $result = $messaging->get_admin_inbox( [
                'page'     => $request->get_param( 'page' ) ?: 1,
                'per_page' => $request->get_param( 'per_page' ) ?: 20,
            ] );
            $threads = $result['items'];
        }

        // Batch-fetch related data to avoid N+1 queries.
        global $wpdb;
        $thread_ids  = wp_list_pluck( $threads, 'id' );
        $doc_ids     = array_unique( array_map( 'intval', wp_list_pluck( $threads, 'document_id' ) ) );
        $contact_ids = array_unique( array_filter( array_map( 'intval', wp_list_pluck( $threads, 'contact_id' ) ) ) );
        $bidder_ids  = array_unique( array_filter( array_map( 'intval', wp_list_pluck( $threads, 'bidder_id' ) ) ) );
        $current_uid = get_current_user_id();

        // Batch-fetch documents.
        $docs_map = [];
        if ( ! empty( $doc_ids ) ) {
            $ph = implode( ',', array_fill( 0, count( $doc_ids ), '%d' ) );
            $docs_table = Eprocurement_Database::table( 'documents' );
            foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT id, bid_number, title FROM {$docs_table} WHERE id IN ({$ph})", ...$doc_ids ) ) as $row ) { // phpcs:ignore
                $docs_map[ (int) $row->id ] = $row;
            }
        }

        // Batch-fetch contacts.
        $contacts_map = [];
        if ( ! empty( $contact_ids ) ) {
            $ph = implode( ',', array_fill( 0, count( $contact_ids ), '%d' ) );
            $ct = Eprocurement_Database::table( 'contact_persons' );
            foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT id, name, type FROM {$ct} WHERE id IN ({$ph})", ...$contact_ids ) ) as $row ) { // phpcs:ignore
                $contacts_map[ (int) $row->id ] = $row;
            }
        }

        // Batch-fetch bidder display names.
        $bidders_map = [];
        if ( ! empty( $bidder_ids ) ) {
            $ph = implode( ',', array_fill( 0, count( $bidder_ids ), '%d' ) );
            foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT ID, display_name FROM {$wpdb->users} WHERE ID IN ({$ph})", ...$bidder_ids ) ) as $row ) { // phpcs:ignore
                $bidders_map[ (int) $row->ID ] = $row->display_name;
            }
        }

        // Batch-fetch message counts, unread counts, and last reply per thread.
        $msg_stats = [];
        if ( ! empty( $thread_ids ) ) {
            $ph = implode( ',', array_fill( 0, count( $thread_ids ), '%d' ) );
            $mt = Eprocurement_Database::table( 'messages' );
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT thread_id, COUNT(*) AS total, " .
                "SUM(CASE WHEN is_read = 0 AND sender_id != %d THEN 1 ELSE 0 END) AS unread, " .
                "MAX(created_at) AS last_reply " .
                "FROM {$mt} WHERE thread_id IN ({$ph}) GROUP BY thread_id", // phpcs:ignore
                $current_uid, ...$thread_ids
            ) );
            foreach ( $rows as $row ) {
                $msg_stats[ (int) $row->thread_id ] = $row;
            }
        }

        $items = array_map( function ( $thread ) use ( $docs_map, $contacts_map, $bidders_map, $msg_stats ) {
            $doc     = $docs_map[ (int) $thread->document_id ] ?? null;
            $contact = $contacts_map[ (int) $thread->contact_id ] ?? null;
            $stats   = $msg_stats[ (int) $thread->id ] ?? null;

            return [
                'id'           => (int) $thread->id,
                'subject'      => $thread->subject,
                'visibility'   => $thread->visibility,
                'status'       => $thread->status,
                'bid_number'   => $doc ? $doc->bid_number : '',
                'bid_title'    => $doc ? $doc->title : '',
                'contact_name' => $contact ? $contact->name : '',
                'contact_type' => $contact ? $contact->type : '',
                'bidder_name'  => $bidders_map[ (int) $thread->bidder_id ] ?? '',
                'message_count' => $stats ? (int) $stats->total : 0,
                'unread_count' => $stats ? (int) $stats->unread : 0,
                'last_reply'   => $stats ? $stats->last_reply : $thread->created_at,
                'created_at'   => $thread->created_at,
            ];
        }, $threads );

        return new \WP_REST_Response( [ 'items' => $items ] );
    }

    /**
     * GET /threads/{id} — Single thread with messages.
     */
    public function get_thread( \WP_REST_Request $request ): \WP_REST_Response {
        $messaging = new Eprocurement_Messaging();
        $thread_id = (int) $request->get_param( 'id' );
        $user_id   = get_current_user_id();

        $thread = $messaging->get_thread( $thread_id, $user_id );
        if ( ! $thread ) {
            return new \WP_REST_Response( [ 'error' => 'Thread not found or access denied.' ], 404 );
        }

        // Mark as read
        $messaging->mark_thread_read( $thread_id, $user_id );

        $messages = $messaging->get_messages( $thread_id );

        $message_data = array_map( function ( $msg ) use ( $messaging ) {
            $sender      = get_userdata( (int) $msg->sender_id );
            $attachments = $messaging->get_attachments( (int) $msg->id );

            return [
                'id'          => (int) $msg->id,
                'sender_name' => $sender ? $sender->display_name : 'Unknown',
                'sender_id'   => (int) $msg->sender_id,
                'is_staff'    => Eprocurement_Roles::is_staff( (int) $msg->sender_id ),
                'message'     => $msg->message,
                'attachments' => array_map( function ( $att ) {
                    return [
                        'id'           => (int) $att->id,
                        'file_name'    => $att->file_name,
                        'file_size'    => (int) $att->file_size,
                        'download_url' => Eprocurement_Downloads::get_download_link( (int) $att->id, 'attachment' ),
                    ];
                }, $attachments ),
                'created_at' => $msg->created_at,
            ];
        }, $messages );

        return new \WP_REST_Response( [
            'thread'   => [
                'id'         => (int) $thread->id,
                'subject'    => $thread->subject,
                'visibility' => $thread->visibility,
                'status'     => $thread->status,
            ],
            'messages' => $message_data,
        ] );
    }

    /**
     * GET /compliance-docs — List all SCM documents.
     */
    public function get_compliance_docs( \WP_REST_Request $request ): \WP_REST_Response {
        $compliance = new Eprocurement_Compliance_Docs();
        $docs       = $compliance->get_all();

        $items = array_map( function ( $doc ) {
            return [
                'id'           => (int) $doc->id,
                'label'        => $doc->label ?: $doc->file_name,
                'description'  => $doc->description,
                'file_name'    => $doc->file_name,
                'file_size'    => (int) $doc->file_size,
                'file_type'    => $doc->file_type,
                'download_url' => Eprocurement_Downloads::get_download_link( (int) $doc->id, 'compliance' ),
            ];
        }, $docs );

        return new \WP_REST_Response( [
            'title' => Eprocurement_Compliance_Docs::get_section_title(),
            'items' => $items,
        ] );
    }

    /**
     * POST /upload — Upload a file to cloud storage.
     */
    public function upload_file( \WP_REST_Request $request ): \WP_REST_Response {
        $files = $request->get_file_params();

        if ( empty( $files['file'] ) ) {
            return new \WP_REST_Response( [ 'error' => 'No file provided.' ], 400 );
        }

        $validation = Eprocurement_Storage_Interface::validate_file( $files['file'] );
        if ( is_wp_error( $validation ) ) {
            return new \WP_REST_Response( [ 'error' => $validation->get_error_message() ], 400 );
        }

        $storage = Eprocurement_Storage_Interface::get_active_provider();
        if ( ! $storage ) {
            return new \WP_REST_Response( [ 'error' => 'Cloud storage not configured.' ], 500 );
        }

        try {
            $folder = sanitize_text_field( $request->get_param( 'folder' ) ?? 'documents' );
            $result = $storage->upload(
                $files['file']['tmp_name'],
                $files['file']['name'],
                $folder
            );

            return new \WP_REST_Response( [
                'success'        => true,
                'cloud_provider' => $storage->get_provider_name(),
                'cloud_key'      => $result['cloud_key'],
                'cloud_url'      => $result['cloud_url'],
                'file_name'      => $files['file']['name'],
                'file_size'      => $files['file']['size'],
                'file_type'      => $files['file']['type'],
            ] );
        } catch ( \Exception $e ) {
            return new \WP_REST_Response( [ 'error' => esc_html( $e->getMessage() ) ], 500 );
        }
    }

    // --- Bid Submission Handlers ---

    /**
     * POST /submissions — Submit a bid document.
     *
     * Supports two modes:
     * - Single file: expects multipart with 'file' field.
     * - Per-document: expects multipart with 'files[field_key]' fields
     *   and 'submission_mode=per_document'. Multiple files are uploaded
     *   to cloud storage and a single submission record is created with
     *   the first file as the primary document; the remaining files
     *   are stored as supporting docs linked to the submission.
     */
    public function submit_bid( \WP_REST_Request $request ): \WP_REST_Response {
        $document_id = absint( $request->get_param( 'document_id' ) );
        if ( ! $document_id ) {
            return new \WP_REST_Response( [ 'error' => __( 'Missing document ID.', 'eprocurement' ) ], 400 );
        }

        $files = $request->get_file_params();
        $submission_mode = sanitize_text_field( $request->get_param( 'submission_mode' ) ?? 'single' );

        // Per-document mode: multiple files keyed by field_key.
        if ( $submission_mode === 'per_document' && ! empty( $files['files'] ) && is_array( $files['files'] ) ) {
            return $this->submit_per_document( $document_id, $files['files'] );
        }

        // Single file mode (original behaviour).
        if ( empty( $files['file'] ) ) {
            return new \WP_REST_Response( [ 'error' => __( 'No file provided.', 'eprocurement' ) ], 400 );
        }

        $submissions = new Eprocurement_Bid_Submissions();
        $result      = $submissions->submit( $document_id, get_current_user_id(), $files['file'] );

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
            'message' => __( 'Your bid has been submitted successfully.', 'eprocurement' ),
        ], 201 );
    }

    /**
     * Handle per-document submission: validate requirements, upload all
     * files, create a single submission record.
     *
     * @param int   $document_id Tender ID.
     * @param array $files       Associative array of field_key => $_FILES element.
     * @return \WP_REST_Response
     */
    private function submit_per_document( int $document_id, array $files ): \WP_REST_Response {
        $user_id = get_current_user_id();

        // Validate required documents.
        $req_model = new Eprocurement_Submission_Requirements();
        $uploaded_keys = array_keys( $files );
        $validation = $req_model->validate_submission( $document_id, $uploaded_keys );

        if ( ! $validation['valid'] ) {
            return new \WP_REST_Response( [
                'error' => __( 'Missing required documents: ', 'eprocurement' ) . implode( ', ', $validation['missing'] ),
                'code'  => 'missing_documents',
            ], 400 );
        }

        // Upload each file to cloud storage.
        $storage = Eprocurement_Storage_Interface::get_active_provider();
        if ( ! $storage ) {
            return new \WP_REST_Response( [ 'error' => __( 'Cloud storage not configured.', 'eprocurement' ) ], 500 );
        }

        $uploaded_docs = [];
        $first_file = null;

        foreach ( $files as $field_key => $file ) {
            // Normalise: WordPress REST API may structure nested files differently.
            if ( isset( $file['name'] ) && is_string( $file['name'] ) ) {
                // Single file per key — normal structure.
            } elseif ( isset( $file['name'] ) && is_array( $file['name'] ) ) {
                // Multiple files per key — take the first.
                $file = [
                    'name'     => $file['name'][0],
                    'type'     => $file['type'][0] ?? '',
                    'tmp_name' => $file['tmp_name'][0],
                    'error'    => $file['error'][0] ?? UPLOAD_ERR_OK,
                    'size'     => $file['size'][0] ?? 0,
                ];
            }

            $validation_result = Eprocurement_Storage_Interface::validate_file( $file, 10485760 ); // 10MB.
            if ( is_wp_error( $validation_result ) ) {
                return new \WP_REST_Response( [
                    'error' => sprintf(
                        /* translators: 1: field label, 2: error message */
                        __( 'Document "%1$s" failed validation: %2$s', 'eprocurement' ),
                        $field_key,
                        $validation_result->get_error_message()
                    ),
                ], 400 );
            }

            try {
                $safe_name = sanitize_file_name( $file['name'] );
                $upload_result = $storage->upload( $file['tmp_name'], $safe_name, 'submissions/' . $document_id );
                $doc_entry = [
                    'field_key'     => $field_key,
                    'file_name'     => $safe_name,
                    'file_size'     => $file['size'],
                    'file_type'     => $file['type'],
                    'cloud_provider'=> $storage->get_provider_name(),
                    'cloud_key'     => $upload_result['cloud_key'],
                    'cloud_url'     => $upload_result['cloud_url'],
                ];

                if ( $first_file === null ) {
                    $first_file = $doc_entry;
                } else {
                    $uploaded_docs[] = $doc_entry;
                }
            } catch ( \Exception $e ) {
                return new \WP_REST_Response( [
                    'error' => sprintf(
                        /* translators: 1: field key, 2: error message */
                        __( 'Failed to upload "%1$s": %2$s', 'eprocurement' ),
                        $field_key,
                        esc_html( $e->getMessage() )
                    ),
                ], 500 );
            }
        }

        if ( ! $first_file ) {
            return new \WP_REST_Response( [ 'error' => __( 'No files were uploaded.', 'eprocurement' ) ], 400 );
        }

        // Create the bid submission record with the first file.
        $submissions = new Eprocurement_Bid_Submissions();
        $primary_file = [
            'name'     => $first_file['file_name'],
            'size'     => $first_file['file_size'],
            'type'     => $first_file['file_type'],
            'tmp_name' => '', // Already uploaded to cloud.
            'error'    => UPLOAD_ERR_OK,
        ];

        // We need to pass the cloud info to submit(). Let's use a custom approach:
        // Create the submission record directly.
        $result = $this->create_submission_with_cloud(
            $document_id,
            $user_id,
            $first_file,
            $uploaded_docs
        );

        if ( is_wp_error( $result ) ) {
            $status = $result->get_error_data()['status'] ?? 400;
            return new \WP_REST_Response( [
                'error' => $result->get_error_message(),
                'code'  => $result->get_error_code(),
            ], $status );
        }

        return new \WP_REST_Response( [
            'success'       => true,
            'id'            => $result,
            'message'       => __( 'All documents submitted successfully.', 'eprocurement' ),
            'files_uploaded'=> count( $uploaded_docs ) + 1,
        ], 201 );
    }

    /**
     * Create a bid submission record with pre-uploaded cloud info.
     *
     * Used by per-document submission mode where files are already
     * uploaded to cloud storage before creating the record.
     */
    private function create_submission_with_cloud(
        int $document_id,
        int $user_id,
        array $primary_file,
        array $additional_files = []
    ): int|\WP_Error {
        // Check eligibility.
        $submissions = new Eprocurement_Bid_Submissions();
        $eligibility = $submissions->can_submit( $document_id, $user_id );
        if ( is_wp_error( $eligibility ) ) {
            return $eligibility;
        }

        // Insert the primary submission record.
        $submission_id = Eprocurement_Database::insert( 'bid_submissions', [
            'document_id'     => $document_id,
            'user_id'         => $user_id,
            'file_name'       => $primary_file['file_name'],
            'file_size'       => $primary_file['file_size'],
            'file_type'       => $primary_file['file_type'],
            'cloud_provider'  => $primary_file['cloud_provider'],
            'cloud_key'       => $primary_file['cloud_key'],
            'cloud_url'       => $primary_file['cloud_url'],
            'status'          => 'submitted',
            'is_late'         => Eprocurement_Documents::is_closing_date_past(
                Eprocurement_Database::get_by_id( 'documents', $document_id )->closing_date ?? null
            ) ? 1 : 0,
            'submitted_at'    => current_time( 'mysql' ),
            'created_at'      => current_time( 'mysql' ),
        ] );

        if ( ! $submission_id ) {
            return new \WP_Error( 'db_error', __( 'Failed to create submission record.', 'eprocurement' ) );
        }

        // Store additional files as supporting docs linked to the document.
        foreach ( $additional_files as $doc ) {
            Eprocurement_Database::insert( 'supporting_docs', [
                'document_id'    => $document_id,
                'file_name'      => $doc['file_name'],
                'file_size'      => $doc['file_size'],
                'file_type'      => $doc['file_type'],
                'cloud_provider' => $doc['cloud_provider'],
                'cloud_key'      => $doc['cloud_key'],
                'cloud_url'      => $doc['cloud_url'],
                'label'          => $doc['field_key'] ?? '',
                'sort_order'     => 0,
                'uploaded_by'    => $user_id,
                'created_at'     => current_time( 'mysql' ),
            ] );
        }

        // Fire the bid_submitted action.
        do_action( 'eprocurement_bid_submitted', $submission_id, $document_id, $user_id, false );

        return $submission_id;
    }

    /**
     * DELETE /submissions/{id} — Cancel a bid submission.
     */
    public function cancel_submission( \WP_REST_Request $request ): \WP_REST_Response {
        $submission_id = (int) $request->get_param( 'id' );

        $submissions = new Eprocurement_Bid_Submissions();
        $result      = $submissions->cancel( $submission_id, get_current_user_id() );

        if ( is_wp_error( $result ) ) {
            $status = $result->get_error_data()['status'] ?? 400;
            return new \WP_REST_Response( [
                'error' => $result->get_error_message(),
                'code'  => $result->get_error_code(),
            ], $status );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => __( 'Your submission has been cancelled. You may resubmit.', 'eprocurement' ),
        ] );
    }

    /**
     * GET /submissions — List the current bidder's submissions across all bids.
     */
    public function get_my_submissions( \WP_REST_Request $request ): \WP_REST_Response {
        $submissions = new Eprocurement_Bid_Submissions();
        $rows        = $submissions->get_submissions_for_user( get_current_user_id() );

        $items = array_map( function ( $sub ) {
            return [
                'id'           => (int) $sub->id,
                'document_id'  => (int) $sub->document_id,
                'bid_number'   => $sub->bid_number ?? '',
                'bid_title'    => $sub->bid_title ?? '',
                'bid_status'   => $sub->bid_status ?? '',
                'file_name'    => $sub->file_name,
                'file_size'    => (int) $sub->file_size,
                'file_type'    => $sub->file_type,
                'status'       => $sub->status,
                'is_late'      => (bool) $sub->is_late,
                'submitted_at' => $sub->submitted_at,
            ];
        }, $rows );

        return new \WP_REST_Response( [ 'items' => $items ] );
    }

    /**
     * GET /submissions/check?document_id={id} — Check if bidder is eligible to submit.
     *
     * Returns eligibility status, existing submission (if any), and bid details.
     */
    public function check_submission_eligibility( \WP_REST_Request $request ): \WP_REST_Response {
        $document_id = absint( $request->get_param( 'document_id' ) );
        $user_id     = get_current_user_id();

        $submissions = new Eprocurement_Bid_Submissions();
        $can_submit  = $submissions->can_submit( $document_id, $user_id );

        // Get existing active submission if any.
        $existing = $submissions->get_active_submission( $document_id, $user_id );

        $response = [
            'eligible'    => ! is_wp_error( $can_submit ),
            'has_existing' => $existing !== null,
        ];

        if ( is_wp_error( $can_submit ) ) {
            $response['reason'] = $can_submit->get_error_message();
            $response['code']   = $can_submit->get_error_code();
        }

        if ( $existing ) {
            $response['existing'] = [
                'id'           => (int) $existing->id,
                'file_name'    => $existing->file_name,
                'submitted_at' => $existing->submitted_at,
                'is_late'      => (bool) $existing->is_late,
            ];
        }

        // Check if briefing is compulsory.
        $response['briefing_compulsory'] = $submissions->is_briefing_compulsory( $document_id );

        return new \WP_REST_Response( $response );
    }

    // --- Permission Callbacks ---

    /**
     * Check if the current user is a verified bidder.
     */
    public function is_verified_bidder(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        $bidder = new Eprocurement_Bidder();
        return $bidder->is_verified( get_current_user_id() );
    }

    /**
     * Check if the current user can reply to threads.
     */
    public function can_reply(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }

        // Staff can reply
        if ( current_user_can( 'eproc_reply_threads' ) ) {
            return true;
        }

        // Verified bidders can reply to their own threads
        $bidder = new Eprocurement_Bidder();
        return $bidder->is_verified( get_current_user_id() );
    }

    /**
     * Check if the current user can upload files.
     */
    public function can_upload(): bool {
        return current_user_can( 'eproc_upload_documents' );
    }

    /**
     * Check if the current user is a bidder.
     */
    public function is_bidder(): bool {
        return is_user_logged_in() && Eprocurement_Roles::is_bidder();
    }

    /**
     * POST /profile — Update bidder profile.
     */
    public function update_profile( \WP_REST_Request $request ): \WP_REST_Response {
        $bidder  = new Eprocurement_Bidder();
        $user_id = get_current_user_id();

        $profile_data = [
            'company_name' => $request->get_param( 'company_name' ),
            'company_reg'  => $request->get_param( 'company_reg' ),
            'phone'        => $request->get_param( 'phone' ),
        ];

        // Include notify_replies if provided
        $notify_replies = $request->get_param( 'notify_replies' );
        if ( $notify_replies !== null ) {
            $profile_data['notify_replies'] = $notify_replies;
        }

        $result = $bidder->update_profile( $user_id, $profile_data );

        if ( $result === false ) {
            return new \WP_REST_Response( [ 'error' => __( 'Failed to update profile.', 'eprocurement' ) ], 500 );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'message' => __( 'Profile updated successfully.', 'eprocurement' ),
        ] );
    }
}
