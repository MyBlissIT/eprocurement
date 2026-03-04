<?php
/**
 * External Database Connector.
 *
 * Connects to a client's existing database to pull users and
 * provision them as WordPress users with eProcurement roles.
 * One-way sync: read-only from external DB → WordPress.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_External_Db {

    /**
     * Test connection to the external database.
     *
     * @return true|string True on success, error message on failure.
     */
    public function test_connection(): true|string {
        $settings = $this->get_settings();

        if ( empty( $settings['host'] ) || empty( $settings['database'] ) ) {
            return __( 'External database not configured.', 'eprocurement' );
        }

        try {
            $pdo = $this->connect( $settings );
            $pdo->query( 'SELECT 1' );
            return true;
        } catch ( \PDOException $e ) {
            return $e->getMessage();
        }
    }

    /**
     * Sync users from external database to WordPress.
     *
     * @return array{created: int, updated: int, skipped: int}|\WP_Error
     */
    public function sync_users(): array|\WP_Error {
        $settings = $this->get_settings();

        if ( empty( $settings['host'] ) || empty( $settings['database'] ) ) {
            return new \WP_Error( 'not_configured', __( 'External database not configured.', 'eprocurement' ) );
        }

        try {
            $pdo = $this->connect( $settings );
        } catch ( \PDOException $e ) {
            return new \WP_Error( 'connection_failed', $e->getMessage() );
        }

        $table      = $settings['table'] ?? 'users';
        $email_col  = $settings['email_column'] ?? 'email';
        $name_col   = $settings['name_column'] ?? 'full_name';
        $default_role = $settings['default_role'] ?? 'eprocurement_scm_official';

        // Validate role
        $valid_roles = [ 'eprocurement_scm_manager', 'eprocurement_scm_official', 'eprocurement_unit_manager' ];
        if ( ! in_array( $default_role, $valid_roles, true ) ) {
            $default_role = 'eprocurement_scm_official';
        }

        // Sanitise table and column names (no user input in SQL identifiers)
        $table     = preg_replace( '/[^a-zA-Z0-9_]/', '', $table );
        $email_col = preg_replace( '/[^a-zA-Z0-9_]/', '', $email_col );
        $name_col  = preg_replace( '/[^a-zA-Z0-9_]/', '', $name_col );

        try {
            $stmt = $pdo->query( "SELECT `{$email_col}`, `{$name_col}` FROM `{$table}` WHERE `{$email_col}` IS NOT NULL AND `{$email_col}` != ''" );
            $rows = $stmt->fetchAll( \PDO::FETCH_ASSOC );
        } catch ( \PDOException $e ) {
            return new \WP_Error( 'query_failed', $e->getMessage() );
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ( $rows as $row ) {
            $email = sanitize_email( $row[ $email_col ] ?? '' );
            $name  = sanitize_text_field( $row[ $name_col ] ?? '' );

            if ( ! $email || ! is_email( $email ) ) {
                $skipped++;
                continue;
            }

            $existing = get_user_by( 'email', $email );

            if ( $existing ) {
                // Update display name if changed
                if ( $name && $existing->display_name !== $name ) {
                    wp_update_user( [
                        'ID'           => $existing->ID,
                        'display_name' => $name,
                    ] );
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }

            // Create new WordPress user
            $username = sanitize_user( strtolower( explode( '@', $email )[0] ), true );
            if ( username_exists( $username ) ) {
                $username .= wp_rand( 100, 999 );
            }

            $user_id = wp_insert_user( [
                'user_login'   => $username,
                'user_email'   => $email,
                'user_pass'    => wp_generate_password( 16 ),
                'display_name' => $name ?: $username,
                'first_name'   => $name ? explode( ' ', $name )[0] : '',
                'role'         => $default_role,
            ] );

            if ( is_wp_error( $user_id ) ) {
                $skipped++;
                continue;
            }

            // Send password setup email
            wp_new_user_notification( $user_id, null, 'user' );
            $created++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * Create a PDO connection to the external database.
     *
     * @param array $settings Connection settings.
     * @return \PDO
     * @throws \PDOException
     */
    private function connect( array $settings ): \PDO {
        $host     = $settings['host'];
        $port     = $settings['port'] ?? 3306;
        $database = $settings['database'];
        $username = $settings['username'] ?? '';
        $password = $settings['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        return new \PDO( $dsn, $username, $password, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_TIMEOUT            => 10,
        ] );
    }

    /**
     * Get decrypted external DB settings.
     *
     * @return array Settings array.
     */
    private function get_settings(): array {
        $encrypted = get_option( 'eprocurement_external_db_settings', '' );
        if ( empty( $encrypted ) ) {
            return [];
        }

        $decrypted = Eprocurement_Storage_Interface::decrypt( $encrypted );
        $decoded   = json_decode( $decrypted, true );

        return is_array( $decoded ) ? $decoded : [];
    }
}
