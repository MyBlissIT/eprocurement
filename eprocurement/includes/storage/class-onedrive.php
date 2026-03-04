<?php
/**
 * Microsoft OneDrive storage provider.
 *
 * Uses Microsoft Graph API (OAuth 2.0) for file operations.
 * Requires: microsoft/microsoft-graph via Composer.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Onedrive extends Eprocurement_Storage_Interface {

    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';
    private const AUTH_URL   = 'https://login.microsoftonline.com/common/oauth2/v2.0';

    public function get_provider_name(): string {
        return 'onedrive';
    }

    /**
     * Get a valid access token, refreshing if necessary.
     */
    private function get_access_token(): string {
        $creds = $this->get_credentials();

        if ( empty( $creds['access_token'] ) ) {
            throw new \RuntimeException( __( 'OneDrive not connected. Please authenticate in Settings.', 'eprocurement' ) );
        }

        // Check if token is expired
        $expires_at = $creds['token_expires_at'] ?? 0;
        if ( time() >= $expires_at && ! empty( $creds['refresh_token'] ) ) {
            return $this->refresh_token( $creds );
        }

        return $creds['access_token'];
    }

    /**
     * Refresh the OAuth access token.
     */
    private function refresh_token( array $creds ): string {
        $response = wp_remote_post( self::AUTH_URL . '/token', [
            'body' => [
                'client_id'     => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'refresh_token' => $creds['refresh_token'],
                'grant_type'    => 'refresh_token',
                'scope'         => 'Files.ReadWrite.All offline_access',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'OneDrive token refresh failed: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new \RuntimeException( 'OneDrive token refresh error: ' . ( $body['error_description'] ?? $body['error'] ) );
        }

        $creds['access_token']    = $body['access_token'];
        $creds['refresh_token']   = $body['refresh_token'] ?? $creds['refresh_token'];
        $creds['token_expires_at'] = time() + ( $body['expires_in'] ?? 3600 );

        $this->save_credentials( $creds );

        return $body['access_token'];
    }

    public static function get_redirect_uri(): string {
        return admin_url( 'admin.php?page=eprocurement-settings&eproc_oauth_callback=onedrive' );
    }

    public function get_auth_url(): string {
        $creds = $this->get_credentials();
        $params = http_build_query( [
            'client_id'     => $creds['client_id'] ?? '',
            'response_type' => 'code',
            'redirect_uri'  => self::get_redirect_uri(),
            'scope'         => 'Files.ReadWrite.All offline_access',
            'response_mode' => 'query',
        ] );

        return self::AUTH_URL . '/authorize?' . $params;
    }

    public function handle_oauth_callback( string $auth_code ): void {
        $creds = $this->get_credentials();

        $response = wp_remote_post( self::AUTH_URL . '/token', [
            'body' => [
                'client_id'     => $creds['client_id'] ?? '',
                'client_secret' => $creds['client_secret'] ?? '',
                'code'          => $auth_code,
                'redirect_uri'  => self::get_redirect_uri(),
                'grant_type'    => 'authorization_code',
                'scope'         => 'Files.ReadWrite.All offline_access',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'OneDrive OAuth failed: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new \RuntimeException( 'OneDrive OAuth error: ' . ( $body['error_description'] ?? $body['error'] ) );
        }

        $creds['access_token']     = $body['access_token'];
        $creds['refresh_token']    = $body['refresh_token'] ?? '';
        $creds['token_expires_at'] = time() + ( $body['expires_in'] ?? 3600 );

        $this->save_credentials( $creds );
    }

    public function upload( string $local_path, string $remote_name, string $folder = '' ): array {
        $token   = $this->get_access_token();
        $creds   = $this->get_credentials();
        $base_folder = $creds['folder_path'] ?? '/eprocurement';

        $path = rtrim( $base_folder, '/' ) . '/' . ( $folder ? trim( $folder, '/' ) . '/' : '' ) . $remote_name;

        $content = file_get_contents( $local_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        $response = wp_remote_request( self::GRAPH_BASE . '/me/drive/root:' . $path . ':/content', [
            'method'  => 'PUT',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => mime_content_type( $local_path ),
            ],
            'body'    => $content,
            'timeout' => 120,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'OneDrive upload failed: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new \RuntimeException( 'OneDrive upload error: ' . ( $body['error']['message'] ?? 'Unknown error' ) );
        }

        return [
            'cloud_key' => $body['id'] ?? '',
            'cloud_url' => $body['webUrl'] ?? '',
        ];
    }

    public function get_download_url( string $cloud_key, int $expires_in = 3600 ): string {
        $token = $this->get_access_token();

        // Create a sharing link
        $response = wp_remote_post( self::GRAPH_BASE . '/me/drive/items/' . $cloud_key . '/createLink', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'type'               => 'view',
                'scope'              => 'anonymous',
                'expirationDateTime' => gmdate( 'Y-m-d\TH:i:s\Z', time() + $expires_in ),
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'OneDrive link creation failed: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return $body['link']['webUrl'] ?? '';
    }

    public function delete( string $cloud_key ): bool {
        $token = $this->get_access_token();

        $response = wp_remote_request( self::GRAPH_BASE . '/me/drive/items/' . $cloud_key, [
            'method'  => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'OneDrive delete failed: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );

        return $code === 204 || $code === 200;
    }

    public function test_connection(): bool {
        try {
            $token    = $this->get_access_token();
            $response = wp_remote_get( self::GRAPH_BASE . '/me/drive', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ] );

            return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
        } catch ( \Exception $e ) {
            return false;
        }
    }
}
