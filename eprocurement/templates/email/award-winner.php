<?php
/**
 * Email template: Award notification (winner).
 *
 * Sent to the winning bidder when the tender is awarded.
 *
 * Variables:
 * - $bidder_name  (string) Recipient bidder name.
 * - $company_name (string) Winning company name.
 * - $bid_number   (string) Bid number.
 * - $bid_title    (string) Bid title.
 * - $award_amount (float|null) Contract value (null if not disclosed).
 * - $award_date   (string) MySQL datetime of award.
 * - $award_notes  (string|null) Public award notes.
 * - $tender_url   (string) Tender detail URL.
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_subject = sprintf(
    /* translators: %s: bid number */
    __( 'Congratulations — tender %s awarded to you', 'eprocurement' ),
    $bid_number
);
$preview_text = sprintf(
    /* translators: %s: bid title */
    __( 'Your bid for %s has been selected as the winning submission.', 'eprocurement' ),
    $bid_title
);

require __DIR__ . '/_header.php';
?>
                        <h1 style="margin:0 0 16px;font-size:26px;font-weight:800;color:#15803d;letter-spacing:-0.02em;text-align:center;">
                            <?php esc_html_e( '🎉 Congratulations!', 'eprocurement' ); ?>
                        </h1>
                        <p style="margin:0 0 24px;text-align:center;font-size:16px;color:#334155;">
                            <?php
                            /* translators: %s: bidder name */
                            echo esc_html( sprintf( __( 'Dear %s,', 'eprocurement' ), $bidder_name ) );
                            ?>
                        </p>
                        <p style="margin:0 0 24px;">
                            <?php
                            echo wp_kses(
                                sprintf(
                                    /* translators: %s: company name */
                                    __( 'We are pleased to inform you that the contract for the following tender has been <strong>awarded to %s</strong>.', 'eprocurement' ),
                                    esc_html( $company_name ?: $bidder_name )
                                ),
                                [ 'strong' => [] ]
                            );
                            ?>
                        </p>

                        <!-- Tender Details -->
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;margin:0 0 24px;">
                            <tr>
                                <td style="padding:20px 24px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                        <tr>
                                            <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#15803d;font-weight:600;padding-bottom:4px;">
                                                <?php esc_html_e( 'Bid Number', 'eprocurement' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:16px;font-weight:700;color:#1e293b;padding-bottom:14px;">
                                                <?php echo esc_html( $bid_number ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#15803d;font-weight:600;padding-bottom:4px;">
                                                <?php esc_html_e( 'Title', 'eprocurement' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:15px;color:#334155;padding-bottom:14px;">
                                                <?php echo esc_html( $bid_title ); ?>
                                            </td>
                                        </tr>
                                        <?php if ( $award_amount !== null && $award_amount > 0 ) : ?>
                                            <tr>
                                                <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#15803d;font-weight:600;padding-bottom:4px;">
                                                    <?php esc_html_e( 'Contract Value', 'eprocurement' ); ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:18px;font-weight:700;color:#15803d;padding-bottom:14px;">
                                                    <?php
                                                    /* translators: %s: amount */
                                                    echo esc_html( sprintf( __( '%s (incl. VAT where applicable)', 'eprocurement' ), number_format_i18n( $award_amount, 2 ) ) );
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td style="font-size:11px;text-transform:uppercase;letter-spacing:0.05em;color:#15803d;font-weight:600;padding-bottom:4px;">
                                                <?php esc_html_e( 'Award Date', 'eprocurement' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:15px;color:#334155;">
                                                <?php echo esc_html( wp_date( 'j F Y', strtotime( $award_date ) ) ); ?>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <?php if ( ! empty( $award_notes ) ) : ?>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8fafc;border-left:3px solid #15803d;border-radius:0 8px 8px 0;margin:0 0 24px;">
                                <tr>
                                    <td style="padding:16px 20px;color:#334155;font-size:14px;line-height:1.65;">
                                        <strong style="color:#15803d;display:block;margin-bottom:6px;"><?php esc_html_e( 'Award notes from the procurement team:', 'eprocurement' ); ?></strong>
                                        <?php echo esc_html( $award_notes ); ?>
                                    </td>
                                </tr>
                            </table>
                        <?php endif; ?>

                        <p style="margin:0 0 16px;">
                            <?php esc_html_e( 'The procurement team will contact you shortly to discuss next steps and contract finalisation.', 'eprocurement' ); ?>
                        </p>

                        <!-- CTA Button -->
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:24px 0;">
                            <tr>
                                <td align="center">
                                    <a href="<?php echo esc_url( $tender_url ); ?>" class="button" style="display:inline-block;padding:14px 32px;background:#8b1a2b;color:#fff !important;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;">
                                        <?php esc_html_e( 'View tender details', 'eprocurement' ); ?>
                                    </a>
                                </td>
                            </tr>
                        </table>
<?php
require __DIR__ . '/_footer.php';
