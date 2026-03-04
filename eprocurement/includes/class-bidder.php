<?php
/**
 * Bidder Registration & Email Verification.
 *
 * Handles the frontend registration flow for bidders:
 * - Account creation with company profile
 * - Email verification token
 * - Verification endpoint
 * - Profile management
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Bidder {

    /**
     * Token expiry in seconds (48 hours).
     */
    private const TOKEN_EXPIRY = 172800;

    /**
     * Hook into WordPress actions.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'handle_verification' ] );
    }

    /**
     * Register a new bidder account.
     *
     * @param array $data Registration data.
     * @return int|\WP_Error User ID on success, WP_Error on failure.
     */
    public function register( array $data ): int|\WP_Error {
        $email      = sanitize_email( $data['email'] ?? '' );
        $first_name = sanitize_text_field( $data['first_name'] ?? '' );
        $last_name  = sanitize_text_field( $data['last_name'] ?? '' );
        $password   = $data['password'] ?? '';
        $company    = sanitize_text_field( $data['company_name'] ?? '' );
        $reg_no     = sanitize_text_field( $data['company_reg'] ?? '' );
        $phone      = sanitize_text_field( $data['phone'] ?? '' );

        // Validate required fields
        if ( ! $email || ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'eprocurement' ) );
        }

        if ( ! $first_name || ! $last_name ) {
            return new \WP_Error( 'missing_name', __( 'First name and last name are required.', 'eprocurement' ) );
        }

        if ( strlen( $password ) < 8 ) {
            return new \WP_Error( 'weak_password', __( 'Password must be at least 8 characters.', 'eprocurement' ) );
        }

        if ( ! $company ) {
            return new \WP_Error( 'missing_company', __( 'Company name is required.', 'eprocurement' ) );
        }

        if ( email_exists( $email ) ) {
            return new \WP_Error( 'email_exists', __( 'An account with this email already exists.', 'eprocurement' ) );
        }

        // Create WordPress user
        $username = sanitize_user( strtolower( $first_name . '.' . $last_name ), true );

        // Ensure unique username
        $original_username = $username;
        $counter = 1;
        while ( username_exists( $username ) ) {
            $username = $original_username . $counter;
            $counter++;
        }

        $user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_pass'    => $password,
            'user_email'   => $email,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
            'role'         => 'eprocurement_subscriber',
        ] );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        // Generate verification token
        $token     = wp_generate_password( 64, false );
        $expires   = gmdate( 'Y-m-d H:i:s', time() + self::TOKEN_EXPIRY );

        // Create bidder profile
        Eprocurement_Database::insert( 'bidder_profiles', [
            'user_id'            => $user_id,
            'company_name'       => $company,
            'company_reg'        => $reg_no ?: null,
            'phone'              => $phone,
            'verified'           => 0,
            'verification_token' => wp_hash( $token ),
            'token_expires_at'   => $expires,
            'created_at'         => current_time( 'mysql' ),
        ] );

        // Send verification email
        $this->send_verification_email( $user_id, $email, $token );

        return $user_id;
    }

    /**
     * Send the email verification email.
     */
    private function send_verification_email( int $user_id, string $email, string $token ): void {
        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        $url  = home_url( "/{$slug}/verify/?token=" . urlencode( $token ) . '&uid=' . $user_id );

        $user    = get_userdata( $user_id );
        $name    = $user ? $user->display_name : '';
        $subject = __( 'Verify your eProcurement account', 'eprocurement' );

        $message = sprintf(
            /* translators: 1: User display name, 2: Verification URL, 3: Expiry hours */
            __(
                "Hello %1\$s,\n\n" .
                "Thank you for registering on the eProcurement Portal.\n\n" .
                "Please click the link below to verify your email address:\n" .
                "%2\$s\n\n" .
                "This link will expire in %3\$d hours.\n\n" .
                "If you did not register, please ignore this email.\n\n" .
                "Regards,\neProcurement System",
                'eprocurement'
            ),
            $name,
            $url,
            self::TOKEN_EXPIRY / 3600
        );

        wp_mail( $email, $subject, $message );
    }

    /**
     * Handle verification link callback.
     *
     * Triggers when the URL contains both `token` and `uid` parameters
     * on the verification page (e.g. /tenders/verify/?token=xxx&uid=123).
     */
    public function handle_verification(): void {
        $token   = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
        $user_id = absint( $_GET['uid'] ?? 0 );

        // Both parameters must be present.
        if ( ! $token || ! $user_id ) {
            return;
        }

        // Only fire on the frontend verification page path.
        $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        $uri  = trim( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), '/' );
        if ( ! str_contains( $uri, $slug . '/verify' ) ) {
            return;
        }

        $result = $this->verify_token( $user_id, $token );

        $slug     = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
        $redirect = home_url( "/{$slug}/login/" );

        if ( $result === true ) {
            $redirect = add_query_arg( 'verified', '1', $redirect );
        } else {
            $redirect = add_query_arg( 'verification_error', '1', $redirect );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Verify a token for a user.
     *
     * @param int    $user_id User ID.
     * @param string $token   Plain token from email.
     * @return bool True if verified.
     */
    public function verify_token( int $user_id, string $token ): bool {
        $profile = $this->get_profile( $user_id );

        if ( ! $profile || (int) $profile->verified === 1 ) {
            return false;
        }

        // Check token — stored via wp_hash() (HMAC-based), compare directly.
        if ( ! hash_equals( wp_hash( $token ), $profile->verification_token ) ) {
            return false;
        }

        // Check expiry
        if ( $profile->token_expires_at && strtotime( $profile->token_expires_at ) < time() ) {
            return false;
        }

        // Mark as verified
        Eprocurement_Database::update(
            'bidder_profiles',
            [
                'verified'           => 1,
                'verification_token' => null,
                'token_expires_at'   => null,
            ],
            [ 'user_id' => $user_id ]
        );

        return true;
    }

    /**
     * Resend the verification email.
     *
     * @return true|\WP_Error
     */
    public function resend_verification( int $user_id ): true|\WP_Error {
        $profile = $this->get_profile( $user_id );
        if ( ! $profile ) {
            return new \WP_Error( 'no_profile', __( 'Bidder profile not found.', 'eprocurement' ) );
        }
        if ( (int) $profile->verified === 1 ) {
            return new \WP_Error( 'already_verified', __( 'This bidder is already verified.', 'eprocurement' ) );
        }

        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return new \WP_Error( 'no_user', __( 'User not found.', 'eprocurement' ) );
        }

        $token   = wp_generate_password( 64, false );
        $expires = gmdate( 'Y-m-d H:i:s', time() + self::TOKEN_EXPIRY );

        Eprocurement_Database::update(
            'bidder_profiles',
            [
                'verification_token' => wp_hash( $token ),
                'token_expires_at'   => $expires,
            ],
            [ 'user_id' => $user_id ]
        );

        $this->send_verification_email( $user_id, $user->user_email, $token );

        return true;
    }

    /**
     * Get a bidder profile by user ID.
     */
    public function get_profile( int $user_id ): ?object {
        global $wpdb;

        $table = Eprocurement_Database::table( 'bidder_profiles' );

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    /**
     * Update a bidder's profile.
     */
    public function update_profile( int $user_id, array $data ): int|false {
        $update = [];

        if ( isset( $data['company_name'] ) ) {
            $update['company_name'] = sanitize_text_field( $data['company_name'] );
        }
        if ( isset( $data['company_reg'] ) ) {
            $update['company_reg'] = sanitize_text_field( $data['company_reg'] );
        }
        if ( isset( $data['phone'] ) ) {
            $update['phone'] = sanitize_text_field( $data['phone'] );
        }
        if ( isset( $data['notify_replies'] ) ) {
            $update['notify_replies'] = absint( $data['notify_replies'] ) ? 1 : 0;
        }

        if ( empty( $update ) ) {
            return 0;
        }

        return Eprocurement_Database::update( 'bidder_profiles', $update, [ 'user_id' => $user_id ] );
    }

    /**
     * Check if a bidder's email is verified.
     */
    public function is_verified( int $user_id ): bool {
        $profile = $this->get_profile( $user_id );
        return $profile && (int) $profile->verified === 1;
    }

    /**
     * Get all bidder profiles with user data for admin view.
     *
     * @param array $args Pagination and filter args.
     * @return array{items: array, total: int}
     */
    public function get_all_bidders( array $args = [] ): array {
        global $wpdb;

        $profiles_table = Eprocurement_Database::table( 'bidder_profiles' );
        $limit  = absint( $args['per_page'] ?? 20 );
        $page   = max( 1, absint( $args['page'] ?? 1 ) );
        $offset = ( $page - 1 ) * $limit;

        $where  = [];
        $values = [];

        if ( isset( $args['verified'] ) ) {
            $where[]  = 'bp.verified = %d';
            $values[] = absint( $args['verified'] );
        }

        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $count_sql = "SELECT COUNT(*) FROM {$profiles_table} bp {$where_sql}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( ! empty( $values ) ) {
            $count_sql = $wpdb->prepare( $count_sql, ...$values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        $total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $query_values   = $values;
        $query_values[] = $limit;
        $query_values[] = $offset;

        $sql = "SELECT bp.*, u.user_email, u.display_name, u.user_registered
                FROM {$profiles_table} bp
                JOIN {$wpdb->users} u ON bp.user_id = u.ID
                {$where_sql}
                ORDER BY bp.created_at DESC
                LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if ( ! empty( $query_values ) ) {
            $sql = $wpdb->prepare( $sql, ...$query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        return [
            'items' => $wpdb->get_results( $sql ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            'total' => $total,
        ];
    }
}
