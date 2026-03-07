<?php
/**
 * Query & Messaging System.
 *
 * Manages threads and messages between bidders and contact persons.
 * Supports public (Q&A) and private visibility.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Messaging {

    /**
     * Create a new thread (query from a bidder).
     *
     * @param array $data Thread data.
     * @return int|false Thread ID or false.
     */
    public function create_thread( array $data ): int|false {
        $document_id = absint( $data['document_id'] ?? 0 );
        $contact_id  = absint( $data['contact_id'] ?? 0 );
        $bidder_id   = absint( $data['bidder_id'] ?? get_current_user_id() );

        // Get document for subject generation
        $document = Eprocurement_Database::get_by_id( 'documents', $document_id );
        if ( ! $document ) {
            return false;
        }

        $subject = sprintf(
            'Query: %s — %s',
            $document->bid_number,
            $document->title
        );

        $visibility = in_array( $data['visibility'] ?? '', [ 'private', 'public' ], true )
            ? $data['visibility']
            : 'private';

        $thread_id = Eprocurement_Database::insert( 'threads', [
            'document_id' => $document_id,
            'bidder_id'   => $bidder_id,
            'contact_id'  => $contact_id,
            'subject'     => sanitize_text_field( $subject ),
            'visibility'  => $visibility,
            'status'      => 'open',
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ] );

        if ( $thread_id && ! empty( $data['message'] ) ) {
            $this->add_message( $thread_id, $bidder_id, $data['message'] );
        }

        return $thread_id;
    }

    /**
     * Add a message to a thread.
     *
     * @param int    $thread_id Thread ID.
     * @param int    $sender_id User ID of sender.
     * @param string $message   Message content.
     * @return int|false Message ID or false.
     */
    public function add_message( int $thread_id, int $sender_id, string $message ): int|false {
        $message_id = Eprocurement_Database::insert( 'messages', [
            'thread_id'  => $thread_id,
            'sender_id'  => $sender_id,
            'message'    => wp_kses_post( $message ),
            'is_read'    => 0,
            'created_at' => current_time( 'mysql' ),
        ] );

        if ( $message_id ) {
            // Update thread's updated_at timestamp
            Eprocurement_Database::update(
                'threads',
                [ 'updated_at' => current_time( 'mysql' ) ],
                [ 'id' => $thread_id ]
            );
        }

        return $message_id;
    }

    /**
     * Add an attachment to a message.
     *
     * @param int   $message_id Message ID.
     * @param array $file_data  File data from cloud upload.
     * @return int|false Attachment ID or false.
     */
    public function add_attachment( int $message_id, array $file_data ): int|false {
        return Eprocurement_Database::insert( 'message_attachments', [
            'message_id'     => $message_id,
            'file_name'      => sanitize_file_name( $file_data['file_name'] ),
            'file_size'      => absint( $file_data['file_size'] ),
            'file_type'      => sanitize_text_field( $file_data['file_type'] ),
            'cloud_provider' => sanitize_text_field( $file_data['cloud_provider'] ),
            'cloud_key'      => sanitize_text_field( $file_data['cloud_key'] ),
            'cloud_url'      => esc_url_raw( $file_data['cloud_url'] ),
            'created_at'     => current_time( 'mysql' ),
        ] );
    }

    /**
     * Get a thread by ID with access control.
     *
     * @param int $thread_id Thread ID.
     * @param int $user_id   Current user ID for access check.
     * @return object|null Thread or null if no access.
     */
    public function get_thread( int $thread_id, int $user_id = 0 ): ?object {
        $thread = Eprocurement_Database::get_by_id( 'threads', $thread_id );
        if ( ! $thread ) {
            return null;
        }

        // Always enforce ACL — caller must provide a valid user_id
        if ( $user_id < 1 || ! $this->can_view_thread( $thread, $user_id ) ) {
            return null;
        }

        return $thread;
    }

    /**
     * Get messages for a thread.
     */
    public function get_messages( int $thread_id ): array {
        return Eprocurement_Database::get_rows(
            'messages',
            [ 'thread_id' => $thread_id ],
            'created_at',
            'ASC'
        );
    }

    /**
     * Get attachments for a message.
     */
    public function get_attachments( int $message_id ): array {
        return Eprocurement_Database::get_rows(
            'message_attachments',
            [ 'message_id' => $message_id ],
            'id',
            'ASC'
        );
    }

    /**
     * Get threads for a specific document.
     *
     * @param int    $document_id Document ID.
     * @param string $visibility  Filter: 'public', 'private', or '' (all).
     * @return array Array of thread objects.
     */
    public function get_threads_for_document( int $document_id, string $visibility = '' ): array {
        $where = [ 'document_id' => $document_id ];
        if ( $visibility && in_array( $visibility, [ 'public', 'private' ], true ) ) {
            $where['visibility'] = $visibility;
        }

        return Eprocurement_Database::get_rows( 'threads', $where, 'updated_at', 'DESC' );
    }

    /**
     * Get threads for a bidder (their dashboard).
     */
    public function get_threads_for_bidder( int $bidder_id ): array {
        return Eprocurement_Database::get_rows(
            'threads',
            [ 'bidder_id' => $bidder_id ],
            'updated_at',
            'DESC'
        );
    }

    /**
     * Get threads for a contact person (admin inbox).
     */
    public function get_threads_for_contact( int $contact_id ): array {
        return Eprocurement_Database::get_rows(
            'threads',
            [ 'contact_id' => $contact_id ],
            'updated_at',
            'DESC'
        );
    }

    /**
     * Get all threads for the admin inbox (staff view).
     *
     * @param array $args Optional filters.
     * @return array{items: array, total: int}
     */
    public function get_admin_inbox( array $args = [] ): array {
        global $wpdb;

        $table   = Eprocurement_Database::table( 'threads' );
        $where   = [];
        $values  = [];
        $limit   = absint( $args['per_page'] ?? 20 );
        $page    = max( 1, absint( $args['page'] ?? 1 ) );
        $offset  = ( $page - 1 ) * $limit;

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'status = %s';
            $values[] = sanitize_text_field( $args['status'] );
        }

        if ( ! empty( $args['visibility'] ) ) {
            $where[]  = 'visibility = %s';
            $values[] = sanitize_text_field( $args['visibility'] );
        }

        if ( ! empty( $args['document_id'] ) ) {
            $where[]  = 'document_id = %d';
            $values[] = absint( $args['document_id'] );
        }

        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        // Count
        $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! empty( $values ) ) {
            $count_sql = $wpdb->prepare( $count_sql, ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        $total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Fetch
        $query_values   = $values;
        $query_values[] = $limit;
        $query_values[] = $offset;

        $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! empty( $query_values ) ) {
            $sql = $wpdb->prepare( $sql, ...$query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        return [
            'items' => $wpdb->get_results( $sql ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'total' => $total,
        ];
    }

    /**
     * Mark all messages in a thread as read for a user.
     */
    public function mark_thread_read( int $thread_id, int $user_id ): void {
        global $wpdb;

        $table = Eprocurement_Database::table( 'messages' );

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET is_read = 1 WHERE thread_id = %d AND sender_id != %d AND is_read = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $thread_id,
                $user_id
            )
        );
    }

    /**
     * Get unread message count for a user.
     */
    public function get_unread_count( int $user_id ): int {
        global $wpdb;

        $threads_table  = Eprocurement_Database::table( 'threads' );
        $messages_table = Eprocurement_Database::table( 'messages' );

        // For staff: unread messages in threads assigned to their contact
        if ( Eprocurement_Roles::is_staff( $user_id ) ) {
            $contacts_table = Eprocurement_Database::table( 'contact_persons' );

            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(m.id) FROM {$messages_table} m
                     JOIN {$threads_table} t ON m.thread_id = t.id
                     JOIN {$contacts_table} c ON t.contact_id = c.id
                     WHERE c.user_id = %d AND m.sender_id != %d AND m.is_read = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $user_id,
                    $user_id
                )
            );
        }

        // For bidders: unread messages in their threads
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(m.id) FROM {$messages_table} m
                 JOIN {$threads_table} t ON m.thread_id = t.id
                 WHERE t.bidder_id = %d AND m.sender_id != %d AND m.is_read = 0", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $user_id,
                $user_id
            )
        );
    }

    /**
     * Close a thread (mark as resolved).
     */
    public function close_thread( int $thread_id ): bool {
        $result = Eprocurement_Database::update(
            'threads',
            [ 'status' => 'resolved', 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $thread_id ]
        );

        return $result !== false;
    }

    /**
     * Change thread visibility (e.g. private → public).
     *
     * @param int    $thread_id  Thread ID.
     * @param string $visibility New visibility ('public' or 'private').
     * @return bool
     */
    public function update_visibility( int $thread_id, string $visibility ): bool {
        if ( ! in_array( $visibility, [ 'public', 'private' ], true ) ) {
            return false;
        }

        $result = Eprocurement_Database::update(
            'threads',
            [ 'visibility' => $visibility, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $thread_id ]
        );

        return $result !== false;
    }

    /**
     * Check if a user can view a thread based on visibility rules.
     */
    private function can_view_thread( object $thread, int $user_id ): bool {
        // Staff can always see all threads
        if ( Eprocurement_Roles::is_staff( $user_id ) ) {
            return true;
        }

        // Thread creator can always see their own thread
        if ( (int) $thread->bidder_id === $user_id ) {
            return true;
        }

        // Public threads are visible to all registered bidders
        if ( $thread->visibility === 'public' && Eprocurement_Roles::is_bidder( $user_id ) ) {
            return true;
        }

        // Private threads: only bidder + contact person
        return false;
    }
}
