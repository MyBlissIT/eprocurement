<?php
/**
 * Email template: Shared header.
 *
 * Used by all HTML emails sent by the eProcurement plugin.
 * Branding (logo, name, colors) is pulled from plugin options.
 *
 * Variables available:
 * - $email_subject (string) Email subject line.
 * - $preview_text  (string) Optional preview text shown in inbox preview.
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$brand_name  = get_option( 'eprocurement_brand_name', 'eProcurement' );
$brand_url   = get_option( 'eprocurement_brand_url', home_url() );
$brand_logo  = get_option( 'eprocurement_brand_logo', '' );
$support_email = get_option( 'eprocurement_support_email', '' );
$primary_color = '#8b1a2b';
$colors = get_option( 'eprocurement_brand_colors', [] );
if ( is_array( $colors ) && ! empty( $colors['primary'] ) ) {
    $primary_color = sanitize_hex_color( $colors['primary'] ) ?: '#8b1a2b';
}

$preview_text = $preview_text ?? '';
?>
<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title><?php echo esc_html( $email_subject ?? $brand_name ); ?></title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body, table, td, h1, h2, h3, h4, p, a { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        body { margin: 0; padding: 0; background: #f1f5f9; -webkit-font-smoothing: antialiased; }
        a { color: <?php echo esc_attr( $primary_color ); ?>; text-decoration: none; }
        .button { display: inline-block; padding: 14px 32px; background: <?php echo esc_attr( $primary_color ); ?>; color: #fff !important; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; letter-spacing: 0.01em; mso-padding-alt: 0; }
        .button:hover { background: <?php echo esc_attr( $primary_color ); ?>; opacity: 0.92; }
    </style>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;-webkit-font-smoothing:antialiased;">

<!-- Preheader (hidden preview text) -->
<?php if ( $preview_text ) : ?>
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;font-size:1px;line-height:1px;">
    <?php echo esc_html( $preview_text ); ?>
</div>
<?php endif; ?>

<!-- Wrapper -->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f1f5f9;">
    <tr>
        <td align="center" style="padding:32px 16px;">

            <!-- Container -->
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(15,23,42,0.06);">

                <!-- Header -->
                <tr>
                    <td style="padding:28px 40px 24px;background:linear-gradient(135deg,<?php echo esc_attr( $primary_color ); ?> 0%,<?php echo esc_attr( $primary_color ); ?>CC 100%);border-radius:14px 14px 0 0;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="color:#fff;font-size:18px;font-weight:700;letter-spacing:-0.01em;">
                                    <?php if ( $brand_logo ) : ?>
                                        <img src="<?php echo esc_url( $brand_logo ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" style="height:32px;width:auto;max-width:200px;border:0;display:block;">
                                    <?php else : ?>
                                        <?php echo esc_html( $brand_name ); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:36px 40px 28px;color:#1e293b;font-size:15px;line-height:1.65;">
