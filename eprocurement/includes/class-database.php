<?php
/**
 * Database helper class.
 *
 * Provides shared query methods and table name resolution
 * for all eProcurement database operations.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Database {

    /**
     * Get the full table name with WordPress prefix.
     */
    public static function table( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . EPROC_TABLE_PREFIX . $name;
    }

    /**
     * Insert a row and return the insert ID.
     *
     * @param string $table  Short table name (e.g., 'documents').
     * @param array  $data   Column => value pairs.
     * @param array  $format Format strings (%s, %d, %f).
     * @return int|false Insert ID on success, false on failure.
     */
    public static function insert( string $table, array $data, array $format = [] ): int|false {
        global $wpdb;

        $result = $wpdb->insert( self::table( $table ), $data, $format ?: null );

        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Update rows in a table.
     *
     * @param string $table        Short table name.
     * @param array  $data         Column => value pairs to update.
     * @param array  $where        Column => value pairs for WHERE clause.
     * @param array  $format       Format for $data values.
     * @param array  $where_format Format for $where values.
     * @return int|false Number of rows affected, or false on error.
     */
    public static function update( string $table, array $data, array $where, array $format = [], array $where_format = [] ): int|false {
        global $wpdb;

        return $wpdb->update(
            self::table( $table ),
            $data,
            $where,
            $format ?: null,
            $where_format ?: null
        );
    }

    /**
     * Delete rows from a table.
     *
     * @param string $table        Short table name.
     * @param array  $where        Column => value pairs.
     * @param array  $where_format Format for $where values.
     * @return int|false Number of rows affected, or false on error.
     */
    public static function delete( string $table, array $where, array $where_format = [] ): int|false {
        global $wpdb;

        return $wpdb->delete(
            self::table( $table ),
            $where,
            $where_format ?: null
        );
    }

    /**
     * Get a single row by ID.
     *
     * @param string $table Short table name.
     * @param int    $id    Row ID.
     * @return object|null Row object or null.
     */
    public static function get_by_id( string $table, int $id ): ?object {
        global $wpdb;

        $full_table = self::table( $table );

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$full_table} WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    /**
     * Get multiple rows with optional conditions.
     *
     * @param string $table   Short table name.
     * @param array  $where   Associative array of conditions (column => value).
     * @param string $orderby Column name to order by.
     * @param string $order   ASC or DESC.
     * @param int    $limit   Maximum rows to return (0 = unlimited).
     * @param int    $offset  Offset for pagination.
     * @return array Array of row objects.
     */
    public static function get_rows(
        string $table,
        array $where = [],
        string $orderby = 'id',
        string $order = 'DESC',
        int $limit = 0,
        int $offset = 0
    ): array {
        global $wpdb;

        $full_table = self::table( $table );
        $order      = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

        // Whitelist orderby to prevent SQL injection
        $allowed_columns = self::get_table_columns( $table );
        if ( ! in_array( $orderby, $allowed_columns, true ) ) {
            $orderby = 'id';
        }

        $sql    = "SELECT * FROM {$full_table}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $values = [];

        if ( ! empty( $where ) ) {
            $conditions = [];
            foreach ( $where as $column => $value ) {
                if ( in_array( $column, $allowed_columns, true ) ) {
                    $conditions[] = "{$column} = %s";
                    $values[]     = $value;
                }
            }
            if ( ! empty( $conditions ) ) {
                $sql .= ' WHERE ' . implode( ' AND ', $conditions );
            }
        }

        $sql .= " ORDER BY {$orderby} {$order}";

        if ( $limit > 0 ) {
            $sql     .= ' LIMIT %d OFFSET %d';
            $values[] = $limit;
            $values[] = $offset;
        }

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Count rows matching conditions.
     *
     * @param string $table Short table name.
     * @param array  $where Conditions.
     * @return int Row count.
     */
    public static function count( string $table, array $where = [] ): int {
        global $wpdb;

        $full_table = self::table( $table );
        $sql        = "SELECT COUNT(*) FROM {$full_table}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $values     = [];

        if ( ! empty( $where ) ) {
            $allowed    = self::get_table_columns( $table );
            $conditions = [];
            foreach ( $where as $column => $value ) {
                if ( in_array( $column, $allowed, true ) ) {
                    $conditions[] = "{$column} = %s";
                    $values[]     = $value;
                }
            }
            if ( ! empty( $conditions ) ) {
                $sql .= ' WHERE ' . implode( ' AND ', $conditions );
            }
        }

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Get allowed column names for a table (for whitelist validation).
     *
     * @param string $table Short table name.
     * @return array List of column names.
     */
    private static function get_table_columns( string $table ): array {
        $columns = [
            'documents'           => [ 'id', 'bid_number', 'title', 'description', 'status', 'category', 'scm_contact_id', 'technical_contact_id', 'opening_date', 'briefing_date', 'closing_date', 'created_by', 'created_at', 'updated_at' ],
            'contact_persons'     => [ 'id', 'user_id', 'type', 'name', 'phone', 'email', 'department', 'created_at' ],
            'supporting_docs'     => [ 'id', 'document_id', 'file_name', 'file_size', 'file_type', 'cloud_provider', 'cloud_key', 'cloud_url', 'label', 'sort_order', 'uploaded_by', 'created_at' ],
            'compliance_docs'     => [ 'id', 'file_name', 'file_size', 'file_type', 'cloud_provider', 'cloud_key', 'cloud_url', 'label', 'description', 'sort_order', 'uploaded_by', 'created_at' ],
            'threads'             => [ 'id', 'document_id', 'bidder_id', 'contact_id', 'subject', 'visibility', 'status', 'created_at', 'updated_at' ],
            'messages'            => [ 'id', 'thread_id', 'sender_id', 'message', 'is_read', 'created_at' ],
            'message_attachments' => [ 'id', 'message_id', 'file_name', 'file_size', 'file_type', 'cloud_provider', 'cloud_key', 'cloud_url', 'created_at' ],
            'downloads'           => [ 'id', 'document_id', 'supporting_doc_id', 'user_id', 'ip_address', 'user_agent', 'downloaded_at' ],
            'bidder_profiles'     => [ 'id', 'user_id', 'company_name', 'company_reg', 'phone', 'verified', 'verification_token', 'token_expires_at', 'created_at' ],
        ];

        return $columns[ $table ] ?? [ 'id' ];
    }
}
