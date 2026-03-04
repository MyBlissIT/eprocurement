<?php
/**
 * Dropbox storage provider.
 *
 * Uses Dropbox API v2 (OAuth 2.0) for file operations.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Dropbox extends Eprocurement_Storage_Interface {

    private const API_BASE     = 'https://api.dropboxapi.com/2';
    private const CONTENT_BASE = 'https://content.dropboxapi.com/2';
    private const AUTH_URL     = 'https://www.dropbox.com/oauth2';

    public function get_provider_name(): string {
        return 'dropbox';
    }

    /**
     * Get a valid access token, refreshing if necessary.
     */
    private function get_access_token(): string {
        $creds = $this->get_credentials();

        if ( empty( $creds['access_token'] ) ) {
            throw new \RuntimeException( __( 'Dropbox not connected. Please authenticate in Settings.', 'eprocurement' ) );
        }

        $expires_at = $creds['token_expires_at'] ?? 0;
        if ( time() >= $expires_at && ! empty( $creds['refresh_token'] ) ) {
            return $this->refresh_token( $creds );
        }

        return $creds['access_token'];
    }

    private function refresh_token( array $creds ): string {
        $response = wp_remote_post( 'https://api.dropboxapi.com/oauth2/token', [
            'body' => [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $creds['refresh_token'],
                'client_id'     => $creds['app_key'],
                'client_secret' => $creds['app_secret'],
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Dropbox token refresh failed: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new \RuntimeException( 'Dropbox token refresh error: ' . ( $body['error_description'] ?? $body['error'] ) );
        }

        $creds['access_token']     = $body['access_token'];
        $creds['token_expires_at'] = time() + ( $body['expires_in'] ?? 14400 );

        $this->save_credentials( $creds );

        return $body['access_token'];
    }

    public static function get_redirect_uri(): string {
        return admin_url( 'admin.php?page=eprocurement-settings&eproc_oauth_callback=dropbox' );
    }

    public function get_auth_url(): string {
        $creds  = $this->get_credentials();
        $params = http_build_query( [
            'client_id'             => $creds['app_key'] ?? '',
            'response_type'         => 'code',
            'redirect_uri'          => self::get_redirect_uri(),
            'token_access_type'     => 'offline',
        ] );

        return self::AUTH_URL . '/authorize?' . $params;
    }

    public function handle_oauth_callback( string $auth_code ): void {
        $creds = $this->get_credentials();

        $response = wp_remote_post( 'https://api.dropboxapi.com/oauth2/token', [
            'body' => [
                'code'          => $auth_code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => self::get_redirect_uri(),
                'client_id'     => $creds['app_key'] ?? '',
                'client_secret' => $creds['app_secret'] ?? '',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Dropbox OAuth failed: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new \RuntimeException( 'Dropbox OAuth error: ' . ( $body['error_description'] ?? $body['error'] ) );
        }

        $creds['access_token']     = $body['access_token'];
        $creds['refresh_token']    = $body['refresh_token'] ?? '';
        $creds['token_expires_at'] = time() + ( $body['expires_in'] ?? 14400 );

        $this->save_credentials( $creds );
    }

    public function upload( string $local_path, string $remote_name, string $folder = '' ): array {
        $token       = $this->get_access_token();
        $creds       = $this->get_credentials();
        $base_folder = $creds['folder_path'] ?? '/eprocurement';

        $path = rtrim( $base_folder, '/' ) . '/' . ( $folder ? trim( $folder, '/' ) . '/' : '' ) . $remote_name;

        $content = file_get_contents( $local_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        $response = wp_remote_post( self::CONTENT_BASE . '/files/upload', [
            'headers' => [
                'Authorization'   => 'Bearer ' . $token,
                'Content-Type'    => 'application/octet-stream',
                'Dropbox-API-Arg' => wp_json_encode( [
                    'path'            => $path,
                    'mode'            => 'add',
                    'autorename'      => true,
                    'mute'            => false,
                ] ),
            ],
            'body'    => $content,
            'timeout' => 120,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Dropbox upload failed: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            throw new \RuntimeException( 'Dropbox upload error: ' . wp_json_encode( $body['error'] ) );
        }

        return [
            'cloud_key' => $body['id'] ?? $body['path_display'] ?? '',
            'cloud_url' => $body['path_display'] ?? '',
        ];
    }

    public function get_download_url( string $cloud_key, int $expires_in = 3600 ): string {
        $token = $this->get_access_token();

        $response = wp_remote_post( self::API_BASE . '/files/get_temporary_link', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'path' => $cloud_key,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Dropbox link creation failed: ' . $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return $body['link'] ?? '';
    }

    public function delete( string $cloud_key ): bool {
        $token = $this->get_access_token();

        $response = wp_remote_post( self::API_BASE . '/files/delete_v2', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'path' => $cloud_key,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Dropbox delete failed: ' . $response->get_error_message() );
        }

        return wp_remote_retrieve_response_code( $response ) === 200;
    }

    public function test_connection(): bool {
        try {
            $token    = $this->get_access_token();
            $response = wp_remote_post( self::API_BASE . '/users/get_current_account', [
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
