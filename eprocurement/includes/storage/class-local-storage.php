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
        // Sanitize each path segment individually to preserve directory separators.
        $target_dir = $folder
            ? $base_dir . '/' . implode( '/', array_map( 'sanitize_file_name', explode( '/', $folder ) ) )
            : $base_dir;

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

        // Protect directory with .htaccess (deny ALL direct access — security fix C-02).
        // Sealed-bid submissions stored here must only be reachable via the
        // nonce-protected /eproc-download/ endpoint, never by direct URL.
        $htaccess = $base_dir . '/.htaccess';
        $htaccess_content = trim(
            "# eProcurement protected storage — deny ALL direct HTTP access\n" .
            "Options -Indexes\n" .
            "Deny from all\n" .
            "<IfModule mod_authz_core.c>\n" .
            "  Require all denied\n" .
            "</IfModule>\n" .
            "<IfModule mod_php.c>\n" .
            "  php_flag engine off\n" .
            "</IfModule>\n" .
            "<IfModule mod_php7.c>\n" .
            "  php_flag engine off\n" .
            "</IfModule>\n" .
            "<IfModule mod_php8.c>\n" .
            "  php_flag engine off\n" .
            "</IfModule>"
        );

        if ( ! file_exists( $htaccess ) || sha1_file( $htaccess ) !== sha1( $htaccess_content ) ) {
            file_put_contents( $htaccess, $htaccess_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            @chmod( $htaccess, 0644 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }

        // Generate unique filename to prevent overwrites
        // Audit fix A25 (local-storage variant): validate extension against
        // the allowed MIME types to prevent .php uploads from being stored
        // (defense-in-depth for nginx/litespeed where .htaccess is ignored).
        $ext       = strtolower( pathinfo( $remote_name, PATHINFO_EXTENSION ) );
        $base_name = pathinfo( $remote_name, PATHINFO_FILENAME );
        $allowed   = self::get_allowed_mime_types();
        if ( ! isset( $allowed[ $ext ] ) ) {
            throw new \RuntimeException(
                sprintf(
                    /* translators: %s: file extension */
                    __( 'File type "%s" is not allowed.', 'eprocurement' ),
                    esc_html( $ext )
                )
            );
        }
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
     *
     * Audit fix A22: previously returned a direct URL to the file under
     * wp-content/uploads/eprocurement/. The .htaccess protection only
     * works on Apache with mod_php — nginx, LiteSpeed, and Apache without
     * mod_php would serve the file publicly, exposing sealed-bid submissions
     * and tender documents to anyone with the URL.
     *
     * Now always returns the nonce-protected PHP download endpoint URL.
     * The actual file path is resolved server-side by class-downloads.php
     * after nonce + capability verification.
     */
    public function get_download_url( string $cloud_key, int $expires_in = 3600 ): string {
        // Return the WordPress-powered download endpoint. The cloud_key is
        // passed as a query param; class-downloads.php resolves it to the
        // local file path after authenticating the request.
        $slug = eprocurement_get_slug();
        return home_url( "/{$slug}/download/?key=" . rawurlencode( $cloud_key ) );
    }

    /**
     * Delete a file from local storage.
     *
     * Audit fix A23: added realpath() containment check to prevent path
     * traversal via attacker-controlled cloud_key values. Without this,
     * a cloud_key like '../../../wp-config.php' could delete critical files.
     */
    public function delete( string $cloud_key ): bool {
        $base_dir   = $this->get_base_dir();
        $file_path  = $base_dir . '/' . $cloud_key;

        // Audit fix A23: verify the resolved path is inside the base dir.
        $real_base  = realpath( $base_dir );
        $real_file  = realpath( $file_path );
        if ( ! $real_base || ! $real_file ) {
            // File doesn't exist — nothing to delete.
            return true;
        }
        if ( strpos( $real_file, $real_base . '/' ) !== 0 && $real_file !== $real_base ) {
            // Path traversal attempt — refuse to delete.
            return false;
        }

        if ( file_exists( $real_file ) ) {
            wp_delete_file( $real_file );
            return ! file_exists( $real_file );
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
