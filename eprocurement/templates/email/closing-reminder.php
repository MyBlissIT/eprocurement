<?php
/**
 * Email template: Closing reminder.
 *
 * Sent to bidders 48h and 24h before a tender closes.
 *
 * Variables:
 * - $bid_number       (string) Bid number.
 * - $bid_title        (string) Bid title.
 * - $hours_remaining  (int)    Hours until closing (48 or 24).
 * - $closing_date     (string) MySQL datetime.
 * - $tender_url       (string) Tender detail URL.
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_subject = sprintf(
    /* translators: %d: hours remaining */
    __( '%d hours left to submit your bid', 'eprocurement' ),
    $hours_remaining
);
$preview_text = sprintf(
    /* translators: %s: bid number */
    __( 'Tender %s closes soon — submit your bid before the deadline.', 'eprocurement' ),
    $bid_number
);

require __DIR__ . '/_header.php';

$urgency_class = $hours_remaining <= 24 ? 'color:#b91c1c;' : 'color:#b45309;';
?>
                        <h1 style="margin:0 0 16px;font-size:24px;font-weight:700;color:#1e293b;letter-spacing:-0.02em;">
                            <?php
                            /* translators: %d: hours remaining */
                            echo esc_html( sprintf( __( 'Closing in %d hours', 'eprocurement' ), $hours_remaining ) );
                            ?>
                        </h1>
                        <p style="margin:0 0 16px;">
                            <?php esc_html_e( 'This is a reminder that the following tender will close soon:', 'eprocurement' ); ?>
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
                                            <td style="font-size:15px;color:#334155;padding-bottom:14px;">
                                                <?php echo esc_html( $bid_title ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;font-weight:600;padding-bottom:4px;">
                                                <?php esc_html_e( 'Closing Date & Time', 'eprocurement' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:16px;font-weight:700;<?php echo esc_attr( $urgency_class ); ?>">
                                                <?php
                                                echo esc_html(
                                                    $closing_date
                                                        ? wp_date( 'l, j F Y \a\t H:i', strtotime( $closing_date ) )
                                                        : __( 'TBC', 'eprocurement' )
                                                );
                                                ?>
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
                                    <a href="<?php echo esc_url( $tender_url ); ?>" class="button" style="display:inline-block;padding:14px 32px;background:#8b1a2b;color:#fff !important;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;">
                                        <?php esc_html_e( 'View tender & submit bid', 'eprocurement' ); ?>
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0;color:#64748b;font-size:13px;">
                            <?php esc_html_e( 'If you have already submitted your bid, you can safely ignore this email.', 'eprocurement' ); ?>
                        </p>
<?php
require __DIR__ . '/_footer.php';
