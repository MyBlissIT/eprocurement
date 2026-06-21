<?php
/**
 * Email template: Email verification.
 *
 * Sent to new bidders after registration. Contains a verification link
 * that, when clicked, marks the bidder's email as verified.
 *
 * Variables available:
 * - $name        (string) Recipient display name.
 * - $verify_url  (string) Verification URL with token.
 * - $expires_in  (int)    Hours until the token expires (default 48).
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_subject = __( 'Verify your email address', 'eprocurement' );
$preview_text  = __( 'Welcome to eProcurement — please verify your email to get started.', 'eprocurement' );
$expires_in    = $expires_in ?? 48;

require __DIR__ . '/_header.php';
?>
                        <h1 style="margin:0 0 16px;font-size:24px;font-weight:700;color:#1e293b;letter-spacing:-0.02em;">
                            <?php echo esc_html__( 'Welcome to eProcurement', 'eprocurement' ); ?>
                        </h1>
                        <p style="margin:0 0 16px;">
                            <?php
                            /* translators: %s: user's name */
                            echo esc_html( sprintf( __( 'Hi %s,', 'eprocurement' ), $name ) );
                            ?>
                        </p>
                        <p style="margin:0 0 16px;">
                            <?php echo esc_html__( 'Thank you for registering. To complete your account setup and start submitting queries and bids, please verify your email address:', 'eprocurement' ); ?>
                        </p>

                        <!-- CTA Button -->
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:24px 0;">
                            <tr>
                                <td align="center">
                                    <a href="<?php echo esc_url( $verify_url ); ?>" class="button" style="display:inline-block;padding:14px 32px;background:#8b1a2b;color:#fff !important;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;">
                                        <?php echo esc_html__( 'Verify my email', 'eprocurement' ); ?>
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 16px;color:#64748b;font-size:13px;">
                            <?php
                            /* translators: %d: hours until expiry */
                            echo esc_html( sprintf( __( 'This link will expire in %d hours. If you did not register for an account, you can safely ignore this email.', 'eprocurement' ), $expires_in ) );
                            ?>
                        </p>

                        <hr style="margin:24px 0;border:none;border-top:1px solid #e2e8f0;">

                        <p style="margin:0;color:#64748b;font-size:12px;">
                            <?php echo esc_html__( 'If the button above does not work, copy and paste this link into your browser:', 'eprocurement' ); ?>
                            <br>
                            <a href="<?php echo esc_url( $verify_url ); ?>" style="color:#64748b;word-break:break-all;"><?php echo esc_html( $verify_url ); ?></a>
                        </p>
<?php
require __DIR__ . '/_footer.php';
