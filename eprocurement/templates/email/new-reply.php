<?php
/**
 * Email template: New reply notification.
 *
 * Sent to bidders when staff replies to their query.
 *
 * Variables available:
 * - $bidder_name  (string) Recipient bidder name.
 * - $responder    (string) Staff member's name.
 * - $bid_number   (string) Bid number.
 * - $bid_title    (string) Bid title.
 * - $reply        (string) Reply message body.
 * - $dashboard_url (string) URL to the bidder's dashboard thread view.
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$email_subject = sprintf(
    /* translators: %s: bid number */
    __( 'New reply to your query: %s', 'eprocurement' ),
    $bid_number
);
$preview_text = sprintf(
    /* translators: %s: responder name */
    __( '%s has replied to your query.', 'eprocurement' ),
    $responder
);

require __DIR__ . '/_header.php';
?>
                        <h1 style="margin:0 0 16px;font-size:24px;font-weight:700;color:#1e293b;letter-spacing:-0.02em;">
                            <?php echo esc_html__( 'You have a new reply', 'eprocurement' ); ?>
                        </h1>
                        <p style="margin:0 0 16px;">
                            <?php
                            /* translators: %s: bidder name */
                            echo esc_html( sprintf( __( 'Hi %s,', 'eprocurement' ), $bidder_name ) );
                            ?>
                        </p>
                        <p style="margin:0 0 24px;">
                            <?php
                            echo wp_kses(
                                sprintf(
                                    /* translators: 1: responder name, 2: bid number, 3: bid title */
                                    __( '<strong>%1$s</strong> has replied to your query about <strong>%2$s — %3$s</strong>.', 'eprocurement' ),
                                    esc_html( $responder ),
                                    esc_html( $bid_number ),
                                    esc_html( $bid_title )
                                ),
                                [ 'strong' => [] ]
                            );
                            ?>
                        </p>

                        <!-- Reply Body -->
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f8fafc;border-left:3px solid #16a34a;border-radius:0 8px 8px 0;margin:0 0 24px;">
                            <tr>
                                <td style="padding:16px 20px;color:#334155;font-size:14px;line-height:1.65;font-style:italic;">
                                    "<?php echo esc_html( $reply ); ?>"
                                </td>
                            </tr>
                        </table>

                        <!-- CTA Button -->
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:24px 0;">
                            <tr>
                                <td align="center">
                                    <a href="<?php echo esc_url( $dashboard_url ); ?>" class="button" style="display:inline-block;padding:14px 32px;background:#8b1a2b;color:#fff !important;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;letter-spacing:0.01em;">
                                        <?php echo esc_html__( 'View full conversation', 'eprocurement' ); ?>
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0;color:#64748b;font-size:13px;">
                            <?php echo esc_html__( 'You can also view all your queries from your dashboard at any time.', 'eprocurement' ); ?>
                        </p>
<?php
require __DIR__ . '/_footer.php';
