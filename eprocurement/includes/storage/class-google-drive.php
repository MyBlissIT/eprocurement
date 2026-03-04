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
                $client->fetchAccessTokenWithRefreshToken( $creds['refresh_token'] );
                $new_token = $client->getAccessToken();

                // Save updated token
                $creds['access_token'] = $new_token;
                $this->save_credentials( $creds );
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
        $mimeType = mime_content_type( $local_path );

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

    public function get_download_url( string $cloud_key, int $expires_in = 3600 ): string {
        $service = $this->get_service();

        // Create a temporary permission for download
        $permission = new \Google\Service\Drive\Permission();
        $permission->setType( 'anyone' );
        $permission->setRole( 'reader' );
        $permission->setExpirationTime(
            gmdate( 'Y-m-d\TH:i:s\Z', time() + $expires_in )
        );

        try {
            $service->permissions->create( $cloud_key, $permission );
        } catch ( \Exception $e ) {
            // Permission might already exist
        }

        $file = $service->files->get( $cloud_key, [ 'fields' => 'webContentLink' ] );

        return $file->webContentLink ?? '';
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
