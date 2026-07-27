<?php
/**
 * CSP Nonce Generator.
 *
 * Generates a per-request cryptographic nonce used in the Content-Security-Policy
 * header to allow inline scripts. The nonce is generated once per request and
 * cached in a static variable so all callers in the same request see the same
 * value.
 *
 * Audit fix A3: previously the CSP allowed 'unsafe-inline' for scripts, which
 * largely defeated XSS protection. The new CSP uses nonce-based script-src
 * with 'strict-dynamic' so only scripts tagged with the per-request nonce
 * (or scripts loaded by such) can execute.
 *
 * @package Eprocurement
 * @since   2.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_CSP_Nonce {

    /**
     * The per-request nonce, cached after first generation.
     *
     * @var string|null
     */
    private static $nonce = null;

    /**
     * Get the per-request CSP nonce.
     *
     * Generates a fresh 22-char base64-encoded nonce on first call,
     * caches it in a static var, and returns the same value for all
     * subsequent calls in the same request.
     *
     * @return string The nonce (22-char base64 of 16 random bytes).
     */
    public static function get_nonce(): string {
        if ( self::$nonce === null ) {
            // 16 bytes (128 bits) of entropy, base64-encoded → 22 chars.
            // CSP nonces must be base64-encoded per the spec.
            self::$nonce = base64_encode( random_bytes( 16 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        }
        return self::$nonce;
    }

    /**
     * Render a nonce attribute for use in an inline <script> tag.
     *
     * Helper for templates that want to manually add the nonce:
     *   <script <?php echo Eprocurement_CSP_Nonce::attribute(); ?>>...</script>
     *
     * @return string Escaped nonce attribute: nonce="..."
     */
    public static function attribute(): string {
        return 'nonce="' . esc_attr( self::get_nonce() ) . '"';
    }
}
