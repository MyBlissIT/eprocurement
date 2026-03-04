<?php
/**
 * S3-compatible storage provider.
 *
 * Works with AWS S3, DigitalOcean Spaces, Backblaze B2, MinIO, etc.
 * Uses key-based authentication (no OAuth).
 * Requires: aws/aws-sdk-php via Composer.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_S3 extends Eprocurement_Storage_Interface {

    private ?object $client = null;

    public function get_provider_name(): string {
        return 's3';
    }

    /**
     * Get or create the S3 client instance.
     */
    private function get_client(): object {
        if ( $this->client !== null ) {
            return $this->client;
        }

        $creds = $this->get_credentials();

        if ( empty( $creds['access_key'] ) || empty( $creds['secret_key'] ) || empty( $creds['bucket'] ) ) {
            throw new \RuntimeException( __( 'S3 credentials not configured.', 'eprocurement' ) );
        }

        $config = [
            'version'     => 'latest',
            'region'      => $creds['region'] ?? 'us-east-1',
            'credentials' => [
                'key'    => $creds['access_key'],
                'secret' => $creds['secret_key'],
            ],
        ];

        // Custom endpoint for S3-compatible services (DigitalOcean Spaces, MinIO, etc.)
        if ( ! empty( $creds['endpoint'] ) ) {
            $config['endpoint']                = $creds['endpoint'];
            $config['use_path_style_endpoint'] = $creds['use_path_style'] ?? true;
        }

        $this->client = new \Aws\S3\S3Client( $config );

        return $this->client;
    }

    /**
     * Get the bucket name from credentials.
     */
    private function get_bucket(): string {
        $creds = $this->get_credentials();
        return $creds['bucket'] ?? '';
    }

    public function upload( string $local_path, string $remote_name, string $folder = '' ): array {
        $client = $this->get_client();
        $bucket = $this->get_bucket();
        $creds  = $this->get_credentials();

        $prefix = $creds['prefix'] ?? 'eprocurement';
        $key    = trim( $prefix, '/' ) . '/' . ( $folder ? trim( $folder, '/' ) . '/' : '' ) . $remote_name;

        try {
            $result = $client->putObject( [
                'Bucket'      => $bucket,
                'Key'         => $key,
                'SourceFile'  => $local_path,
                'ContentType' => mime_content_type( $local_path ),
                'ACL'         => 'private',
            ] );

            return [
                'cloud_key' => $key,
                'cloud_url' => $result['ObjectURL'] ?? '',
            ];
        } catch ( \Exception $e ) {
            throw new \RuntimeException( 'S3 upload failed: ' . $e->getMessage() );
        }
    }

    public function get_download_url( string $cloud_key, int $expires_in = 3600 ): string {
        $client = $this->get_client();
        $bucket = $this->get_bucket();

        try {
            $cmd = $client->getCommand( 'GetObject', [
                'Bucket' => $bucket,
                'Key'    => $cloud_key,
            ] );

            $request = $client->createPresignedRequest( $cmd, "+{$expires_in} seconds" );

            return (string) $request->getUri();
        } catch ( \Exception $e ) {
            throw new \RuntimeException( 'S3 pre-signed URL generation failed: ' . $e->getMessage() );
        }
    }

    public function delete( string $cloud_key ): bool {
        $client = $this->get_client();
        $bucket = $this->get_bucket();

        try {
            $client->deleteObject( [
                'Bucket' => $bucket,
                'Key'    => $cloud_key,
            ] );
            return true;
        } catch ( \Exception $e ) {
            throw new \RuntimeException( 'S3 delete failed: ' . $e->getMessage() );
        }
    }

    public function test_connection(): bool {
        try {
            $client = $this->get_client();
            $bucket = $this->get_bucket();

            $client->headBucket( [ 'Bucket' => $bucket ] );

            return true;
        } catch ( \Exception $e ) {
            return false;
        }
    }
}
