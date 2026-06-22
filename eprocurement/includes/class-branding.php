<?php
/**
 * Dynamic tenant branding support.
 *
 * Provides static methods that return branding values from WordPress options,
 * with sensible MyBliss Technologies defaults. All values are per-site
 * (via get_option) so multisite installs can override per tenant.
 *
 * @package Eprocurement
 * @since   2.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Branding {

    /* ------------------------------------------------------------------
     *  Brand Identity
     * ---------------------------------------------------------------- */

    /**
     * Organisation / tenant name.
     */
    public static function brand_name(): string {
        return get_option( 'eprocurement_brand_name', 'MyBliss Technologies' );
    }

    /**
     * Organisation website URL.
     */
    public static function brand_url(): string {
        return get_option( 'eprocurement_brand_url', 'https://www.myblisstech.com' );
    }

    /**
     * Support email address.
     */
    public static function support_email(): string {
        return get_option( 'eprocurement_support_email', 'support@myblisstech.com' );
    }

    /**
     * Brand logo URL.
     *
     * Accepts an attachment ID (numeric) or a full URL string.
     * Falls back to the MU-plugin logo, then to a plugin-bundled default.
     */
    public static function logo_url(): string {
        $stored = get_option( 'eprocurement_brand_logo', '' );

        // Attachment ID — resolve to URL.
        if ( is_numeric( $stored ) && (int) $stored > 0 ) {
            $url = wp_get_attachment_url( (int) $stored );
            if ( $url ) {
                return $url;
            }
        }

        // Full URL stored directly.
        if ( ! empty( $stored ) && filter_var( $stored, FILTER_VALIDATE_URL ) ) {
            return $stored;
        }

        // Fallback: MU-plugin logo.
        $mu_logo = ABSPATH . 'wp-content/mu-plugins/sme-assets/mybliss-logo.png';
        if ( file_exists( $mu_logo ) ) {
            return content_url( 'mu-plugins/sme-assets/mybliss-logo.png' );
        }

        // Fallback: plugin assets directory.
        return EPROC_PLUGIN_URL . 'assets/logo.png';
    }

    /**
     * Short tagline / system subtitle.
     */
    public static function tagline(): string {
        return get_option( 'eprocurement_brand_tagline', 'eProcurement System' );
    }

    /* ------------------------------------------------------------------
     *  Colors
     * ---------------------------------------------------------------- */

    /**
     * Default color palette.
     */
    private static function default_colors(): array {
        return [
            'primary'         => '#8b1a2b',
            'primary_hover'   => '#6d1522',
            'secondary'       => '#1a1a5e',
            'secondary_light' => '#2d2d7a',
            'success'         => '#1a7a3f',
            'danger'          => '#e74c3c',
            'warning'         => '#f39c12',
        ];
    }

    /**
     * Retrieve saved color palette merged over defaults.
     */
    private static function resolved_colors(): array {
        $saved = get_option( 'eprocurement_brand_colors', '' );

        if ( is_string( $saved ) && $saved !== '' ) {
            $decoded = json_decode( $saved, true );
            if ( is_array( $decoded ) ) {
                return array_merge( self::default_colors(), $decoded );
            }
        }

        if ( is_array( $saved ) ) {
            return array_merge( self::default_colors(), $saved );
        }

        return self::default_colors();
    }

    /**
     * Primary brand color.
     */
    public static function color_primary(): string {
        return self::resolved_colors()['primary'];
    }

    /**
     * Primary hover / darker variant.
     */
    public static function color_primary_hover(): string {
        return self::resolved_colors()['primary_hover'];
    }

    /**
     * Secondary color (sidebar background, etc.).
     */
    public static function color_secondary(): string {
        return self::resolved_colors()['secondary'];
    }

    /**
     * Lighter secondary variant.
     */
    public static function color_secondary_light(): string {
        return self::resolved_colors()['secondary_light'];
    }

    /**
     * Get all colors as an associative array mapping
     * CSS custom-property names (without leading `--`) to values.
     *
     * Example: [ 'eproc-primary' => '#8b1a2b', ... ]
     */
    public static function get_colors(): array {
        $colors = self::resolved_colors();

        return [
            'eproc-primary'         => $colors['primary'],
            'eproc-primary-hover'   => $colors['primary_hover'],
            'eproc-secondary'       => $colors['secondary'],
            'eproc-secondary-light' => $colors['secondary_light'],
            'eproc-success'         => $colors['success'],
            'eproc-danger'          => $colors['danger'],
            'eproc-warning'         => $colors['warning'],
        ];
    }

    /**
     * Get every branding value as a flat array.
     *
     * Useful for wp_localize_script() or REST API responses.
     */
    public static function get_all(): array {
        return [
            'brand_name'    => self::brand_name(),
            'brand_url'     => self::brand_url(),
            'support_email' => self::support_email(),
            'logo_url'      => self::logo_url(),
            'tagline'       => self::tagline(),
            'login_title'   => self::login_title(),
            'colors'        => self::get_colors(),
        ];
    }

    /* ------------------------------------------------------------------
     *  Inline CSS Override
     * ---------------------------------------------------------------- */

    /**
     * Return a <style> block that overrides CSS custom properties
     * on .eproc-wrap and .eproc-admin-shell when custom colors are set.
     *
     * Safe to call even when no custom colors are saved — it simply
     * re-declares the defaults, which has no visual effect.
     */
    public static function inline_css(): string {
        $colors = self::get_colors();

        $declarations = '';
        foreach ( $colors as $prop => $value ) {
            $safe_prop  = esc_attr( $prop );
            $safe_value = esc_attr( $value );
            $declarations .= "--{$safe_prop}:{$safe_value};";
        }

        if ( $declarations === '' ) {
            return '';
        }

        return '<style id="eproc-branding-overrides">'
             . ".eproc-wrap,.eproc-admin-shell{{$declarations}}"
             . '</style>';
    }

    /* ------------------------------------------------------------------
     *  Login Page
     * ---------------------------------------------------------------- */

    /**
     * Login page title / heading.
     */
    public static function login_title(): string {
        return get_option( 'eprocurement_login_title', 'Client Portal' );
    }

    /**
     * Login page background gradient CSS value.
     *
     * Returns a full `background` shorthand suitable for inline style
     * or CSS declaration. Uses the secondary color family by default.
     */
    public static function login_bg_gradient(): string {
        $colors = self::resolved_colors();
        $from   = $colors['secondary'];
        $to     = $colors['secondary_light'];

        return "linear-gradient(135deg, {$from} 0%, {$to} 100%)";
    }
}
