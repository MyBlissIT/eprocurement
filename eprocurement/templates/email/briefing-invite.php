<?php
/**
 * Email template: Briefing invite.
 *
 * Sent to briefing attendees with their unique submission link.
 *
 * Variables available:
 * - $bidder_name   (string) Company name or email.
 * - $bid_number    (string) Bid number.
 * - $bid_title     (string) Bid title.
 * - $briefing_date (string) Formatted briefing date.
 * - $submit_url    (string) Token-based submission link.
 * - $register_url  (string) Registration page URL.
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_subject = sprintf(
    /* translators: %s: bid number */
    __( 'Briefing invitation: %s', 'eprocurement' ),
    $bid_number
);
$preview_text = sprintf(
    /* translators: %s: bid title */
    __( 'You are invited to submit a bid for %s', 'eprocurement' ),
    $bid_title
);

require __DIR__ . '/_header.php';
?>
                        <h1 style="margin:0 0 16px;font-size:24px;font-weight:700;color:#1e293b;letter-spacing:-0.02em;">
                            <?php echo esc_html__( 'Briefing invitation', 'eprocurement' ); ?>
                        </h1>
                        <p style="margin:0 0 16px;">
                            <?php
                            /* translators: %s: bidder name */
                            echo esc_html( sprintf( __( 'Hi %s,', 'eprocurement' ), $bidder_name ) );
                            ?>
                        </p>
                        <p style="margin:0 0 24px;">
                            <?php echo esc_html__( 'You are invited to submit a bid for the following tender:', 'eprocurement' ); ?>
                        </p>

                        <!-- Tender Details Card -->
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin:0 0 24px;">
                            <tr>
                                <td style="padding:20px 24px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                        <tr>
                                            <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;padding-bottom:4px;">
                                                <?php echo esc_html__( 'Bid Number', 'eprocurement' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:16px;font-weight:700;color:#1e293b;padding-bottom:14px;">
                                                <?php echo esc_html( $bid_number ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;padding-bottom:4px;">
                                                <?php echo esc_html__( 'Title', 'eprocurement' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:15px;color:#334155;padding-bottom:14px;">
                                                <?php echo esc_html( $bid_title ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;padding-bottom:4px;">
                                                <?php echo esc_html__( 'Briefing Date', 'eprocurement' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:15px;color:#334155;">
                                                <?php echo esc_html( $briefing_date ); ?>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- CTA Button -->
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:24px 0;">
                            <tr>
                                <td align="center">
                                    <a href="<?php echo esc_url( $submit_url ); ?>" class="button" style="display:inline-block;padding:14px 32px;background:#8b1a2b;color:#fff !important;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;">
                                        <?php echo esc_html__( 'Submit your bid', 'eprocurement' ); ?>
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 16px;">
                            <?php echo esc_html__( "Don't have an account yet?", 'eprocurement' ); ?>
                            <a href="<?php echo esc_url( $register_url ); ?>" style="color:#8b1a2b;font-weight:600;">
                                <?php echo esc_html__( 'Register here first', 'eprocurement' ); ?>
                            </a>
                        </p>

                        <hr style="margin:24px 0;border:none;border-top:1px solid #e2e8f0;">

                        <p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">
                            <strong style="color:#475569;"><?php echo esc_html__( 'Accepted file types:', 'eprocurement' ); ?></strong>
                            <?php echo esc_html__( 'PDF, Excel (XLS, XLSX), CSV — max 10 MB.', 'eprocurement' ); ?>
                            <br>
                            <strong style="color:#475569;"><?php echo esc_html__( 'Note:', 'eprocurement' ); ?></strong>
                            <?php echo esc_html__( 'Only one submission per bidder is allowed. You may cancel and resubmit before the closing date.', 'eprocurement' ); ?>
                        </p>
<?php
require __DIR__ . '/_footer.php';
