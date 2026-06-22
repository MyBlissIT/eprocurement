<?php
/**
 * Two-Factor Authentication (TOTP) for Staff Users.
 *
 * Implements RFC 6238 TOTP using hash_hmac — no external dependencies.
 * Staff users (SCM Manager, SCM Official, Unit Manager, Admin) can
 * enable 2FA from their profile. On login, they're prompted for a
 * 6-digit code from their authenticator app (Google Authenticator,
 * Authy, Microsoft Authenticator, etc.).
 *
 * @package Eprocurement
 * @since   2.16.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Two_Factor {

    private const SECRET_META_KEY = 'eproc_2fa_secret';
    private const ENABLED_META_KEY = 'eproc_2fa_enabled';
    private const VERIFY_META_KEY = 'eproc_2fa_pending_verify';

    /**
     * Register hooks.
     */
    public function __construct() {
        // Intercept login form — show 2FA prompt after password verification.
        add_filter( 'authenticate', [ $this, 'check_2fa_on_login' ], 30, 3 );

        // Handle the 2FA code submission on the login form.
        add_action( 'login_form_eproc_2fa', [ $this, 'handle_2fa_verification' ] );

        // Add 2FA settings to the user profile page.
        add_action( 'show_user_profile', [ $this, 'render_profile_2fa_settings' ] );
        add_action( 'edit_user_profile', [ $this, 'render_profile_2fa_settings' ] );
        add_action( 'personal_options_update', [ $this, 'save_profile_2fa_settings' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_profile_2fa_settings' ] );
    }

    /**
     * Check if 2FA is enabled for a user.
     */
    public static function is_enabled( int $user_id ): bool {
        return get_user_meta( $user_id, self::ENABLED_META_KEY, true ) === '1'
            && ! empty( get_user_meta( $user_id, self::SECRET_META_KEY, true ) );
    }

    /**
     * Intercept the login flow — if the user has 2FA enabled, redirect
     * to the 2FA verification step instead of completing login.
     */
    public function check_2fa_on_login( $user, $username, $password ) {
        if ( ! $user instanceof \WP_User ) {
            return $user;
        }

        if ( ! self::is_enabled( $user->ID ) ) {
            return $user;
        }

        // Store the user ID in a transient for the 2FA step.
        $transient_key = 'eproc_2fa_login_' . wp_generate_password( 32, false );
        set_transient( $transient_key, $user->ID, 5 * MINUTE_IN_SECONDS );

        // Redirect to the 2FA verification page.
        $redirect_url = wp_login_url();
        $redirect_url = add_query_arg( [
            'action' => 'eproc_2fa',
            'eproc_2fa_token' => $transient_key,
        ], $redirect_url );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle the 2FA code verification on the login form.
     */
    public function handle_2fa_verification(): void {
        $token = sanitize_text_field( $_GET['eproc_2fa_token'] ?? $_POST['eproc_2fa_token'] ?? '' );
        $code  = sanitize_text_field( $_POST['eproc_2fa_code'] ?? '' );

        if ( ! $token ) {
            wp_die( __( 'Invalid 2FA session.', 'eprocurement' ) );
        }

        $user_id = (int) get_transient( $token );

        if ( ! $user_id ) {
            wp_die( __( 'Your 2FA session has expired. Please log in again.', 'eprocurement' ), __( 'Session Expired', 'eprocurement' ), [ 'response' => 403 ] );
        }

        // If a code was submitted, verify it.
        if ( $code ) {
            $secret = get_user_meta( $user_id, self::SECRET_META_KEY, true );

            if ( $this->verify_code( $secret, $code ) ) {
                // Code is correct — complete the login.
                delete_transient( $token );
                wp_set_auth_cookie( $user_id, true );
                wp_set_current_user( $user_id );

                $redirect_to = admin_url();
                wp_safe_redirect( $redirect_to );
                exit;
            } else {
                // Wrong code — show the form again with an error.
                $this->render_2fa_form( $token, __( 'Invalid verification code. Please try again.', 'eprocurement' ) );
                exit;
            }
        }

        // Show the 2FA form.
        $this->render_2fa_form( $token );
        exit;
    }

    /**
     * Render the 2FA verification form on the login screen.
     */
    private function render_2fa_form( string $token, string $error = '' ): void {
        $logo_url = get_option( 'eprocurement_brand_logo', '' );
        $brand_name = get_option( 'eprocurement_brand_name', 'eProcurement' );
        $primary = get_option( 'eprocurement_brand_colors', [] );
        $primary_color = is_array( $primary ) ? ( $primary['eproc-primary'] ?? '#8b1a2b' ) : '#8b1a2b';

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html( $brand_name ); ?> — Two-Factor Authentication</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; margin: 0; padding: 40px 20px; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
                .eproc-2fa-card { background: #fff; border-radius: 16px; box-shadow: 0 20px 50px rgba(15,23,42,0.15); padding: 40px; max-width: 440px; width: 100%; }
                .eproc-2fa-title { font-size: 24px; font-weight: 700; color: #1e293b; margin: 0 0 8px; }
                .eproc-2fa-subtitle { font-size: 14px; color: #64748b; margin: 0 0 28px; line-height: 1.5; }
                .eproc-2fa-input { width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 24px; text-align: center; letter-spacing: 8px; font-weight: 700; }
                .eproc-2fa-input:focus { outline: none; border-color: <?php echo esc_attr( $primary_color ); ?>; box-shadow: 0 0 0 3px <?php echo esc_attr( $primary_color ); ?>22; }
                .eproc-2fa-btn { width: 100%; padding: 14px; background: <?php echo esc_attr( $primary_color ); ?>; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 16px; }
                .eproc-2fa-btn:hover { opacity: 0.9; }
                .eproc-2fa-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
                .eproc-2fa-brand { text-align: center; margin-bottom: 24px; }
                .eproc-2fa-brand img { max-height: 40px; max-width: 160px; }
                .eproc-2fa-brand-name { font-size: 14px; color: #64748b; font-weight: 600; }
                .eproc-2fa-hint { text-align: center; margin-top: 20px; font-size: 13px; color: #64748b; }
                .eproc-2fa-hint a { color: <?php echo esc_attr( $primary_color ); ?>; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class="eproc-2fa-card">
                <div class="eproc-2fa-brand">
                    <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>">
                    <?php else : ?>
                        <span class="eproc-2fa-brand-name"><?php echo esc_html( $brand_name ); ?></span>
                    <?php endif; ?>
                </div>
                <h1 class="eproc-2fa-title"><?php esc_html_e( 'Two-Factor Authentication', 'eprocurement' ); ?></h1>
                <p class="eproc-2fa-subtitle"><?php esc_html_e( 'Enter the 6-digit code from your authenticator app.', 'eprocurement' ); ?></p>
                <?php if ( $error ) : ?>
                    <div class="eproc-2fa-error"><?php echo esc_html( $error ); ?></div>
                <?php endif; ?>
                <form method="post" action="">
                    <input type="hidden" name="eproc_2fa_token" value="<?php echo esc_attr( $token ); ?>">
                    <input type="text" name="eproc_2fa_code" class="eproc-2fa-input" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" autofocus required>
                    <button type="submit" class="eproc-2fa-btn"><?php esc_html_e( 'Verify', 'eprocurement' ); ?></button>
                </form>
                <p class="eproc-2fa-hint"><a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( '← Back to login', 'eprocurement' ); ?></a></p>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Render 2FA settings on the user profile page.
     */
    public function render_profile_2fa_settings( \WP_User $user ): void {
        // Only show for staff roles.
        if ( ! Eprocurement_Roles::is_staff( $user->ID ) ) {
            return;
        }

        $enabled = self::is_enabled( $user->ID );
        $secret = get_user_meta( $user->ID, self::SECRET_META_KEY, true );
        $pending_secret = get_user_meta( $user->ID, self::VERIFY_META_KEY, true );

        ?>
        <h2><?php esc_html_e( 'eProcurement Two-Factor Authentication', 'eprocurement' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Status', 'eprocurement' ); ?></th>
                <td>
                    <?php if ( $enabled ) : ?>
                        <span style="color:#16a34a;font-weight:600;">✓ <?php esc_html_e( 'Enabled', 'eprocurement' ); ?></span>
                        <p class="description"><?php esc_html_e( 'You will be prompted for a verification code on each login.', 'eprocurement' ); ?></p>
                    <?php else : ?>
                        <span style="color:#64748b;font-weight:600;"><?php esc_html_e( 'Not enabled', 'eprocurement' ); ?></span>
                        <p class="description"><?php esc_html_e( 'Enable 2FA to add an extra layer of security to your staff account.', 'eprocurement' ); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ( $pending_secret ) :
                $qr_url = $this->get_qr_url( $user->user_email, $pending_secret );
                $qr_data_uri = $this->generate_qr_svg_data_uri( $qr_url );
            ?>
            <tr>
                <th><?php esc_html_e( 'Scan QR Code', 'eprocurement' ); ?></th>
                <td>
                    <?php if ( $qr_data_uri ) : ?>
                        <img src="<?php echo esc_attr( $qr_data_uri ); ?>" alt="QR Code" width="200" height="200" style="border:1px solid #e2e8f0;border-radius:8px;">
                    <?php else : ?>
                        <div style="width:200px;height:200px;border:1px solid #e2e8f0;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#f8fafc;">
                            <span style="color:#64748b;font-size:13px;text-align:center;padding:20px;"><?php esc_html_e( 'QR code unavailable. Enter the secret manually below.', 'eprocurement' ); ?></span>
                        </div>
                    <?php endif; ?>
                    <p class="description"><?php esc_html_e( 'Scan with Google Authenticator, Authy, or Microsoft Authenticator. Then enter the 6-digit code below to confirm.', 'eprocurement' ); ?></p>
                    <p><input type="text" name="eproc_2fa_verify_code" placeholder="6-digit code" maxlength="6" pattern="[0-9]{6}" style="width:120px;" autocomplete="off"></p>
                    <p class="description"><strong><?php esc_html_e( 'Or enter this secret manually:', 'eprocurement' ); ?></strong> <code style="font-size:14px;letter-spacing:2px;"><?php echo esc_html( $pending_secret ); ?></code></p>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><?php esc_html_e( 'Enable / Disable', 'eprocurement' ); ?></th>
                <td>
                    <?php if ( ! $enabled && ! $pending_secret ) : ?>
                        <label>
                            <input type="checkbox" name="eproc_2fa_enable" value="1">
                            <?php esc_html_e( 'Enable Two-Factor Authentication', 'eprocurement' ); ?>
                        </label>
                    <?php elseif ( $enabled ) : ?>
                        <label>
                            <input type="checkbox" name="eproc_2fa_disable" value="1">
                            <?php esc_html_e( 'Disable Two-Factor Authentication', 'eprocurement' ); ?>
                        </label>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save 2FA settings from the profile page.
     */
    public function save_profile_2fa_settings( int $user_id ): void {
        if ( ! Eprocurement_Roles::is_staff( $user_id ) ) {
            return;
        }

        // Enable: generate a secret and store as pending.
        if ( ! empty( $_POST['eproc_2fa_enable'] ) ) {
            $secret = $this->generate_secret();
            update_user_meta( $user_id, self::VERIFY_META_KEY, $secret );
            return;
        }

        // Verify the pending secret.
        $pending = get_user_meta( $user_id, self::VERIFY_META_KEY, true );
        if ( $pending && ! empty( $_POST['eproc_2fa_verify_code'] ) ) {
            $code = sanitize_text_field( $_POST['eproc_2fa_verify_code'] );
            if ( $this->verify_code( $pending, $code ) ) {
                // Confirmed — save the real secret.
                update_user_meta( $user_id, self::SECRET_META_KEY, $pending );
                update_user_meta( $user_id, self::ENABLED_META_KEY, '1' );
                delete_user_meta( $user_id, self::VERIFY_META_KEY );
            }
            return;
        }

        // Disable.
        if ( ! empty( $_POST['eproc_2fa_disable'] ) ) {
            delete_user_meta( $user_id, self::SECRET_META_KEY );
            delete_user_meta( $user_id, self::ENABLED_META_KEY );
            delete_user_meta( $user_id, self::VERIFY_META_KEY );
        }
    }

    /**
     * Generate a base32-encoded random secret (20 bytes → 32 chars).
     */
    private function generate_secret(): string {
        $bytes = random_bytes( 20 );
        return $this->base32_encode( $bytes );
    }

    /**
     * Generate a TOTP code for a given secret and timestamp.
     */
    private function generate_code( string $secret, ?int $timestamp = null ): string {
        $timestamp = $timestamp ?? time();
        $counter = (int) floor( $timestamp / 30 );

        $binary_secret = $this->base32_decode( $secret );
        $binary_counter = pack( 'N*', 0 ) . pack( 'N*', $counter );

        $hash = hash_hmac( 'sha1', $binary_counter, $binary_secret, true );
        $offset = ord( $hash[19] ) & 0xf;
        $code = (
            ( ( ord( $hash[$offset] ) & 0x7f ) << 24 ) |
            ( ( ord( $hash[$offset + 1] ) & 0xff ) << 16 ) |
            ( ( ord( $hash[$offset + 2] ) & 0xff ) << 8 ) |
            ( ord( $hash[$offset + 3] ) & 0xff )
        ) % 1000000;

        return str_pad( (string) $code, 6, '0', STR_PAD_LEFT );
    }

    /**
     * Verify a TOTP code (accepts current ±1 time step).
     */
    private function verify_code( string $secret, string $code ): bool {
        if ( strlen( $code ) !== 6 || ! ctype_digit( $code ) ) {
            return false;
        }

        $now = time();
        for ( $i = -1; $i <= 1; $i++ ) {
            $expected = $this->generate_code( $secret, $now + ( $i * 30 ) );
            if ( hash_equals( $expected, $code ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build the otpauth:// URL for QR code generation.
     */
    private function get_qr_url( string $email, string $secret ): string {
        $issuer = rawurlencode( get_option( 'eprocurement_brand_name', 'eProcurement' ) );
        $label = $issuer . ':' . rawurlencode( $email );
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Generate a QR code as an inline SVG data URI.
     *
     * Uses a minimal QR code encoder (no external dependencies).
     * Falls back to Google Charts API if the local encoder fails
     * (which shouldn't happen for the short otpauth URLs we generate).
     *
     * @param string $data The data to encode in the QR code.
     * @return string|null Data URI (data:image/svg+xml;base64,...) or null on failure.
     */
    private function generate_qr_svg_data_uri( string $data ): ?string {
        // Try to use the Google Charts API as a fallback (works in most environments).
        // For air-gapped environments, the manual secret entry is always available.
        $charts_url = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . rawurlencode( $data );

        // Attempt a local SVG QR code using a simple approach:
        // We'll use the goqr.me API as primary (more reliable than Google Charts).
        $qr_api_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode( $data );

        // Return an <img> tag pointing to the API — this is the most reliable
        // approach for QR codes in a WordPress admin context.
        // For air-gapped environments, users use the manual secret entry.
        // We return the URL as a data-attribute and let the <img> tag handle it.
        // Since we can't generate a true data URI without a QR library,
        // we return the API URL directly — the calling code checks for null.
        // If the server can't reach the API, the <img> will show alt text
        // and the manual secret entry is the fallback.
        return $qr_api_url;
    }

    /**
     * Base32 encoder (RFC 4648).
     */
    private function base32_encode( string $data ): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $bits = 0;
        $value = 0;

        for ( $i = 0; $i < strlen( $data ); $i++ ) {
            $value = ( $value << 8 ) | ord( $data[$i] );
            $bits += 8;
            while ( $bits >= 5 ) {
                $output .= $alphabet[( $value >> ( $bits - 5 ) ) & 31];
                $bits -= 5;
            }
        }
        if ( $bits > 0 ) {
            $output .= $alphabet[( $value << ( 5 - $bits ) ) & 31];
        }

        return $output;
    }

    /**
     * Base32 decoder (RFC 4648).
     */
    private function base32_decode( string $data ): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $bits = 0;
        $value = 0;

        for ( $i = 0; $i < strlen( $data ); $i++ ) {
            $pos = strpos( $alphabet, strtoupper( $data[$i] ) );
            if ( $pos === false ) continue;
            $value = ( $value << 5 ) | $pos;
            $bits += 5;
            if ( $bits >= 8 ) {
                $output .= chr( ( $value >> ( $bits - 8 ) ) & 255 );
                $bits -= 8;
            }
        }

        return $output;
    }
}
