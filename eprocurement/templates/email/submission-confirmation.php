<?php
/**
 * Email template: Submission confirmation.
 *
 * Sent to the bidder when they submit a bid. Confirms receipt
 * and provides a link to view the submission in the dashboard.
 *
 * Variables:
 * - $bidder_name   (string) Bidder display name.
 * - $bid_number    (string) Bid number.
 * - $bid_title     (string) Bid title.
 * - $file_name     (string) Uploaded file name.
 * - $submitted_at  (string) Submission timestamp.
 * - $is_late       (bool)   Whether this was a late submission.
 * - $dashboard_url (string) URL to bidder's submissions tab.
 *
 * @package Eprocurement
 * @since   2.16.3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_subject = sprintf(
    /* translators: %s: bid number */
    __( 'Submission confirmed: %s', 'eprocurement' ),
    $bid_number
);
$preview_text = __( 'Your bid submission has been received.', 'eprocurement' );

require __DIR__ . '/_header.php';
?>
                        <h1 style="margin:0 0 16px;font-size:24px;font-weight:700;color:#1e293b;letter-spacing:-0.02em;">
                            <?php esc_html_e( 'Submission received', 'eprocurement' ); ?>
                        </h1>
                        <p style="margin:0 0 16px;">
                            <?php
                            /* translators: %s: bidder name */
                            echo esc_html( sprintf( __( 'Dear %s,', 'eprocurement' ), $bidder_name ) );
                            ?>
                        </p>
                        <p style="margin:0 0 24px;">
                            <?php esc_html_e( 'Your bid submission has been received successfully. The details are below:', 'eprocurement' ); ?>
                        </p>

                        <!-- Submission Details Card -->
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin:0 0 24px;">
                            <tr>
                                <td style="padding:20px 24px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                        <tr>
                                            <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;padding-bottom:4px;">
                                                <?php esc_html_e( 'Bid Number', 'eprocurement' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:16px;font-weight:700;color:#1e293b;padding-bottom:14px;">
                                                <?php echo esc_html( $bid_number ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;padding-bottom:4px;">
                                                <?php esc_html_e( 'Title', 'eprocurement' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:15px;color:#334155;padding-bottom:14px;">
                                                <?php echo esc_html( $bid_title ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;padding-bottom:4px;">
                                                <?php esc_html_e( 'File Submitted', 'eprocurement' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:14px;color:#334155;padding-bottom:14px;">
                                                <?php echo esc_html( $file_name ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;padding-bottom:4px;">
                                                <?php esc_html_e( 'Submitted At', 'eprocurement' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:14px;color:#334155;padding-bottom:14px;">
                                                <?php echo esc_html( wp_date( 'j F Y, H:i', strtotime( $submitted_at ) ) ); ?>
                                                <?php if ( $is_late ) : ?>
                                                    <span style="display:inline-block;padding:2px 8px;background:#fef3c7;color:#b45309;border-radius:6px;font-size:11px;font-weight:600;margin-left:8px;">
                                                        <?php esc_html_e( 'LATE', 'eprocurement' ); ?>
                                                    </span>
                                                <?php endif; ?>
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
                                    <a href="<?php echo esc_url( $dashboard_url ); ?>" class="button" style="display:inline-block;padding:14px 32px;background:#8b1a2b;color:#fff !important;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;">
                                        <?php esc_html_e( 'View my submissions', 'eprocurement' ); ?>
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0;color:#64748b;font-size:13px;">
                            <?php esc_html_e( 'Please keep this email for your records. If you did not submit this bid, please contact the procurement team immediately.', 'eprocurement' ); ?>
                        </p>
<?php
require __DIR__ . '/_footer.php';
