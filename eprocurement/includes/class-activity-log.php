<?php
/**
 * Activity Log.
 *
 * Records key procurement actions (bid created, status changed, queries
 * submitted, bids awarded, etc.) into an append-only option. Surfaced
 * on the admin dashboard as a "Recent Activity" feed.
 *
 * @package Eprocurement
 * @since   2.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Activity_Log {

    /**
     * Maximum entries returned by get_recent() by default.
     * Audit fix A10: the log is now stored in a dedicated DB table
     * (wp_eproc_audit_log) with append-only INSERT. No cap needed —
     * table grows naturally and is indexed by created_at for fast paging.
     */
    private const DEFAULT_LIMIT = 200;

    /**
     * Record a new activity entry.
     *
     * Audit fix A10: now uses an append-only INSERT into the audit_log
     * table instead of read-modify-write on a wp_options array. Eliminates
     * the race condition where two concurrent requests could lose entries,
     * and removes the O(N) per-write cost as the log grows.
     *
     * @param string $type    Activity type (bid, status, query, reply, submission, award, download, bidder).
     * @param string $message Human-readable description (may contain HTML via sprintf).
     * @param int    $user_id User who performed the action (0 = system/cron).
     * @param array  $context Optional context data (document_id, etc.).
     */
    public static function log( string $type, string $message, int $user_id = 0, array $context = [] ): void {
        global $wpdb;

        $user = $user_id ? get_userdata( $user_id ) : null;

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            Eprocurement_Database::table( 'audit_log' ),
            [
                'event_type' => sanitize_key( $type ),
                'message'    => $message, // Pre-escaped by caller
                'user_id'    => $user_id,
                'user_name'  => $user ? $user->display_name : __( 'System', 'eprocurement' ),
                'user_email' => $user ? $user->user_email : '',
                'context'    => $context ? wp_json_encode( $context ) : null,
                'ip_address' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
                'created_at' => current_time( 'mysql', true ),
            ],
            [ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Get recent activity entries.
     *
     * @param int $limit Maximum entries to return (default 10).
     * @return array Array of activity entry arrays.
     */
    public static function get_recent( int $limit = 10 ): array {
        global $wpdb;

        $table = Eprocurement_Database::table( 'audit_log' );
        $limit = min( $limit, self::DEFAULT_LIMIT );

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d",
                $limit
            )
        );

        // Decode context JSON and return in the same shape as the old option-based log.
        $entries = [];
        foreach ( $rows as $row ) {
            $entries[] = [
                'type'       => $row->event_type,
                'message'    => $row->message,
                'user_id'    => (int) $row->user_id,
                'user_name'  => $row->user_name,
                'user_email' => $row->user_email,
                'context'    => $row->context ? json_decode( $row->context, true ) : [],
                'timestamp'  => $row->created_at,
            ];
        }
        return $entries;
    }

    /**
     * Migrate existing option-based log entries into the new DB table.
     *
     * Called once during the v2.18.0 upgrade. Idempotent — if the option
     * is empty or already migrated, this is a no-op.
     */
    public static function migrate_from_options(): void {
        $old_activity = get_option( 'eproc_activity_log', [] );
        $old_audit    = get_option( 'eproc_audit_log', [] );

        if ( is_array( $old_activity ) && ! empty( $old_activity ) ) {
            foreach ( $old_activity as $entry ) {
                self::log(
                    $entry['type'] ?? 'legacy',
                    $entry['message'] ?? '',
                    (int) ( $entry['user_id'] ?? 0 ),
                    (array) ( $entry['context'] ?? [] )
                );
            }
            delete_option( 'eproc_activity_log' );
        }

        if ( is_array( $old_audit ) && ! empty( $old_audit ) ) {
            global $wpdb;
            foreach ( $old_audit as $entry ) {
                $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    Eprocurement_Database::table( 'audit_log' ),
                    [
                        'event_type' => 'submission_backdated',
                        'message'    => wp_json_encode( $entry ),
                        'user_id'    => (int) ( $entry['admin_id'] ?? 0 ),
                        'user_name'  => '',
                        'user_email' => $entry['admin_email'] ?? '',
                        'context'    => wp_json_encode( $entry ),
                        'ip_address' => $entry['ip'] ?? '',
                        'created_at' => $entry['timestamp'] ?? current_time( 'mysql', true ),
                    ],
                    [ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
                );
            }
            delete_option( 'eproc_audit_log' );
        }
    }

    /**
     * Register hooks that log key actions.
     */
    public static function register_hooks(): void {
        // Bid published (draft → open).
        add_action( 'eprocurement_bid_published', function ( int $doc_id ): void {
            $doc = Eprocurement_Database::get_by_id( 'documents', $doc_id );
            if ( ! $doc ) return;
            self::log( 'bid', sprintf(
                /* translators: 1: bid number, 2: bid title */
                __( 'Tender <strong>%1$s — %2$s</strong> was published and is now open for bidding.', 'eprocurement' ),
                esc_html( $doc->bid_number ),
                esc_html( $doc->title )
            ), get_current_user_id(), [ 'document_id' => $doc_id ] );
        } );

        // Status changed.
        add_action( 'eprocurement_status_changed', function ( int $doc_id, string $new_status, string $old_status ): void {
            $doc = Eprocurement_Database::get_by_id( 'documents', $doc_id );
            if ( ! $doc ) return;
            self::log( 'status', sprintf(
                /* translators: 1: bid number, 2: old status, 3: new status */
                __( 'Status of <strong>%1$s</strong> changed from %2$s to %3$s.', 'eprocurement' ),
                esc_html( $doc->bid_number ),
                esc_html( ucfirst( $old_status ) ),
                esc_html( ucfirst( $new_status ) )
            ), get_current_user_id(), [ 'document_id' => $doc_id ] );
        }, 10, 3 );

        // Query created.
        add_action( 'eprocurement_query_created', function ( int $thread_id, int $message_id ): void {
            $thread = Eprocurement_Database::get_by_id( 'threads', $thread_id );
            if ( ! $thread ) return;
            $doc = Eprocurement_Database::get_by_id( 'documents', (int) $thread->document_id );
            $bidder = get_userdata( (int) $thread->bidder_id );
            self::log( 'query', sprintf(
                /* translators: 1: bidder name, 2: bid number */
                __( '<strong>%1$s</strong> submitted a query on <strong>%2$s</strong>.', 'eprocurement' ),
                esc_html( $bidder ? $bidder->display_name : 'Unknown' ),
                esc_html( $doc ? $doc->bid_number : 'Unknown' )
            ), (int) $thread->bidder_id, [ 'document_id' => (int) $thread->document_id, 'thread_id' => $thread_id ] );
        }, 10, 2 );

        // Reply posted.
        add_action( 'eprocurement_reply_posted', function ( int $thread_id, int $message_id ): void {
            $thread = Eprocurement_Database::get_by_id( 'threads', $thread_id );
            if ( ! $thread ) return;
            $doc = Eprocurement_Database::get_by_id( 'documents', (int) $thread->document_id );
            $sender = get_userdata( get_current_user_id() );
            self::log( 'reply', sprintf(
                /* translators: 1: sender name, 2: bid number */
                __( '<strong>%1$s</strong> replied to a query on <strong>%2$s</strong>.', 'eprocurement' ),
                esc_html( $sender ? $sender->display_name : 'Staff' ),
                esc_html( $doc ? $doc->bid_number : 'Unknown' )
            ), get_current_user_id(), [ 'document_id' => (int) $thread->document_id, 'thread_id' => $thread_id ] );
        }, 10, 2 );

        // Bid submitted.
        add_action( 'eprocurement_bid_submitted', function ( int $submission_id, int $document_id, int $user_id = 0, bool $is_late = false ): void {
            $doc = Eprocurement_Database::get_by_id( 'documents', $document_id );
            $sub = Eprocurement_Database::get_by_id( 'bid_submissions', $submission_id );
            $bidder = $sub ? get_userdata( (int) $sub->user_id ) : null;
            $profile = $sub ? ( new Eprocurement_Bidder() )->get_profile( (int) $sub->user_id ) : null;
            self::log( 'submission', sprintf(
                /* translators: 1: company name, 2: bid number */
                __( '<strong>%1$s</strong> submitted a bid for <strong>%2$s</strong>.', 'eprocurement' ),
                esc_html( $profile ? $profile->company_name : ( $bidder ? $bidder->display_name : 'Unknown' ) ),
                esc_html( $doc ? $doc->bid_number : 'Unknown' )
            ), $sub ? (int) $sub->user_id : 0, [ 'document_id' => $document_id, 'submission_id' => $submission_id ] );
        }, 10, 4 );

        // Bid awarded.
        add_action( 'eprocurement_bid_awarded', function ( int $document_id, int $winner_user_id ): void {
            $doc = Eprocurement_Database::get_by_id( 'documents', $document_id );
            $winner = get_userdata( $winner_user_id );
            $profile = ( new Eprocurement_Bidder() )->get_profile( $winner_user_id );
            self::log( 'award', sprintf(
                /* translators: 1: bid number, 2: company name */
                __( 'Tender <strong>%1$s</strong> was awarded to <strong>%2$s</strong>.', 'eprocurement' ),
                esc_html( $doc ? $doc->bid_number : 'Unknown' ),
                esc_html( $profile ? $profile->company_name : ( $winner ? $winner->display_name : 'Unknown' ) )
            ), get_current_user_id(), [ 'document_id' => $document_id, 'winner_user_id' => $winner_user_id ] );
        }, 10, 2 );

        // Bidder registration.
        add_action( 'user_register', function ( int $user_id ): void {
            $user = get_userdata( $user_id );
            if ( ! $user ) return;
            if ( ! in_array( 'eprocurement_subscriber', (array) $user->roles, true ) ) return;
            $profile = ( new Eprocurement_Bidder() )->get_profile( $user_id );
            self::log( 'bidder', sprintf(
                /* translators: %s: company name */
                __( 'New bidder registered: <strong>%s</strong>.', 'eprocurement' ),
                esc_html( $profile ? $profile->company_name : $user->display_name )
            ), $user_id );
        } );
    }

    /**
     * Get the SVG icon for an activity type.
     *
     * @param string $type Activity type.
     * @return string Inline SVG.
     */
    public static function get_icon( string $type ): string {
        $icons = [
            'bid'        => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/></svg>',
            'status'     => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5z" clip-rule="evenodd"/></svg>',
            'query'      => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9a1 1 0 011-1h4a1 1 0 110 2H8a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>',
            'reply'      => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 017 7v2a1 1 0 11-2 0v-2a5 5 0 00-5-5H5.414l2.293 2.293a1 1 0 11-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>',
            'submission' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>',
            'award'      => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zm7-10a1 1 0 01.945.671L14.118 6H17a1 1 0 110 2h-.018l-.382 1.428a1 1 0 01-1.94-.514L14.732 8h-2.99l-.276.829a1 1 0 11-1.94-.514l2-6A1 1 0 0112 2z" clip-rule="evenodd"/></svg>',
            'download'   => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>',
            'bidder'     => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>',
        ];
        return $icons[ $type ] ?? $icons['bid'];
    }
}
