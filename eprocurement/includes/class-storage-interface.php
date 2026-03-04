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
     * @return self|null Provider instance or null if none configured.
     */
    public static function get_active_provider(): ?self {
        $provider = get_option( 'eprocurement_cloud_provider', '' );

        $instance = match ( $provider ) {
            'google_drive' => new Eprocurement_Google_Drive(),
            'onedrive'     => new Eprocurement_Onedrive(),
            'dropbox'      => new Eprocurement_Dropbox(),
            's3'           => new Eprocurement_S3(),
            default        => null,
        };

        // If a cloud provider is configured, verify it can connect.
        // Fall back to local storage if it fails (e.g. missing credentials).
        if ( $instance && $provider !== '' ) {
            try {
                if ( ! $instance->test_connection() ) {
                    $instance = null;
                }
            } catch ( \Exception $e ) {
                $instance = null;
            }
        }

        return $instance ?? new Eprocurement_Local_Storage();
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
     */
    public static function encrypt( string $data ): string {
        $key    = self::get_encryption_key();
        $iv     = openssl_random_pseudo_bytes( 16 );
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
        $raw  = base64_decode( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $iv   = substr( $raw, 0, 16 );
        $data = substr( $raw, 16 );

        $decrypted = openssl_decrypt( $data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

        return $decrypted ?: '';
    }

    /**
     * Derive encryption key from WordPress salts.
     */
    private static function get_encryption_key(): string {
        $salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'eprocurement-fallback-key';
        return hash( 'sha256', $salt, true );
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

        // Basic MIME type check — verify declared type is in our allowed list.
        // We skip wp_check_filetype_and_ext() as it's overly strict in many
        // server environments (Docker, restrictive fileinfo configs) and
        // rejects legitimate DOC/DOCX/XLS/XLSX files.
        $finfo = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : null;
        if ( $finfo ) {
            $detected_mime = finfo_file( $finfo, $file['tmp_name'] );
            finfo_close( $finfo );

            // Allow common "generic" MIME types that finfo returns for Office docs
            $generic_mimes = [
                'application/octet-stream',
                'application/x-empty',
                'application/zip', // DOCX/XLSX are ZIP-based
            ];

            if ( $detected_mime && ! in_array( $detected_mime, $generic_mimes, true ) && ! in_array( $detected_mime, $allowed, true ) ) {
                return new \WP_Error(
                    'mime_mismatch',
                    __( 'File content does not match its extension.', 'eprocurement' )
                );
            }
        }

        return true;
    }
}
