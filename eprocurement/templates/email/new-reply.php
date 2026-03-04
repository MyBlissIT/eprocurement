<?php
/**
 * Email template: Reply notification to bidder.
 *
 * Variables available:
 * - $bidder_name   (string) Bidder's display name.
 * - $responder_name (string) Staff member's display name.
 * - $bid_number    (string) Bid reference number.
 * - $reply_text    (string) Reply message (plain text).
 * - $dashboard_url (string) URL to the bidder's dashboard.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
Hello <?php echo esc_html( $bidder_name ); ?>,

<?php echo esc_html( $responder_name ); ?> has replied to your query about <?php echo esc_html( $bid_number ); ?>.

Reply:
<?php echo esc_html( $reply_text ); ?>


View the full conversation in your dashboard:
<?php echo esc_url( $dashboard_url ); ?>


Regards,
eProcurement System
<?php echo esc_url( home_url() ); ?>
