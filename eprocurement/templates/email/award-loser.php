<?php
/**
 * Email template: Award notification (non-winner).
 *
 * Sent to bidders whose submission was not selected.
 *
 * Variables:
 * - $bidder_name (string) Recipient bidder name.
 * - $bid_number  (string) Bid number.
 * - $bid_title   (string) Bid title.
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_subject = sprintf(
    /* translators: %s: bid number */
    __( 'Tender award notice: %s', 'eprocurement' ),
    $bid_number
);
$preview_text = __( 'Thank you for your submission. The contract has been awarded.', 'eprocurement' );

require __DIR__ . '/_header.php';
?>
                        <h1 style="margin:0 0 16px;font-size:24px;font-weight:700;color:#1e293b;letter-spacing:-0.02em;">
                            <?php esc_html_e( 'Tender award notice', 'eprocurement' ); ?>
                        </h1>
                        <p style="margin:0 0 16px;">
                            <?php
                            /* translators: %s: bidder name */
                            echo esc_html( sprintf( __( 'Dear %s,', 'eprocurement' ), $bidder_name ) );
                            ?>
                        </p>
                        <p style="margin:0 0 24px;">
                            <?php esc_html_e( 'Thank you for taking the time to prepare and submit a bid for the following tender. We regret to inform you that the contract has been awarded to another bidder.', 'eprocurement' ); ?>
                        </p>

                        <!-- Tender Details -->
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
                                            <td style="font-size:15px;color:#334155;">
                                                <?php echo esc_html( $bid_title ); ?>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 16px;">
                            <?php esc_html_e( 'We sincerely appreciate the time and effort you invested in preparing your bid. The quality of submissions received was high, and the decision was not an easy one.', 'eprocurement' ); ?>
                        </p>
                        <p style="margin:0 0 16px;">
                            <?php esc_html_e( 'We encourage you to participate in future tenders. New opportunities are published regularly on our portal.', 'eprocurement' ); ?>
                        </p>

                        <hr style="margin:24px 0;border:none;border-top:1px solid #e2e8f0;">

                        <p style="margin:0;color:#64748b;font-size:13px;">
                            <?php esc_html_e( 'If you would like feedback on your submission, please contact the SCM contact person listed on the tender page.', 'eprocurement' ); ?>
                        </p>
<?php
require __DIR__ . '/_footer.php';
