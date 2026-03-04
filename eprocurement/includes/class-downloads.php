<?php
/**
 * Download audit log and secure download endpoint.
 *
 * Logs every file download (including guest downloads),
 * and serves files through a secure endpoint that generates
 * time-limited cloud download URLs.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Downloads {

    /**
     * Hook into WordPress actions.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_download_endpoint' ] );
        add_action( 'template_redirect', [ $this, 'handle_download_request' ] );
    }

    /**
     * Register the download rewrite endpoint.
     */
    public function register_download_endpoint(): void {
        add_rewrite_rule(
            '^eproc-download/?$',
            'index.php?eproc_download=1',
            'top'
        );
        add_rewrite_tag( '%eproc_download%', '([^&]+)' );
    }

    /**
     * Handle download requests.
     */
    public function handle_download_request(): void {
        if ( ! get_query_var( 'eproc_download' ) ) {
            return;
        }

        // Verify nonce
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'eproc_download' ) ) {
            wp_die( esc_html__( 'Invalid download link.', 'eprocurement' ), 403 );
        }

        $type = sanitize_text_field( $_GET['type'] ?? 'supporting' );
        $id   = absint( $_GET['id'] ?? 0 );

        if ( ! $id ) {
            wp_die( esc_html__( 'Invalid download request.', 'eprocurement' ), 400 );
        }

        $file_record = null;
        $document_id = 0;

        if ( $type === 'supporting' ) {
            $file_record = Eprocurement_Database::get_by_id( 'supporting_docs', $id );
            if ( $file_record ) {
                $document_id = (int) $file_record->document_id;
            }
        } elseif ( $type === 'compliance' ) {
            $file_record = Eprocurement_Database::get_by_id( 'compliance_docs', $id );
        } elseif ( $type === 'attachment' ) {
            $file_record = Eprocurement_Database::get_by_id( 'message_attachments', $id );
            // Attachment downloads require login
            if ( ! is_user_logged_in() ) {
                wp_die( esc_html__( 'You must be logged in to download attachments.', 'eprocurement' ), 403 );
            }
        }

        if ( ! $file_record ) {
            wp_die( esc_html__( 'File not found.', 'eprocurement' ), 404 );
        }

        // Log the download
        $this->log_download( $document_id, $id, $type );

        // Get download URL from cloud provider
        try {
            $storage = Eprocurement_Storage_Interface::get_active_provider();
            if ( ! $storage ) {
                wp_die( esc_html__( 'Cloud storage not configured.', 'eprocurement' ), 500 );
            }

            $download_url = $storage->get_download_url( $file_record->cloud_key );

            // Redirect to the time-limited download URL
            wp_redirect( $download_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
            exit;
        } catch ( \Exception $e ) {
            wp_die( esc_html( $e->getMessage() ), 500 );
        }
    }

    /**
     * Log a file download event.
     *
     * @param int    $document_id     Document ID (0 for SCM docs).
     * @param int    $supporting_doc_id Bid doc ID.
     * @param string $type            Type of download.
     */
    public function log_download( int $document_id, int $supporting_doc_id, string $type = 'supporting' ): void {
        Eprocurement_Database::insert( 'downloads', [
            'document_id'      => $document_id,
            'supporting_doc_id' => $type === 'supporting' ? $supporting_doc_id : null,
            'user_id'          => is_user_logged_in() ? get_current_user_id() : null,
            'ip_address'       => $this->get_client_ip(),
            'user_agent'       => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
            'downloaded_at'    => current_time( 'mysql' ),
        ] );
    }

    /**
     * Generate a secure download URL.
     *
     * @param int    $file_id File ID (supporting_doc, compliance_doc, or attachment).
     * @param string $type    Type: 'supporting', 'compliance', or 'attachment'.
     * @return string Secure download URL with nonce.
     */
    public static function get_download_link( int $file_id, string $type = 'supporting' ): string {
        return wp_nonce_url(
            add_query_arg( [
                'eproc_download' => 1,
                'type'           => $type,
                'id'             => $file_id,
            ], home_url( '/eproc-download/' ) ),
            'eproc_download'
        );
    }

    /**
     * Get download log for a document.
     *
     * @param int   $document_id Document ID.
     * @param array $args        Pagination args.
     * @return array{items: array, total: int}
     */
    public function get_log( int $document_id = 0, array $args = [] ): array {
        global $wpdb;

        $table      = Eprocurement_Database::table( 'downloads' );
        $sup_table  = Eprocurement_Database::table( 'supporting_docs' );
        $where      = [];
        $values     = [];
        $limit      = absint( $args['per_page'] ?? 50 );
        $page       = max( 1, absint( $args['page'] ?? 1 ) );
        $offset     = ( $page - 1 ) * $limit;
        $date_from  = $args['date_from'] ?? '';
        $date_to    = $args['date_to'] ?? '';
        $search     = $args['search'] ?? '';

        if ( $document_id > 0 ) {
            $where[]  = 'd.document_id = %d';
            $values[] = $document_id;
        }

        if ( $date_from ) {
            $where[]  = 'd.downloaded_at >= %s';
            $values[] = $date_from . ' 00:00:00';
        }

        if ( $date_to ) {
            $where[]  = 'd.downloaded_at <= %s';
            $values[] = $date_to . ' 23:59:59';
        }

        // Join supporting_docs for search by file name
        $join_sup = '';
        if ( $search ) {
            $join_sup = "LEFT JOIN {$sup_table} sd ON d.supporting_doc_id = sd.id";
            $like      = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]   = '(u.display_name LIKE %s OR sd.file_name LIKE %s OR d.ip_address LIKE %s)';
            $values[]  = $like;
            $values[]  = $like;
            $values[]  = $like;
        }

        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $count_sql = "SELECT COUNT(*) FROM {$table} d LEFT JOIN {$wpdb->users} u ON d.user_id = u.ID {$join_sup} {$where_sql}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! empty( $values ) ) {
            $count_sql = $wpdb->prepare( $count_sql, ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        $total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $query_values   = $values;
        $query_values[] = $limit;
        $query_values[] = $offset;

        $sql = "SELECT d.*, u.display_name, u.user_email
                FROM {$table} d
                LEFT JOIN {$wpdb->users} u ON d.user_id = u.ID
                {$join_sup}
                {$where_sql}
                ORDER BY d.downloaded_at DESC
                LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ( ! empty( $query_values ) ) {
            $sql = $wpdb->prepare( $sql, ...$query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        return [
            'items' => $wpdb->get_results( $sql ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'total' => $total,
        ];
    }

    /**
     * Get download count for today.
     *
     * @return int
     */
    public static function get_downloads_today(): int {
        global $wpdb;
        $table       = Eprocurement_Database::table( 'downloads' );
        $today_start = current_time( 'Y-m-d' ) . ' 00:00:00';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE downloaded_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $today_start
            )
        );
    }

    /**
     * Get the most downloaded open document.
     *
     * @return object|null Object with ->title and ->dl_count, or null.
     */
    public static function get_most_downloaded_document(): ?object {
        global $wpdb;
        $downloads_table = Eprocurement_Database::table( 'downloads' );
        $docs_table      = Eprocurement_Database::table( 'documents' );

        return $wpdb->get_row(
            "SELECT doc.title, COUNT(dl.id) as dl_count
             FROM {$downloads_table} dl
             INNER JOIN {$docs_table} doc ON dl.document_id = doc.id
             WHERE doc.status = 'open' AND dl.document_id > 0
             GROUP BY dl.document_id
             ORDER BY dl_count DESC
             LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    /**
     * Export download log to CSV.
     *
     * @param int $document_id Optional document ID filter.
     */
    public function export_csv( int $document_id = 0 ): void {
        $data = $this->get_log( $document_id, [ 'per_page' => 10000 ] );

        $filename = 'download-log';
        if ( $document_id > 0 ) {
            $doc = Eprocurement_Database::get_by_id( 'documents', $document_id );
            if ( $doc ) {
                $filename .= '-' . sanitize_file_name( $doc->bid_number );
            }
        }
        $filename .= '-' . gmdate( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // CSV header
        fputcsv( $output, [
            'Date/Time',
            'User',
            'Email',
            'Company',
            'Document',
            'File',
            'IP Address',
            'User Agent',
        ] );

        foreach ( $data['items'] as $row ) {
            $user_name = $row->display_name ?: 'Guest';
            $user_email = $row->user_email ?: 'N/A';
            $company = '';

            if ( $row->user_id ) {
                $bidder  = new Eprocurement_Bidder();
                $profile = $bidder->get_profile( (int) $row->user_id );
                $company = $profile->company_name ?? '';
            }

            fputcsv( $output, [
                $row->downloaded_at,
                $user_name,
                $user_email,
                $company,
                $row->document_id,
                $row->supporting_doc_id ?? 'N/A',
                $row->ip_address,
                $row->user_agent,
            ] );
        }

        fclose( $output );
        exit;
    }

    /**
     * Get the client's IP address.
     */
    private function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                // Take first IP if comma-separated
                if ( str_contains( $ip, ',' ) ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
