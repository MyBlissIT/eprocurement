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

        $type = sanitize_text_field( $_GET['type'] ?? 'supporting' );
        if ( ! in_array( $type, [ 'supporting', 'compliance', 'attachment', 'submission' ], true ) ) {
            wp_die( esc_html__( 'Invalid download type.', 'eprocurement' ), 400 );
        }
        $id   = absint( $_GET['id'] ?? 0 );

        if ( ! $id ) {
            wp_die( esc_html__( 'Invalid download request.', 'eprocurement' ), 400 );
        }

        // Per-file nonce verification (security fix H-04).
        // The nonce is bound to both the file type and ID, so a valid nonce
        // for one file cannot be reused to download a different file.
        $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, "eproc_download_{$type}_{$id}" ) ) {
            wp_die( esc_html__( 'Invalid or expired download link.', 'eprocurement' ), 403 );
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
            // Attachment downloads require login.
            if ( ! is_user_logged_in() ) {
                wp_die( esc_html__( 'You must be logged in to download attachments.', 'eprocurement' ), 403 );
            }

            $file_record = Eprocurement_Database::get_by_id( 'message_attachments', $id );

            // IDOR protection (security fix C-03): verify the current user is
            // a participant in the thread that owns this attachment. Without
            // this check, any logged-in user could enumerate attachment IDs
            // and download private bidder correspondence.
            if ( $file_record ) {
                if ( ! $this->user_can_access_attachment( $file_record, get_current_user_id() ) ) {
                    wp_die( esc_html__( 'You do not have permission to download this attachment.', 'eprocurement' ), 403 );
                }
            }
        } elseif ( $type === 'submission' ) {
            // Audit fix A28: sealed-bid submission downloads.
            // Require staff capability — submissions are sealed until the
            // tender closes, and even after closing they're only visible
            // to staff with publish capability.
            if ( ! current_user_can( 'eproc_publish_bids' ) ) {
                wp_die( esc_html__( 'You do not have permission to download bid submissions.', 'eprocurement' ), 403 );
            }

            $file_record = Eprocurement_Database::get_by_id( 'bid_submissions', $id );
            if ( $file_record ) {
                $document_id = (int) $file_record->document_id;
            }
        }

        if ( ! $file_record ) {
            wp_die( esc_html__( 'File not found.', 'eprocurement' ), 404 );
        }

        // Log the download
        $this->log_download( $document_id, $id, $type );

        // Serve the file. Audit fix A28: ALL providers now stream server-side
        // via stream_file() rather than redirecting to a cloud URL. This
        // eliminates the public sharing links that Google Drive and OneDrive
        // previously created (which leaked to anyone with the URL).
        try {
            $storage = Eprocurement_Storage_Interface::get_active_provider();
            if ( ! $storage ) {
                wp_die( esc_html__( 'Cloud storage not configured.', 'eprocurement' ), 500 );
            }

            $filename  = $file_record->file_name ?? 'download';
            $mime_type = $file_record->file_type ?? 'application/octet-stream';

            // For local storage, stream_file() reads from the filesystem
            // (the realpath containment check happens inside stream_file()
            // for local storage, or via the base class default for cloud).
            // For cloud providers, stream_file() downloads server-side and
            // streams to the browser — the cloud URL is never exposed.
            $storage->stream_file( $file_record->cloud_key, $filename, $mime_type );
            // stream_file() calls exit; if we reach here, something went wrong.
            wp_die( esc_html__( 'Failed to stream file.', 'eprocurement' ), 500 );
        } catch ( \Exception $e ) {
            wp_die( esc_html__( 'An error occurred while serving the file.', 'eprocurement' ), 500 );
        }
    }

    /**
     * Verify that a user is allowed to download a message attachment.
     *
     * A user is allowed if:
     *   - they are a staff member (SCM/Unit Manager/Admin/Editor), OR
     *   - they are the bidder who owns the thread containing the attachment.
     *
     * @since 2.13.1  Security fix C-03 — IDOR prevention on attachments.
     * @param object $file_record The message_attachments row.
     * @param int    $user_id     The current user ID.
     * @return bool True if access is permitted.
     */
    private function user_can_access_attachment( object $file_record, int $user_id ): bool {
        if ( ! isset( $file_record->message_id ) ) {
            return false;
        }

        // Staff can access all attachments.
        if ( Eprocurement_Roles::is_staff( $user_id ) ) {
            return true;
        }

        // Resolve message → thread → bidder_id, and confirm ownership.
        $message = Eprocurement_Database::get_by_id( 'messages', (int) $file_record->message_id );
        if ( ! $message ) {
            return false;
        }

        $thread = Eprocurement_Database::get_by_id( 'threads', (int) $message->thread_id );
        if ( ! $thread ) {
            return false;
        }

        return (int) $thread->bidder_id === $user_id;
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
     * Generate a secure download URL bound to the specific file.
     *
     * The nonce is per-file (type + id) so that a valid link for one file
     * cannot be reused to download a different file (security fix H-04).
     *
     * @param int    $file_id File ID (supporting_doc, compliance_doc, or attachment).
     * @param string $type    Type: 'supporting', 'compliance', or 'attachment'.
     * @return string Secure download URL with per-file nonce.
     */
    public static function get_download_link( int $file_id, string $type = 'supporting' ): string {
        return wp_nonce_url(
            add_query_arg( [
                'eproc_download' => 1,
                'type'           => $type,
                'id'             => $file_id,
            ], home_url( '/eproc-download/' ) ),
            "eproc_download_{$type}_{$file_id}"
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
     * Get the most downloaded documents.
     *
     * @param int $limit Number of results to return.
     * @return array Array of objects with ->title and ->dl_count.
     */
    public static function get_most_downloaded_documents( int $limit = 4 ): array {
        global $wpdb;
        $downloads_table = Eprocurement_Database::table( 'downloads' );
        $docs_table      = Eprocurement_Database::table( 'documents' );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT doc.title, COUNT(dl.id) as dl_count
             FROM {$downloads_table} dl
             INNER JOIN {$docs_table} doc ON dl.document_id = doc.id
             WHERE dl.document_id > 0
             GROUP BY dl.document_id
             ORDER BY dl_count DESC
             LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $limit
        ) );
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

            // Audit fix A25: prevent CSV formula injection.
            // Excel/LibreOffice interpret leading =, +, -, @, \t, \r as
            // formula triggers. Prefix with a single quote to neutralize.
            $row_data = [
                $row->downloaded_at,
                $user_name,
                $user_email,
                $company,
                $row->document_id,
                $row->supporting_doc_id ?? 'N/A',
                $row->ip_address,
                $row->user_agent,
            ];
            $row_data = array_map( [ $this, 'csv_inject_defend' ], $row_data );

            fputcsv( $output, $row_data );
        }

        fclose( $output );
        exit;
    }

    /**
     * Defend a CSV cell against formula injection.
     *
     * If the value starts with a character that Excel/LibreOffice would
     * interpret as a formula trigger (=, +, -, @, \t, \r), prefix it with
     * a single quote to neutralize the trigger. The quote is displayed as
     * a leading character in the cell but is not interpreted as a formula.
     *
     * @since 2.17.0  Audit fix A25.
     * @param string $value Cell value.
     * @return string Safe cell value.
     */
    private function csv_inject_defend( $value ): string {
        if ( ! is_string( $value ) || $value === '' ) {
            return (string) $value;
        }
        $first = $value[0];
        if ( in_array( $first, [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
            return "'" . $value;
        }
        return $value;
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
