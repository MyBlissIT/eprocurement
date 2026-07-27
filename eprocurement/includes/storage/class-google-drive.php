<?php
/**
 * Google Drive storage provider.
 *
 * Uses Google API Client (OAuth 2.0) for file operations.
 * Requires: google/apiclient via Composer.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Google_Drive extends Eprocurement_Storage_Interface {

    private ?object $service = null;

    public function get_provider_name(): string {
        return 'google_drive';
    }

    /**
     * Get or create the Google Drive service instance.
     */
    private function get_service(): object {
        if ( $this->service !== null ) {
            return $this->service;
        }

        $creds = $this->get_credentials();

        if ( empty( $creds['client_id'] ) || empty( $creds['client_secret'] ) ) {
            throw new \RuntimeException( __( 'Google Drive credentials not configured.', 'eprocurement' ) );
        }

        $client = new \Google\Client();
        $client->setClientId( $creds['client_id'] );
        $client->setClientSecret( $creds['client_secret'] );
        $client->setAccessType( 'offline' );
        $client->setPrompt( 'consent' );
        $client->addScope( \Google\Service\Drive::DRIVE_FILE );

        // Set access token
        if ( ! empty( $creds['access_token'] ) ) {
            $client->setAccessToken( $creds['access_token'] );

            // Refresh token if expired
            if ( $client->isAccessTokenExpired() && ! empty( $creds['refresh_token'] ) ) {
                try {
                    $client->fetchAccessTokenWithRefreshToken( $creds['refresh_token'] );
                    $new_token = $client->getAccessToken();

                    // Save updated token
                    $creds['access_token'] = $new_token;
                    $this->save_credentials( $creds );
                } catch ( \Exception $e ) {
                    throw new \RuntimeException( 'Google Drive token refresh failed: ' . $e->getMessage() );
                }
            }
        }

        $this->service = new \Google\Service\Drive( $client );

        return $this->service;
    }

    /**
     * Get the OAuth redirect URI for this provider.
     */
    public static function get_redirect_uri(): string {
        return admin_url( 'admin.php?page=eprocurement-settings&eproc_oauth_callback=google_drive' );
    }

    /**
     * Get the OAuth authorization URL.
     */
    public function get_auth_url(): string {
        $creds = $this->get_credentials();

        $client = new \Google\Client();
        $client->setClientId( $creds['client_id'] ?? '' );
        $client->setClientSecret( $creds['client_secret'] ?? '' );
        $client->setRedirectUri( self::get_redirect_uri() );
        $client->setAccessType( 'offline' );
        $client->setPrompt( 'consent' );
        $client->addScope( \Google\Service\Drive::DRIVE_FILE );
        $client->setState( self::generate_oauth_state() );

        return $client->createAuthUrl();
    }

    /**
     * Handle the OAuth callback and store tokens.
     *
     * @param string $auth_code Authorization code from callback.
     */
    public function handle_oauth_callback( string $auth_code ): void {
        $creds = $this->get_credentials();

        $client = new \Google\Client();
        $client->setClientId( $creds['client_id'] ?? '' );
        $client->setClientSecret( $creds['client_secret'] ?? '' );
        $client->setRedirectUri( self::get_redirect_uri() );

        $token = $client->fetchAccessTokenWithAuthCode( $auth_code );

        if ( isset( $token['error'] ) ) {
            throw new \RuntimeException(
                sprintf( 'Google OAuth error: %s', $token['error_description'] ?? $token['error'] )
            );
        }

        $creds['access_token']  = $token;
        $creds['refresh_token'] = $token['refresh_token'] ?? ( $creds['refresh_token'] ?? '' );

        $this->save_credentials( $creds );
    }

    public function upload( string $local_path, string $remote_name, string $folder = '' ): array {
        $service = $this->get_service();

        $file_metadata = new \Google\Service\Drive\DriveFile();
        $file_metadata->setName( $remote_name );

        // Set parent folder if configured
        $creds     = $this->get_credentials();
        $folder_id = $creds['folder_id'] ?? '';
        if ( $folder_id ) {
            $file_metadata->setParents( [ $folder_id ] );
        }

        $content  = file_get_contents( $local_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $mimeType = wp_check_filetype( $local_path )['type'] ?: 'application/octet-stream';

        $uploaded = $service->files->create( $file_metadata, [
            'data'       => $content,
            'mimeType'   => $mimeType,
            'uploadType' => 'multipart',
            'fields'     => 'id, webContentLink',
        ] );

        return [
            'cloud_key' => $uploaded->id,
            'cloud_url' => $uploaded->webContentLink ?? '',
        ];
    }

    /**
     * Get a server-side download URL for a Google Drive file.
     *
     * Audit fix A28: previously created an `anyone`/`reader` permission with
     * an expiration window, making the file publicly downloadable by anyone
     * with the URL during that window. The URL leaked via browser history,
     * referer headers, and support tickets — exposing sealed-bid submissions
     * and tender documents.
     *
     * Now returns the Google Drive API `?alt=media` endpoint URL with the
     * access token appended. This URL is consumed server-side only (by
     * stream_file() in the base class, which downloads via wp_remote_get).
     * The URL is never sent to the browser.
     *
     * @param string $cloud_key  Google Drive file ID.
     * @param int    $expires_in Ignored — token expiry is governed by the OAuth refresh cycle.
     * @return string Server-side-only download URL with access token.
     * @throws \RuntimeException If the access token cannot be retrieved.
     */
    public function get_download_url( string $cloud_key, int $expires_in = 3600 ): string {
        $service = $this->get_service();
        $client  = $service->getClient();

        // Force a token refresh if needed, then retrieve the access token.
        $access_token = $client->getAccessToken();
        if ( empty( $access_token['access_token'] ) ) {
            $client->fetchAccessTokenWithRefreshToken( $client->getRefreshToken() );
            $access_token = $client->getAccessToken();
        }

        if ( empty( $access_token['access_token'] ) ) {
            throw new \RuntimeException( 'Google Drive: no access token available.' );
        }

        // Return the API endpoint URL with the access token as a query param.
        // This URL is only valid for ~1 hour (token lifetime) and is consumed
        // server-side by stream_file() — never exposed to the browser.
        return "https://www.googleapis.com/drive/v3/files/{$cloud_key}?alt=media&access_token=" . rawurlencode( $access_token['access_token'] );
    }

    public function delete( string $cloud_key ): bool {
        $service = $this->get_service();

        try {
            $service->files->delete( $cloud_key );
            return true;
        } catch ( \Exception $e ) {
            throw new \RuntimeException(
                sprintf( 'Google Drive delete failed: %s', $e->getMessage() )
            );
        }
    }

    public function test_connection(): bool {
        try {
            $service = $this->get_service();
            $service->files->listFiles( [ 'pageSize' => 1 ] );
            return true;
        } catch ( \Exception $e ) {
            return false;
        }
    }
}
