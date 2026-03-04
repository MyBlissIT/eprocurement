<?php
/**
 * Bid Document Management.
 *
 * Handles CRUD operations for bid/tender documents, status transitions,
 * and supporting file management.
 *
 * Status workflow: draft → open → closed → archived | cancelled
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Documents {

    /**
     * Valid status transitions.
     */
    private const STATUS_TRANSITIONS = [
        'draft'     => [ 'open', 'cancelled' ],
        'open'      => [ 'closed', 'cancelled' ],
        'closed'    => [ 'archived' ],
        'cancelled' => [],
        'archived'  => [],
    ];

    /**
     * Create a new bid document.
     *
     * @param array $data Document data.
     * @return int|false Document ID on success, false on failure.
     */
    public function create( array $data ): int|false {
        $sanitised = $this->sanitise_input( $data );

        $sanitised['created_by'] = get_current_user_id();
        $sanitised['status']     = 'draft';
        $sanitised['created_at'] = current_time( 'mysql' );
        $sanitised['updated_at'] = current_time( 'mysql' );

        return Eprocurement_Database::insert( 'documents', $sanitised );
    }

    /**
     * Update an existing bid document.
     *
     * @param int   $id   Document ID.
     * @param array $data Fields to update.
     * @return int|false Rows affected or false on error.
     */
    public function update( int $id, array $data ): int|false {
        $sanitised = $this->sanitise_input( $data );
        $sanitised['updated_at'] = current_time( 'mysql' );

        return Eprocurement_Database::update( 'documents', $sanitised, [ 'id' => $id ] );
    }

    /**
     * Get a single document by ID.
     */
    public function get( int $id ): ?object {
        return Eprocurement_Database::get_by_id( 'documents', $id );
    }

    /**
     * Get a document by bid number.
     */
    public function get_by_bid_number( string $bid_number, string $category = 'bid' ): ?object {
        global $wpdb;

        $table = Eprocurement_Database::table( 'documents' );

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE bid_number = %s AND category = %s", $bid_number, $category ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    /**
     * List documents with filters.
     *
     * @param array $args {
     *     Optional. Query arguments.
     *     @type string $status   Filter by status.
     *     @type string $search   Search in title and bid_number.
     *     @type string $orderby  Column to order by.
     *     @type string $order    ASC or DESC.
     *     @type int    $per_page Items per page.
     *     @type int    $page     Page number.
     * }
     * @return array{items: array, total: int, pages: int}
     */
    public function list( array $args = [] ): array {
        global $wpdb;

        $table   = Eprocurement_Database::table( 'documents' );
        $where   = [];
        $values  = [];
        $orderby = 'created_at';
        $order   = 'DESC';
        $limit   = absint( $args['per_page'] ?? 12 );
        $page    = max( 1, absint( $args['page'] ?? 1 ) );
        $offset  = ( $page - 1 ) * $limit;

        // Status filter
        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = sanitize_text_field( $args['status'] );
        }

        // Category filter
        if ( ! empty( $args['category'] ) ) {
            $where[]  = 'category = %s';
            $values[] = sanitize_text_field( $args['category'] );
        } elseif ( empty( $args['include_all_categories'] ) ) {
            $where[]  = "category = 'bid'";
        }

        // Exclude non-public statuses from frontend listing by default.
        // Admin callers can pass 'include_all_statuses' => true to see everything.
        if ( empty( $args['include_all_statuses'] ) && empty( $args['status'] ) ) {
            $where[] = "status IN ('open', 'closed')";
        }

        // Search filter
        if ( ! empty( $args['search'] ) ) {
            $search   = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $where[]  = '(title LIKE %s OR bid_number LIKE %s)';
            $values[] = $search;
            $values[] = $search;
        }

        // Build WHERE clause
        $where_sql = '';
        if ( ! empty( $where ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where );
        }

        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! empty( $values ) ) {
            $count_sql = $wpdb->prepare( $count_sql, ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        $total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Whitelist orderby
        $allowed_order = [ 'id', 'bid_number', 'title', 'status', 'category', 'opening_date', 'closing_date', 'created_at' ];
        if ( ! empty( $args['orderby'] ) && in_array( $args['orderby'], $allowed_order, true ) ) {
            $orderby = $args['orderby'];
        }
        if ( ! empty( $args['order'] ) && strtoupper( $args['order'] ) === 'ASC' ) {
            $order = 'ASC';
        }

        // Fetch rows
        $query_values   = $values;
        $query_values[] = $limit;
        $query_values[] = $offset;

        $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ( ! empty( $query_values ) ) {
            $sql = $wpdb->prepare( $sql, ...$query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        $items = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return [
            'items' => $items,
            'total' => $total,
            'pages' => $limit > 0 ? (int) ceil( $total / $limit ) : 1,
        ];
    }

    /**
     * Transition a document to a new status.
     *
     * @param int    $id         Document ID.
     * @param string $new_status Target status.
     * @return bool True on success.
     */
    public function transition_status( int $id, string $new_status ): bool {
        $document = $this->get( $id );
        if ( ! $document ) {
            return false;
        }

        $allowed = self::STATUS_TRANSITIONS[ $document->status ] ?? [];
        if ( ! in_array( $new_status, $allowed, true ) ) {
            return false;
        }

        $result = Eprocurement_Database::update(
            'documents',
            [ 'status' => $new_status, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $id ]
        );

        if ( $result !== false ) {
            /**
             * Fires after a document status changes.
             *
             * @param int    $document_id Document ID.
             * @param string $new_status  New status.
             * @param string $old_status  Previous status.
             */
            do_action( 'eprocurement_status_changed', $id, $new_status, $document->status );
        }

        return $result !== false;
    }

    /**
     * Delete a document and its related records.
     *
     * @param int $id Document ID.
     * @return bool True on success.
     */
    public function delete( int $id ): bool {
        // Delete bid docs (cloud files should be manually managed)
        Eprocurement_Database::delete( 'supporting_docs', [ 'document_id' => $id ] );

        // Delete threads and messages
        global $wpdb;
        $threads_table  = Eprocurement_Database::table( 'threads' );
        $messages_table = Eprocurement_Database::table( 'messages' );
        $attachments_table = Eprocurement_Database::table( 'message_attachments' );

        // Get thread IDs for this document
        $thread_ids = $wpdb->get_col(
            $wpdb->prepare( "SELECT id FROM {$threads_table} WHERE document_id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        if ( ! empty( $thread_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $thread_ids ), '%d' ) );

            // Delete message attachments
            $msg_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$messages_table} WHERE thread_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
                    ...$thread_ids
                )
            );

            if ( ! empty( $msg_ids ) ) {
                $msg_placeholders = implode( ',', array_fill( 0, count( $msg_ids ), '%d' ) );
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$attachments_table} WHERE message_id IN ({$msg_placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
                        ...$msg_ids
                    )
                );
            }

            // Delete messages
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$messages_table} WHERE thread_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
                    ...$thread_ids
                )
            );

            // Delete threads
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$threads_table} WHERE document_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $id
                )
            );
        }

        // Delete downloads log
        Eprocurement_Database::delete( 'downloads', [ 'document_id' => $id ] );

        // Delete the document
        $result = Eprocurement_Database::delete( 'documents', [ 'id' => $id ] );

        return $result !== false;
    }

    /**
     * Archive closed bids that have exceeded retention period.
     * Called by WP-Cron daily.
     */
    public function archive_expired_closed_bids(): void {
        $retention_days = get_option( 'eprocurement_closed_bid_retention_days', '' );

        // Empty = keep forever, don't archive
        if ( $retention_days === '' || ! is_numeric( $retention_days ) ) {
            return;
        }

        $days = absint( $retention_days );
        if ( $days < 1 ) {
            return;
        }

        global $wpdb;

        $table    = Eprocurement_Database::table( 'documents' );
        $cutoff   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'archived', updated_at = %s WHERE status = 'closed' AND updated_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                current_time( 'mysql' ),
                $cutoff
            )
        );
    }

    /**
     * Auto-close open bids whose closing date has passed.
     * Called by WP-Cron daily + on page load checks.
     */
    public function auto_close_expired_bids(): void {
        global $wpdb;

        $table = Eprocurement_Database::table( 'documents' );
        $now   = current_time( 'mysql' );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'closed', updated_at = %s WHERE status = 'open' AND closing_date IS NOT NULL AND closing_date > '0000-00-00 00:00:00' AND closing_date <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $now,
                $now
            )
        );
    }

    /**
     * Check if a closing date is in the past.
     *
     * @param string|null $closing_date The closing date string.
     * @return bool True if the date is in the past.
     */
    public static function is_closing_date_past( ?string $closing_date ): bool {
        if ( empty( $closing_date ) ) {
            return false;
        }

        $closing_ts = strtotime( $closing_date );
        return $closing_ts && $closing_ts <= current_time( 'timestamp' );
    }

    /**
     * Get bid documents for a bid.
     *
     * @param int $document_id Document ID.
     * @return array Array of bid doc objects.
     */
    public function get_supporting_docs( int $document_id ): array {
        return Eprocurement_Database::get_rows(
            'supporting_docs',
            [ 'document_id' => $document_id ],
            'sort_order',
            'ASC'
        );
    }

    /**
     * Add a bid document record (file already uploaded to cloud).
     *
     * @param array $data Bid doc data.
     * @return int|false Insert ID or false.
     */
    public function add_supporting_doc( array $data ): int|false {
        return Eprocurement_Database::insert( 'supporting_docs', [
            'document_id'    => absint( $data['document_id'] ),
            'file_name'      => sanitize_file_name( $data['file_name'] ),
            'file_size'      => absint( $data['file_size'] ),
            'file_type'      => sanitize_text_field( $data['file_type'] ),
            'cloud_provider' => sanitize_text_field( $data['cloud_provider'] ),
            'cloud_key'      => sanitize_text_field( $data['cloud_key'] ),
            'cloud_url'      => esc_url_raw( $data['cloud_url'] ),
            'label'          => sanitize_text_field( $data['label'] ?? '' ),
            'sort_order'     => absint( $data['sort_order'] ?? 0 ),
            'uploaded_by'    => get_current_user_id(),
            'created_at'     => current_time( 'mysql' ),
        ] );
    }

    /**
     * Remove a bid document record.
     */
    public function remove_supporting_doc( int $id ): bool {
        $result = Eprocurement_Database::delete( 'supporting_docs', [ 'id' => $id ] );
        return $result !== false;
    }

    /**
     * Get status counts for dashboard.
     *
     * @return array Associative array of status => count.
     */
    public function get_status_counts(): array {
        global $wpdb;

        $table   = Eprocurement_Database::table( 'documents' );
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        $counts = [
            'draft'     => 0,
            'open'      => 0,
            'closed'    => 0,
            'cancelled' => 0,
            'archived'  => 0,
        ];

        foreach ( $results as $row ) {
            $counts[ $row['status'] ] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Sanitise input data for document operations.
     */
    private function sanitise_input( array $data ): array {
        $sanitised = [];

        $text_fields = [ 'bid_number', 'title', 'status', 'category' ];
        foreach ( $text_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $sanitised[ $field ] = sanitize_text_field( $data[ $field ] );
            }
        }

        if ( isset( $data['description'] ) ) {
            $sanitised['description'] = wp_kses_post( $data['description'] );
        }

        $int_fields = [ 'scm_contact_id', 'technical_contact_id', 'created_by' ];
        foreach ( $int_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $sanitised[ $field ] = $data[ $field ] ? absint( $data[ $field ] ) : null;
            }
        }

        $date_fields = [ 'opening_date', 'briefing_date', 'closing_date' ];
        foreach ( $date_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $sanitised[ $field ] = $data[ $field ] ? sanitize_text_field( $data[ $field ] ) : null;
            }
        }

        return $sanitised;
    }
}
