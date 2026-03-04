<?php
/**
 * Local filesystem storage provider.
 *
 * Stores files in wp-content/uploads/eprocurement/ as a fallback
 * when no cloud storage provider is configured.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Local_Storage extends Eprocurement_Storage_Interface {

    /**
     * Base upload directory for the plugin.
     */
    private function get_base_dir(): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/eprocurement';
    }

    /**
     * Base upload URL for the plugin.
     */
    private function get_base_url(): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/eprocurement';
    }

    /**
     * Upload a file to local storage.
     */
    public function upload( string $local_path, string $remote_name, string $folder = '' ): array {
        $base_dir   = $this->get_base_dir();
        $target_dir = $folder ? $base_dir . '/' . sanitize_file_name( $folder ) : $base_dir;

        // Ensure directory exists with correct permissions
        if ( ! wp_mkdir_p( $target_dir ) ) {
            throw new \RuntimeException( __( 'Failed to create upload directory.', 'eprocurement' ) );
        }

        // Ensure the directory is writable by the web server
        if ( ! is_writable( $target_dir ) ) {
            @chmod( $target_dir, 0755 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ( ! is_writable( $target_dir ) ) {
                throw new \RuntimeException(
                    sprintf(
                        /* translators: %s: directory path */
                        __( 'Upload directory is not writable: %s', 'eprocurement' ),
                        $target_dir
                    )
                );
            }
        }

        // Protect directory with .htaccess (prevent direct browsing)
        $htaccess = $base_dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            @file_put_contents( $htaccess, "Options -Indexes\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.PHP.NoSilencedErrors.Discouraged
        }

        // Generate unique filename to prevent overwrites
        $ext       = pathinfo( $remote_name, PATHINFO_EXTENSION );
        $base_name = pathinfo( $remote_name, PATHINFO_FILENAME );
        $safe_name = sanitize_file_name( $base_name ) . '-' . wp_generate_password( 8, false ) . '.' . $ext;
        $dest_path = $target_dir . '/' . $safe_name;

        // Cloud key is the relative path from base dir
        $cloud_key = $folder ? $folder . '/' . $safe_name : $safe_name;

        // Use move_uploaded_file for actual uploads, copy for other sources
        $success = is_uploaded_file( $local_path )
            ? move_uploaded_file( $local_path, $dest_path )
            : copy( $local_path, $dest_path );

        if ( ! $success ) {
            throw new \RuntimeException( __( 'Failed to save file. Please check upload directory permissions.', 'eprocurement' ) );
        }

        return [
            'cloud_key' => $cloud_key,
            'cloud_url' => $this->get_base_url() . '/' . $cloud_key,
        ];
    }

    /**
     * Generate a download URL for a locally stored file.
     */
    public function get_download_url( string $cloud_key, int $expires_in = 3600 ): string {
        // For local storage, return a direct URL (protected by WP nonce at the download endpoint)
        return $this->get_base_url() . '/' . $cloud_key;
    }

    /**
     * Delete a file from local storage.
     */
    public function delete( string $cloud_key ): bool {
        $file_path = $this->get_base_dir() . '/' . $cloud_key;

        if ( file_exists( $file_path ) ) {
            wp_delete_file( $file_path );
            return ! file_exists( $file_path );
        }

        return true; // File doesn't exist, consider it deleted
    }

    /**
     * Test the local storage connection.
     */
    public function test_connection(): bool {
        $base_dir = $this->get_base_dir();
        return wp_mkdir_p( $base_dir ) && is_writable( $base_dir );
    }

    /**
     * Get the provider name.
     */
    public function get_provider_name(): string {
        return 'local';
    }
}
