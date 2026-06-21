<?php
/**
 * Email template: New query notification.
 *
 * Sent to contact persons when a bidder submits a new query.
 *
 * Variables available:
 * - $contact_name (string) Recipient contact name.
 * - $bidder_name  (string) Bidder's name / company.
 * - $bid_number   (string) Bid number.
 * - $bid_title    (string) Bid title.
 * - $message      (string) Query message body.
 * - $visibility   (string) 'public' or 'private'.
 * - $admin_url    (string) URL to the admin thread view.
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_subject = sprintf(
    /* translators: %s: bid number */
    __( 'New query: %s', 'eprocurement' ),
    $bid_number
);
$preview_text = sprintf(
    /* translators: %s: bidder name */
    __( '%s submitted a query that needs your response.', 'eprocurement' ),
    $bidder_name
);

require __DIR__ . '/_header.php';
?>
                        <h1 style="margin:0 0 16px;font-size:24px;font-weight:700;color:#1e293b;letter-spacing:-0.02em;">
                            <?php echo esc_html__( 'New query received', 'eprocurement' ); ?>
                        </h1>
                        <p style="margin:0 0 16px;">
                            <?php
                            /* translators: %s: contact name */
                            echo esc_html( sprintf( __( 'Hi %s,', 'eprocurement' ), $contact_name ) );
                            ?>
                        </p>
                        <p style="margin:0 0 24px;">
                            <?php
                            echo wp_kses(
                                sprintf(
                                    /* translators: 1: bidder name, 2: bid number, 3: bid title */
                                    __( '<strong>%1$s</strong> submitted a query regarding <strong>%2$s — %3$s</strong>.', 'eprocurement' ),
                                    esc_html( $bidder_name ),
                                    esc_html( $bid_number ),
                                    esc_html( $bid_title )
                                ),
                                [ 'strong' => [] ]
                            );
                            ?>
                        </p>

                        <?php if ( $visibility === 'public' ) : ?>
                        <p style="margin:0 0 16px;">
                            <span style="display:inline-block;padding:3px 10px;background:#dbeafe;color:#1d4ed8;border-radius:6px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">
                                <?php echo esc_html__( 'Public', 'eprocurement' ); ?>
                            </span>
                            <span style="color:#64748b;font-size:13px;margin-left:6px;">
                                <?php echo esc_html__( 'Visible to all bidders on the tender detail page.', 'eprocurement' ); ?>
                            </span>
                        </p>
                        <?php else : ?>
                        <p style="margin:0 0 16px;">
                            <span style="display:inline-block;padding:3px 10px;background:#f3e8ff;color:#7c3aed;border-radius:6px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">
                                <?php echo esc_html__( 'Private', 'eprocurement' ); ?>
                            </span>
                            <span style="color:#64748b;font-size:13px;margin-left:6px;">
                                <?php echo esc_html__( 'Visible only to you and the bidder.', 'eprocurement' ); ?>
                            </span>
                        </p>
                        <?php endif; ?>

                        <!-- Message Body -->
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8fafc;border-left:3px solid #8b1a2b;border-radius:0 8px 8px 0;margin:0 0 24px;">
                            <tr>
                                <td style="padding:16px 20px;color:#334155;font-size:14px;line-height:1.65;font-style:italic;">
                                    "<?php echo esc_html( $message ); ?>"
                                </td>
                            </tr>
                        </table>

                        <!-- CTA Button -->
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:24px 0;">
                            <tr>
                                <td align="center">
                                    <a href="<?php echo esc_url( $admin_url ); ?>" class="button" style="display:inline-block;padding:14px 32px;background:#8b1a2b;color:#fff !important;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;">
                                        <?php echo esc_html__( 'Reply to query', 'eprocurement' ); ?>
                                    </a>
                                </td>
                            </tr>
                        </table>
<?php
require __DIR__ . '/_footer.php';
