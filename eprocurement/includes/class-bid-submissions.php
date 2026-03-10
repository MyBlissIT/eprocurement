<?php
/**
 * Bid Submissions handler.
 *
 * Manages sealed-bid document submissions with closing-date enforcement,
 * compulsory briefing attendance gating, late submission overrides,
 * and Super Admin timestamp backdating with visibility control.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Bid_Submissions {

    /**
     * Maximum file size for bid submissions (10 MB).
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Allowed file extensions for bid submissions.
     */
    private const ALLOWED_EXTENSIONS = [ 'pdf', 'xls', 'xlsx', 'csv' ];

    /**
     * Submit a bid document.
     *
     * Enforces all submission rules, uploads the file to cloud storage,
     * and records the submission. One active submission per bidder per bid.
     *
     * @param int   $document_id Bid/tender ID.
     * @param int   $user_id     Bidder user ID.
     * @param array $file        $_FILES array element.
     * @return int|\WP_Error Submission ID on success, WP_Error on failure.
     */
    public function submit( int $document_id, int $user_id, array $file ): int|\WP_Error {
        // Check eligibility.
        $can = $this->can_submit( $document_id, $user_id );
        if ( is_wp_error( $can ) ) {
            return $can;
        }

        // Check for existing active submission.
        $existing = $this->get_active_submission( $document_id, $user_id );
        if ( $existing ) {
            return new \WP_Error(
                'already_submitted',
                __( 'You already have an active submission for this bid. Cancel it first to resubmit.', 'eprocurement' ),
                [ 'status' => 400 ]
            );
        }

        // Validate file.
        $file_error = $this->validate_file( $file );
        if ( is_wp_error( $file_error ) ) {
            return $file_error;
        }

        // Get bid details for file naming.
        $bid = Eprocurement_Database::get_by_id( 'documents', $document_id );
        if ( ! $bid ) {
            return new \WP_Error( 'bid_not_found', __( 'Bid not found.', 'eprocurement' ), [ 'status' => 404 ] );
        }

        // Get bidder company name for file naming.
        $bidder  = new Eprocurement_Bidder();
        $profile = $bidder->get_profile( $user_id );
        $company = $profile && $profile->company_name
            ? sanitize_file_name( $profile->company_name )
            : 'bidder-' . $user_id;

        // Build file name: {company_name}_{timestamp}.{ext}
        $ext         = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $timestamp   = gmdate( 'Ymd-His' );
        $remote_name = "{$company}_{$timestamp}.{$ext}";

        // Build cloud folder: submissions/{bid_number} - {title}/
        $folder_name = sanitize_file_name( $bid->bid_number . ' - ' . $bid->title );
        $folder      = 'submissions/' . $folder_name;

        // Upload to cloud storage.
        $provider = Eprocurement_Storage_Interface::get_active_provider();
        if ( ! $provider ) {
            return new \WP_Error(
                'no_storage',
                __( 'No storage provider configured. Please contact the administrator.', 'eprocurement' ),
                [ 'status' => 500 ]
            );
        }

        try {
            $upload_result = $provider->upload( $file['tmp_name'], $remote_name, $folder );
        } catch ( \RuntimeException $e ) {
            return new \WP_Error(
                'upload_failed',
                __( 'File upload failed. Please try again.', 'eprocurement' ),
                [ 'status' => 500 ]
            );
        }

        // Determine if this is a late submission.
        $is_late = 0;
        if ( $bid->closing_date && strtotime( $bid->closing_date ) < time() ) {
            $is_late = 1;
        }

        $now = current_time( 'mysql', true );

        // Insert submission record.
        $submission_id = Eprocurement_Database::insert( 'bid_submissions', [
            'document_id'    => $document_id,
            'user_id'        => $user_id,
            'file_name'      => sanitize_file_name( $file['name'] ),
            'file_size'      => (int) $file['size'],
            'file_type'      => $ext,
            'cloud_provider' => $provider->get_provider_name(),
            'cloud_key'      => $upload_result['cloud_key'],
            'cloud_url'      => $upload_result['cloud_url'],
            'status'         => 'submitted',
            'is_late'        => $is_late,
            'submitted_at'   => $now,
            'created_at'     => $now,
        ] );

        if ( ! $submission_id ) {
            // Rollback: delete the uploaded file.
            try {
                $provider->delete( $upload_result['cloud_key'] );
            } catch ( \RuntimeException $e ) {
                // Best-effort cleanup.
            }
            return new \WP_Error(
                'db_error',
                __( 'Failed to record submission. Please try again.', 'eprocurement' ),
                [ 'status' => 500 ]
            );
        }

        /**
         * Fires after a bid submission is created.
         *
         * @param int  $submission_id The submission ID.
         * @param int  $document_id   The bid/tender ID.
         * @param int  $user_id       The bidder user ID.
         * @param bool $is_late       Whether this is a late submission.
         */
        do_action( 'eprocurement_bid_submitted', $submission_id, $document_id, $user_id, (bool) $is_late );

        return $submission_id;
    }

    /**
     * Cancel a submission.
     *
     * Marks the submission as cancelled, deletes the cloud file,
     * and fires a hook for notifications. Only allowed before closing date.
     *
     * @param int $submission_id Submission ID.
     * @param int $user_id       Bidder user ID.
     * @return true|\WP_Error True on success.
     */
    public function cancel( int $submission_id, int $user_id ): true|\WP_Error {
        $submission = Eprocurement_Database::get_by_id( 'bid_submissions', $submission_id );

        if ( ! $submission ) {
            return new \WP_Error( 'not_found', __( 'Submission not found.', 'eprocurement' ), [ 'status' => 404 ] );
        }

        if ( (int) $submission->user_id !== $user_id ) {
            return new \WP_Error( 'forbidden', __( 'You can only cancel your own submissions.', 'eprocurement' ), [ 'status' => 403 ] );
        }

        if ( $submission->status === 'cancelled' ) {
            return new \WP_Error( 'already_cancelled', __( 'This submission is already cancelled.', 'eprocurement' ), [ 'status' => 400 ] );
        }

        // Check if cancellation is still allowed (before closing date).
        $can_cancel = $this->can_cancel( (int) $submission->document_id );
        if ( is_wp_error( $can_cancel ) ) {
            return $can_cancel;
        }

        // Delete file from cloud storage.
        $provider = Eprocurement_Storage_Interface::get_active_provider();
        if ( $provider && $submission->cloud_key ) {
            try {
                $provider->delete( $submission->cloud_key );
            } catch ( \RuntimeException $e ) {
                // Log but don't block cancellation.
                error_log( 'eProcurement: Failed to delete submission file: ' . $e->getMessage() ); // phpcs:ignore
            }
        }

        // Mark as cancelled.
        Eprocurement_Database::update(
            'bid_submissions',
            [
                'status'       => 'cancelled',
                'cancelled_at' => current_time( 'mysql', true ),
            ],
            [ 'id' => $submission_id ]
        );

        /**
         * Fires after a bid submission is cancelled.
         *
         * @param int $submission_id The submission ID.
         * @param int $document_id   The bid/tender ID.
         * @param int $user_id       The bidder user ID.
         */
        do_action( 'eprocurement_bid_cancelled', $submission_id, (int) $submission->document_id, $user_id );

        return true;
    }

    /**
     * Check if a bidder is eligible to submit for a bid.
     *
     * Enforcement rules:
     * 1. Bid must be status = 'open'
     * 2. Bidder must be verified (email verified)
     * 3. Closing date must not have passed (unless allow_late_submissions is on)
     * 4. If briefing_compulsory, bidder email must be in briefing_attendees
     *
     * Does NOT check for existing submission or file validity.
     *
     * @param int $document_id Bid/tender ID.
     * @param int $user_id     Bidder user ID.
     * @return true|\WP_Error True if eligible, WP_Error with reason.
     */
    public function can_submit( int $document_id, int $user_id ): true|\WP_Error {
        $bid = Eprocurement_Database::get_by_id( 'documents', $document_id );

        if ( ! $bid ) {
            return new \WP_Error( 'bid_not_found', __( 'Bid not found.', 'eprocurement' ), [ 'status' => 404 ] );
        }

        // Rule 0: Online submissions must be enabled for this bid.
        if ( empty( $bid->accept_online_submissions ) ) {
            return new \WP_Error(
                'online_submissions_disabled',
                __( 'This bid does not accept online submissions.', 'eprocurement' ),
                [ 'status' => 400 ]
            );
        }

        // Rule 1: Bid must be open.
        if ( $bid->status !== 'open' ) {
            return new \WP_Error(
                'bid_not_open',
                __( 'This bid is not currently open for submissions.', 'eprocurement' ),
                [ 'status' => 400 ]
            );
        }

        // Rule 2: Bidder must be verified.
        $bidder = new Eprocurement_Bidder();
        if ( ! $bidder->is_verified( $user_id ) ) {
            return new \WP_Error(
                'not_verified',
                __( 'You must verify your email address before submitting.', 'eprocurement' ),
                [ 'status' => 403 ]
            );
        }

        // Rule 3: Closing date check.
        if ( $bid->closing_date && strtotime( $bid->closing_date ) < time() ) {
            $allow_late = ! empty( $bid->allow_late_submissions );
            if ( ! $allow_late ) {
                return new \WP_Error(
                    'deadline_passed',
                    __( 'The closing date for this bid has passed. Submissions are no longer accepted.', 'eprocurement' ),
                    [ 'status' => 400 ]
                );
            }
        }

        // Rule 4: Briefing compulsory check.
        if ( ! empty( $bid->briefing_compulsory ) ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                return new \WP_Error( 'user_not_found', __( 'User not found.', 'eprocurement' ), [ 'status' => 404 ] );
            }

            $attendee = $this->get_attendee_by_email( $document_id, $user->user_email );
            if ( ! $attendee ) {
                return new \WP_Error(
                    'briefing_required',
                    __( 'Submission is restricted to briefing session attendees. Contact the SCM office for assistance.', 'eprocurement' ),
                    [ 'status' => 403 ]
                );
            }
        }

        return true;
    }

    /**
     * Check if cancellation is allowed for a bid (before closing date).
     *
     * @param int $document_id Bid/tender ID.
     * @return true|\WP_Error True if cancellation is allowed.
     */
    public function can_cancel( int $document_id ): true|\WP_Error {
        $bid = Eprocurement_Database::get_by_id( 'documents', $document_id );

        if ( ! $bid ) {
            return new \WP_Error( 'bid_not_found', __( 'Bid not found.', 'eprocurement' ), [ 'status' => 404 ] );
        }

        if ( $bid->closing_date && strtotime( $bid->closing_date ) < time() ) {
            return new \WP_Error(
                'deadline_passed',
                __( 'Cancellation is not allowed after the closing date.', 'eprocurement' ),
                [ 'status' => 400 ]
            );
        }

        return true;
    }

    /**
     * Get a single submission by ID.
     *
     * @param int $submission_id Submission ID.
     * @return object|null Submission row or null.
     */
    public function get_submission( int $submission_id ): ?object {
        return Eprocurement_Database::get_by_id( 'bid_submissions', $submission_id );
    }

    /**
     * Get the active (non-cancelled) submission for a bidder on a specific bid.
     *
     * Only one active submission per bidder per bid is allowed.
     *
     * @param int $document_id Bid/tender ID.
     * @param int $user_id     Bidder user ID.
     * @return object|null Submission row or null.
     */
    public function get_active_submission( int $document_id, int $user_id ): ?object {
        global $wpdb;

        $table = Eprocurement_Database::table( 'bid_submissions' );

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE document_id = %d AND user_id = %d AND status = 'submitted' LIMIT 1", // phpcs:ignore
                $document_id,
                $user_id
            )
        );
    }

    /**
     * Get all active submissions for a bid (SCM Manager view).
     *
     * Joins bidder profile and user tables to include company name and email.
     * This is a sealed-bid view — only SCM staff should call this.
     *
     * @param int $document_id Bid/tender ID.
     * @return array Array of submission objects with bidder info.
     */
    public function get_submissions_for_document( int $document_id ): array {
        global $wpdb;

        $sub_table = Eprocurement_Database::table( 'bid_submissions' );
        $bp_table  = Eprocurement_Database::table( 'bidder_profiles' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, bp.company_name, u.user_email, u.display_name
                 FROM {$sub_table} s
                 LEFT JOIN {$bp_table} bp ON s.user_id = bp.user_id
                 LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
                 WHERE s.document_id = %d AND s.status = 'submitted'
                 ORDER BY s.submitted_at DESC", // phpcs:ignore
                $document_id
            )
        );
    }

    /**
     * Get all submissions for a bidder across all bids (bidder dashboard view).
     *
     * Joins documents table to include bid number, title, and status.
     *
     * @param int $user_id Bidder user ID.
     * @return array Array of submission objects with bid info.
     */
    public function get_submissions_for_user( int $user_id ): array {
        global $wpdb;

        $sub_table = Eprocurement_Database::table( 'bid_submissions' );
        $doc_table = Eprocurement_Database::table( 'documents' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, d.bid_number, d.title AS bid_title, d.status AS bid_status
                 FROM {$sub_table} s
                 LEFT JOIN {$doc_table} d ON s.document_id = d.id
                 WHERE s.user_id = %d
                 ORDER BY s.submitted_at DESC", // phpcs:ignore
                $user_id
            )
        );
    }

    /**
     * Count active (non-cancelled) submissions for a bid.
     *
     * @param int $document_id Bid/tender ID.
     * @return int Count of active submissions.
     */
    public function get_submission_count( int $document_id ): int {
        return Eprocurement_Database::count( 'bid_submissions', [
            'document_id' => $document_id,
            'status'      => 'submitted',
        ] );
    }

    /**
     * Get all bids that accept online submissions with their submission counts.
     *
     * Returns bid info joined with a count of active (non-cancelled) submissions
     * and the number of unique bidders who have submitted.
     *
     * @param array $args {
     *     Optional. Query arguments.
     *     @type string $status   Filter by bid status (open, closed, etc.).
     *     @type int    $per_page Items per page. Default 25.
     *     @type int    $page     Page number. Default 1.
     * }
     * @return array{items: array, total: int}
     */
    public function get_bids_with_submissions( array $args = [] ): array {
        global $wpdb;

        $doc_table = Eprocurement_Database::table( 'documents' );
        $sub_table = Eprocurement_Database::table( 'bid_submissions' );
        $per_page  = absint( $args['per_page'] ?? 25 );
        $page      = max( 1, absint( $args['page'] ?? 1 ) );
        $offset    = ( $page - 1 ) * $per_page;

        $where  = [ 'd.category = %s', 'd.accept_online_submissions = 1' ];
        $values = [ 'bid' ];

        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'd.status = %s';
            $values[] = sanitize_text_field( $args['status'] );
        }

        $where_sql = implode( ' AND ', $where );

        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$doc_table} d WHERE {$where_sql}";
        $total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // Fetch items with submission counts
        $sql = $wpdb->prepare(
            "SELECT d.id, d.bid_number, d.title, d.status, d.closing_date,
                    COUNT(s.id) AS submission_count,
                    COUNT(DISTINCT s.user_id) AS bidder_count
             FROM {$doc_table} d
             LEFT JOIN {$sub_table} s ON d.id = s.document_id AND s.status = 'submitted'
             WHERE {$where_sql}
             GROUP BY d.id
             ORDER BY d.closing_date DESC
             LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ...array_merge( $values, [ $per_page, $offset ] )
        );

        $items = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return [
            'items' => $items ?: [],
            'total' => $total,
        ];
    }

    /**
     * Backdate a submission timestamp (Super Admin only).
     *
     * Two modes:
     * - visible=true:  Stores admin ID in backdated_by, shows "Backdated" indicator to staff.
     * - visible=false: Sets backdated_by to NULL, hides the backdate from all users.
     *
     * In both cases, original_submitted_at is always preserved (stores the first
     * original timestamp, never overwritten on subsequent backdates).
     *
     * @param int    $submission_id Submission ID.
     * @param string $new_datetime  New datetime (Y-m-d H:i:s or Y-m-d\TH:i format).
     * @param int    $admin_id      Super Admin user ID performing the backdate.
     * @param bool   $visible       Whether to show the backdate indicator.
     * @return true|\WP_Error True on success.
     */
    public function backdate( int $submission_id, string $new_datetime, int $admin_id, bool $visible = true ): true|\WP_Error {
        if ( ! is_super_admin( $admin_id ) ) {
            return new \WP_Error(
                'forbidden',
                __( 'Only Super Admins can backdate submissions.', 'eprocurement' ),
                [ 'status' => 403 ]
            );
        }

        $submission = Eprocurement_Database::get_by_id( 'bid_submissions', $submission_id );
        if ( ! $submission ) {
            return new \WP_Error( 'not_found', __( 'Submission not found.', 'eprocurement' ), [ 'status' => 404 ] );
        }

        // Validate datetime format (accept both Y-m-d H:i:s and Y-m-d\TH:i).
        $parsed = \DateTime::createFromFormat( 'Y-m-d H:i:s', $new_datetime );
        if ( ! $parsed ) {
            $parsed = \DateTime::createFromFormat( 'Y-m-d\TH:i', $new_datetime );
        }
        if ( ! $parsed ) {
            return new \WP_Error( 'invalid_date', __( 'Invalid datetime format.', 'eprocurement' ), [ 'status' => 400 ] );
        }

        $formatted = $parsed->format( 'Y-m-d H:i:s' );

        // Preserve the first original timestamp (never overwrite on subsequent backdates).
        $original = $submission->original_submitted_at ?: $submission->submitted_at;

        // Use raw query to properly handle NULL for hidden backdates.
        global $wpdb;
        $table = Eprocurement_Database::table( 'bid_submissions' );

        if ( $visible ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET submitted_at = %s, original_submitted_at = %s, backdated_by = %d WHERE id = %d", // phpcs:ignore
                    $formatted,
                    $original,
                    $admin_id,
                    $submission_id
                )
            );
        } else {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET submitted_at = %s, original_submitted_at = %s, backdated_by = NULL WHERE id = %d", // phpcs:ignore
                    $formatted,
                    $original,
                    $submission_id
                )
            );
        }

        return true;
    }

    /**
     * Generate a ZIP archive of all active submissions for a bid.
     *
     * ZIP filename format: {Bid No} - {Title} - {Date}.zip
     * Internal files: {company_name}_{original_filename}
     *
     * @param int $document_id Bid/tender ID.
     * @return string|\WP_Error Path to the generated ZIP file, or WP_Error.
     */
    public function generate_submissions_zip( int $document_id ): string|\WP_Error {
        $bid = Eprocurement_Database::get_by_id( 'documents', $document_id );
        if ( ! $bid ) {
            return new \WP_Error( 'bid_not_found', __( 'Bid not found.', 'eprocurement' ), [ 'status' => 404 ] );
        }

        $submissions = $this->get_submissions_for_document( $document_id );
        if ( empty( $submissions ) ) {
            return new \WP_Error( 'no_submissions', __( 'No submissions to download.', 'eprocurement' ), [ 'status' => 404 ] );
        }

        // ZIP filename: {Bid No} - {Title} - {Date}.zip
        $zip_name = sanitize_file_name(
            $bid->bid_number . ' - ' . $bid->title . ' - ' . gmdate( 'Y-m-d' ) . '.zip'
        );
        $tmp_dir  = get_temp_dir();
        $zip_path = $tmp_dir . $zip_name;

        $zip = new \ZipArchive();
        if ( $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
            return new \WP_Error( 'zip_error', __( 'Failed to create ZIP archive.', 'eprocurement' ), [ 'status' => 500 ] );
        }

        $provider = Eprocurement_Storage_Interface::get_active_provider();
        if ( ! $provider ) {
            return new \WP_Error( 'no_storage', __( 'No storage provider configured.', 'eprocurement' ), [ 'status' => 500 ] );
        }

        $temp_files = [];

        foreach ( $submissions as $sub ) {
            // Build local filename: {company_name}_{original_filename}
            $company = $sub->company_name
                ? sanitize_file_name( $sub->company_name )
                : 'bidder-' . $sub->user_id;

            $local_name = $company . '_' . $sub->file_name;

            try {
                $download_url = $provider->get_download_url( $sub->cloud_key );
                $tmp_file     = download_url( $download_url, 60 );

                if ( is_wp_error( $tmp_file ) ) {
                    continue; // Skip files that can't be downloaded.
                }

                $temp_files[] = $tmp_file;
                $zip->addFile( $tmp_file, $local_name );
            } catch ( \RuntimeException $e ) {
                continue; // Skip on error, don't block entire ZIP.
            }
        }

        $zip->close();

        // Clean up temp downloaded files.
        foreach ( $temp_files as $tmp ) {
            @unlink( $tmp ); // phpcs:ignore
        }

        return $zip_path;
    }

    /**
     * Check if briefing attendance is compulsory for a bid.
     *
     * @param int $document_id Bid/tender ID.
     * @return bool True if compulsory.
     */
    public function is_briefing_compulsory( int $document_id ): bool {
        $bid = Eprocurement_Database::get_by_id( 'documents', $document_id );
        return $bid && ! empty( $bid->briefing_compulsory );
    }

    /**
     * Get a briefing attendee by email for a specific bid.
     *
     * @param int    $document_id Bid/tender ID.
     * @param string $email       Bidder email address.
     * @return object|null Attendee row or null.
     */
    public function get_attendee_by_email( int $document_id, string $email ): ?object {
        global $wpdb;

        $table = Eprocurement_Database::table( 'briefing_attendees' );

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE document_id = %d AND bidder_email = %s LIMIT 1", // phpcs:ignore
                $document_id,
                $email
            )
        );
    }

    /**
     * Get a briefing attendee by their invite token.
     *
     * @param string $token Unique attendee token (UUID).
     * @return object|null Attendee row or null.
     */
    public function get_attendee_by_token( string $token ): ?object {
        global $wpdb;

        $table = Eprocurement_Database::table( 'briefing_attendees' );

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE token = %s LIMIT 1", // phpcs:ignore
                $token
            )
        );
    }

    /**
     * Add a briefing attendee to a bid's allowlist.
     *
     * Generates a UUID token for the invite link.
     *
     * @param int      $document_id  Bid/tender ID.
     * @param string   $email        Bidder email.
     * @param string   $company_name Company name.
     * @param int|null $user_id      Optional WP user ID if known.
     * @return int|\WP_Error Attendee ID or WP_Error.
     */
    public function add_attendee( int $document_id, string $email, string $company_name, ?int $user_id = null ): int|\WP_Error {
        // Check if already added (unique constraint: document_id + bidder_email).
        $existing = $this->get_attendee_by_email( $document_id, $email );
        if ( $existing ) {
            return new \WP_Error(
                'already_added',
                __( 'This email is already in the attendee list.', 'eprocurement' ),
                [ 'status' => 400 ]
            );
        }

        // Generate a unique token for the invite link.
        $token = wp_generate_uuid4();

        $attendee_id = Eprocurement_Database::insert( 'briefing_attendees', [
            'document_id'  => $document_id,
            'user_id'      => $user_id,
            'bidder_email' => sanitize_email( $email ),
            'company_name' => sanitize_text_field( $company_name ),
            'token'        => $token,
            'invited_at'   => current_time( 'mysql', true ),
        ] );

        if ( ! $attendee_id ) {
            return new \WP_Error( 'db_error', __( 'Failed to add attendee.', 'eprocurement' ), [ 'status' => 500 ] );
        }

        return $attendee_id;
    }

    /**
     * Remove a briefing attendee.
     *
     * @param int $attendee_id Attendee ID.
     * @return true|\WP_Error True on success.
     */
    public function remove_attendee( int $attendee_id ): true|\WP_Error {
        $result = Eprocurement_Database::delete( 'briefing_attendees', [ 'id' => $attendee_id ] );

        if ( $result === false ) {
            return new \WP_Error( 'db_error', __( 'Failed to remove attendee.', 'eprocurement' ), [ 'status' => 500 ] );
        }

        return true;
    }

    /**
     * Get all attendees for a bid.
     *
     * @param int $document_id Bid/tender ID.
     * @return array Array of attendee objects.
     */
    public function get_attendees( int $document_id ): array {
        return Eprocurement_Database::get_rows(
            'briefing_attendees',
            [ 'document_id' => $document_id ],
            'invited_at',
            'ASC'
        );
    }

    /**
     * Mark a briefing invite token as used (records the timestamp).
     *
     * @param int $attendee_id Attendee ID.
     * @return void
     */
    public function mark_token_used( int $attendee_id ): void {
        Eprocurement_Database::update(
            'briefing_attendees',
            [ 'used_at' => current_time( 'mysql', true ) ],
            [ 'id' => $attendee_id ]
        );
    }

    /**
     * Validate an uploaded file for bid submission.
     *
     * Checks: upload error, file size (max 10 MB), extension (PDF/XLS/XLSX/CSV),
     * and MIME type verification.
     *
     * @param array $file $_FILES array element.
     * @return true|\WP_Error True if valid, WP_Error if not.
     */
    private function validate_file( array $file ): true|\WP_Error {
        // Check for upload errors.
        if ( ! isset( $file['error'] ) || $file['error'] !== UPLOAD_ERR_OK ) {
            return new \WP_Error(
                'upload_error',
                __( 'File upload error. Please try again.', 'eprocurement' ),
                [ 'status' => 400 ]
            );
        }

        // Check file size.
        if ( $file['size'] > self::MAX_FILE_SIZE ) {
            return new \WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %d: maximum file size in megabytes */
                    __( 'File size exceeds the maximum of %d MB.', 'eprocurement' ),
                    self::MAX_FILE_SIZE / ( 1024 * 1024 )
                ),
                [ 'status' => 400 ]
            );
        }

        if ( $file['size'] === 0 ) {
            return new \WP_Error(
                'empty_file',
                __( 'The uploaded file is empty.', 'eprocurement' ),
                [ 'status' => 400 ]
            );
        }

        // Check extension.
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, self::ALLOWED_EXTENSIONS, true ) ) {
            return new \WP_Error(
                'invalid_type',
                sprintf(
                    /* translators: %s: comma-separated list of allowed file extensions */
                    __( 'Invalid file type. Allowed types: %s', 'eprocurement' ),
                    implode( ', ', array_map( 'strtoupper', self::ALLOWED_EXTENSIONS ) )
                ),
                [ 'status' => 400 ]
            );
        }

        // Verify MIME type via WordPress.
        $wp_check = wp_check_filetype( $file['name'], [
            'pdf'  => 'application/pdf',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv'  => 'text/csv',
        ] );

        if ( ! $wp_check['type'] ) {
            return new \WP_Error(
                'invalid_mime',
                __( 'File type verification failed.', 'eprocurement' ),
                [ 'status' => 400 ]
            );
        }

        return true;
    }
}
