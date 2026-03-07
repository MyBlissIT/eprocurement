<?php
/**
 * SMTP Configuration.
 *
 * Handles plugin-level SMTP configuration, replacing the dev-only
 * Mailpit block. Credentials are encrypted with AES-256-CBC.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Smtp {

    public function __construct() {
        $settings = $this->get_settings();
        if ( ! empty( $settings['host'] ) ) {
            add_action( 'phpmailer_init', [ $this, 'configure_phpmailer' ], 20 );
            add_filter( 'wp_mail_from', [ $this, 'set_from_email' ], 20 );
            add_filter( 'wp_mail_from_name', [ $this, 'set_from_name' ], 20 );
        }
    }

    /**
     * Configure PHPMailer with SMTP settings.
     */
    public function configure_phpmailer( $phpmailer ): void {
        $settings = $this->get_settings();

        if ( empty( $settings['host'] ) ) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $settings['host'];
        $phpmailer->Port       = (int) ( $settings['port'] ?? 587 );
        $phpmailer->SMTPSecure = $settings['encryption'] ?? '';

        if ( ! empty( $settings['username'] ) ) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $settings['username'];
            $phpmailer->Password = $settings['password'] ?? '';
        } else {
            $phpmailer->SMTPAuth = false;
        }

        // Disable auto TLS if encryption is explicitly set to none
        if ( empty( $settings['encryption'] ) ) {
            $phpmailer->SMTPAutoTLS = false;
        }
    }

    /**
     * Set the From email address.
     */
    public function set_from_email( string $from ): string {
        $settings = $this->get_settings();
        if ( ! empty( $settings['from_email'] ) ) {
            return $settings['from_email'];
        }
        // Fix localhost issue — use site domain instead of hardcoded fallback
        if ( strpos( $from, '@localhost' ) !== false ) {
            $domain = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'eprocurement.local';
            return 'noreply@' . $domain;
        }
        return $from;
    }

    /**
     * Set the From name.
     */
    public function set_from_name( string $name ): string {
        $settings = $this->get_settings();
        if ( ! empty( $settings['from_name'] ) ) {
            return $settings['from_name'];
        }
        return $name === 'WordPress' ? 'eProcurement' : $name;
    }

    /**
     * Send a test email.
     *
     * @param string $to Recipient email address.
     * @return true|string True on success, error message on failure.
     */
    public function send_test_email( string $to ): true|string {
        if ( ! $to || ! is_email( $to ) ) {
            return __( 'Please provide a valid email address.', 'eprocurement' );
        }

        $subject = __( 'eProcurement SMTP Test', 'eprocurement' );
        $body    = __( 'This is a test email from the eProcurement plugin. If you received this, your SMTP settings are working correctly.', 'eprocurement' );

        $result = wp_mail( $to, $subject, $body );

        if ( $result ) {
            return true;
        }

        global $phpmailer;
        $error = '';
        if ( isset( $phpmailer ) && $phpmailer->ErrorInfo ) {
            $error = $phpmailer->ErrorInfo;
        }

        return $error ?: __( 'Failed to send email. Please check your SMTP settings.', 'eprocurement' );
    }

    /**
     * Get decrypted SMTP settings.
     *
     * @return array SMTP settings (host, port, username, password, encryption, from_name, from_email).
     */
    private function get_settings(): array {
        static $cached = null;
        if ( $cached !== null ) {
            return $cached;
        }

        $encrypted = get_option( 'eprocurement_smtp_settings', '' );
        if ( empty( $encrypted ) ) {
            $cached = [];
            return $cached;
        }

        $decrypted = Eprocurement_Storage_Interface::decrypt( $encrypted );
        $decoded   = json_decode( $decrypted, true );

        $cached = is_array( $decoded ) ? $decoded : [];
        return $cached;
    }
}
