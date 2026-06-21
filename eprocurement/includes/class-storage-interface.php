<?php
/**
 * Abstract storage interface for cloud file operations.
 *
 * All cloud storage providers (Google Drive, OneDrive, Dropbox, S3)
 * must implement this interface. The plugin uses whichever provider
 * the admin has configured in Settings.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Eprocurement_Storage_Interface {

    /**
     * Upload a file to cloud storage.
     *
     * @param string $local_path  Temporary local file path.
     * @param string $remote_name Desired filename in cloud storage.
     * @param string $folder      Subfolder/path in cloud storage.
     * @return array{cloud_key: string, cloud_url: string} File identifier and URL.
     * @throws \RuntimeException On upload failure.
     */
    abstract public function upload( string $local_path, string $remote_name, string $folder = '' ): array;

    /**
     * Generate a time-limited download URL for a cloud-stored file.
     *
     * @param string $cloud_key  The file identifier/key in cloud storage.
     * @param int    $expires_in Seconds until the URL expires (default: 3600 = 1 hour).
     * @return string Signed/temporary download URL.
     * @throws \RuntimeException On failure to generate URL.
     */
    abstract public function get_download_url( string $cloud_key, int $expires_in = 3600 ): string;

    /**
     * Delete a file from cloud storage.
     *
     * @param string $cloud_key The file identifier/key to delete.
     * @return bool True on success.
     * @throws \RuntimeException On deletion failure.
     */
    abstract public function delete( string $cloud_key ): bool;

    /**
     * Test the connection to the cloud storage provider.
     *
     * @return bool True if connection is successful.
     */
    abstract public function test_connection(): bool;

    /**
     * Get the provider identifier string.
     *
     * @return string Provider name (e.g., 'google_drive', 'onedrive', 'dropbox', 's3').
     */
    abstract public function get_provider_name(): string;

    /**
     * Factory: get the active storage provider instance.
     *
     * The resolved instance is memoised for the request, and the
     * test_connection() check is cached in a 5-minute transient so we
     * don't issue a network round-trip on every file operation
     * (performance fix M-11).
     *
     * @return self|null Provider instance or null if none configured.
     */
    public static function get_active_provider(): ?self {
        // Memoise for the current request.
        static $cached = null;
        if ( $cached !== null ) {
            return $cached;
        }

        $provider = get_option( 'eprocurement_cloud_provider', '' );

        $instance = match ( $provider ) {
            'google_drive' => new Eprocurement_Google_Drive(),
            'onedrive'     => new Eprocurement_Onedrive(),
            'dropbox'      => new Eprocurement_Dropbox(),
            's3'           => new Eprocurement_S3(),
            default        => null,
        };

        // If a cloud provider is configured, verify it can connect — but
        // only once per 5 minutes (transient-cached). Falls back to local
        // storage if the connection test fails.
        if ( $instance && $provider !== '' ) {
            $cache_key = 'eproc_storage_ok_' . $provider;

            if ( ! get_transient( $cache_key ) ) {
                try {
                    if ( $instance->test_connection() ) {
                        set_transient( $cache_key, 1, 5 * MINUTE_IN_SECONDS );
                    } else {
                        $instance = null;
                    }
                } catch ( \Exception $e ) {
                    $instance = null;
                }
            }
        }

        $cached = $instance ?? new Eprocurement_Local_Storage();
        return $cached;
    }

    /**
     * Clear the cached storage connection test result.
     *
     * Should be called whenever credentials are re-saved so the next
     * file operation re-validates against the new credentials.
     *
     * @since 2.14.0
     */
    public static function clear_connection_cache(): void {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_eproc_storage_ok_%'" ); // phpcs:ignore
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_eproc_storage_ok_%'" ); // phpcs:ignore
    }

    /**
     * Generate and persist a random OAuth state token for CSRF protection.
     *
     * Stores the value in a per-user transient keyed by the current user ID,
     * so multiple Super Admins can run OAuth flows concurrently without
     * colliding. TTL is 10 minutes.
     *
     * @return string Random 32-char alphanumeric state token.
     * @since 2.13.1  Security fix C-01 — OAuth CSRF prevention.
     */
    protected static function generate_oauth_state(): string {
        $state = wp_generate_password( 32, false );
        set_transient( 'eproc_oauth_state_' . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS );
        return $state;
    }

    /**
     * Get encrypted credentials from options.
     *
     * @return array Decoded credentials array.
     */
    protected function get_credentials(): array {
        $encrypted = get_option( 'eprocurement_cloud_credentials', '' );
        if ( empty( $encrypted ) ) {
            return [];
        }

        $decrypted = self::decrypt( $encrypted );
        $decoded   = json_decode( $decrypted, true );

        return is_array( $decoded ) ? $decoded : [];
    }

    /**
     * Save credentials (encrypted) to options.
     *
     * @param array $credentials Key-value credential data.
     */
    protected function save_credentials( array $credentials ): void {
        $json      = wp_json_encode( $credentials );
        $encrypted = self::encrypt( $json );
        update_option( 'eprocurement_cloud_credentials', $encrypted );
    }

    /**
     * Encrypt a string using WordPress auth keys.
     *
     * @param string $data Plain text to encrypt.
     * @return string Base64-encoded encrypted string.
     * @throws \RuntimeException If AUTH_KEY is not defined — encryption
     *                           without a secret key provides no security.
     */
    public static function encrypt( string $data ): string {
        $key    = self::get_encryption_key();
        $iv     = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'AES-256-CBC' ) );
        $cipher = openssl_encrypt( $data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        return base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
    }

    /**
     * Decrypt a string using WordPress auth keys.
     *
     * @param string $data Base64-encoded encrypted string.
     * @return string Decrypted plain text.
     */
    public static function decrypt( string $data ): string {
        $key  = self::get_encryption_key();
        $raw  = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        if ( $raw === false || strlen( $raw ) < 16 ) {
            return '';
        }
        $iv_len   = openssl_cipher_iv_length( 'AES-256-CBC' );
        $iv       = substr( $raw, 0, $iv_len );
        $cipher   = substr( $raw, $iv_len );

        $decrypted = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        return $decrypted ?: '';
    }

    /**
     * Derive encryption key from WordPress salts.
     *
     * @return string 32-byte binary key.
     * @throws \RuntimeException If AUTH_KEY is not defined or is empty.
     *                           Previously, this method silently fell back to
     *                           a publicly-known string when AUTH_KEY was
     *                           missing — that defeated the encryption
     *                           entirely (security fix H-06).
     */
    private static function get_encryption_key(): string {
        if ( ! defined( 'AUTH_KEY' ) || AUTH_KEY === '' || AUTH_KEY === 'put your unique phrase here' ) {
            throw new \RuntimeException(
                'eProcurement cannot encrypt credentials: AUTH_KEY is not configured in wp-config.php. ' .
                'Please generate unique salts at https://api.wordpress.org/secret-key/1.1/salt/ and add them to wp-config.php.'
            );
        }
        return hash( 'sha256', AUTH_KEY, true );
    }

    /**
     * Get allowed MIME types for file uploads.
     *
     * @return array Allowed MIME types.
     */
    public static function get_allowed_mime_types(): array {
        return [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'zip'  => 'application/zip',
        ];
    }

    /**
     * Validate file before upload.
     *
     * @param array $file     $_FILES array element.
     * @param int   $max_size Maximum file size in bytes (default: 50MB).
     * @return true|\WP_Error True on valid, WP_Error on invalid.
     */
    public static function validate_file( array $file, int $max_size = 52428800 ): true|\WP_Error {
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new \WP_Error( 'upload_error', __( 'File upload failed.', 'eprocurement' ) );
        }

        if ( $file['size'] > $max_size ) {
            $max_mb = round( $max_size / 1048576 );
            return new \WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %d: maximum file size in megabytes */
                    __( 'File exceeds maximum size of %dMB.', 'eprocurement' ),
                    $max_mb
                )
            );
        }

        $allowed = self::get_allowed_mime_types();
        $ext     = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        if ( ! isset( $allowed[ $ext ] ) ) {
            return new \WP_Error(
                'invalid_type',
                __( 'File type not allowed. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP.', 'eprocurement' )
            );
        }

        // MIME-type verification via finfo (security fix M-02: tightened
        // generic MIME allowlist so application/octet-stream is no longer
        // accepted for every extension — it now requires the extension to
        // be in a known-binary list, and we cross-check against the
        // expected MIME for the declared extension).
        $finfo = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : null;
        if ( $finfo ) {
            $detected_mime = finfo_file( $finfo, $file['tmp_name'] );
            finfo_close( $finfo );

            $expected_mime = $allowed[ $ext ];

            // Per-extension "generic" MIME map — only the types that
            // finfo commonly returns for these extensions on restrictive
            // server environments. application/octet-stream is only allowed
            // for binary containers (Office docs), never for images or PDFs.
            $generic_per_ext = [
                'doc'  => [ 'application/msword', 'application/octet-stream' ],
                'docx' => [ 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream' ],
                'xls'  => [ 'application/vnd.ms-excel', 'application/octet-stream' ],
                'xlsx' => [ 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream' ],
                'zip'  => [ 'application/zip', 'application/octet-stream' ],
            ];

            $allowed_for_ext = $generic_per_ext[ $ext ] ?? [ $expected_mime ];

            // Image and PDF extensions must match their expected MIME exactly.
            if ( in_array( $ext, [ 'pdf', 'jpg', 'jpeg', 'png' ], true ) ) {
                if ( $detected_mime && $detected_mime !== $expected_mime ) {
                    return new \WP_Error(
                        'mime_mismatch',
                        __( 'File content does not match its extension.', 'eprocurement' )
                    );
                }
            } else {
                // For Office/binary docs, accept any MIME in the per-extension list.
                if ( $detected_mime && ! in_array( $detected_mime, $allowed_for_ext, true ) ) {
                    return new \WP_Error(
                        'mime_mismatch',
                        __( 'File content does not match its extension.', 'eprocurement' )
                    );
                }
            }
        }

        return true;
    }
}
