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
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
Hello <?php echo esc_html( $bidder_name ); ?>,

You are invited to submit a bid for the following tender:

Bid Number: <?php echo esc_html( $bid_number ); ?>

Title: <?php echo esc_html( $bid_title ); ?>

Briefing Date: <?php echo esc_html( $briefing_date ); ?>


To submit your bid, click the link below:

<?php echo esc_url( $submit_url ); ?>


If you don't have an account yet, register here first:

<?php echo esc_url( $register_url ); ?>


Accepted file types: PDF, Excel (XLS, XLSX), CSV — max 10MB.
Only one submission per bidder is allowed. You may cancel and resubmit before the closing date.

Regards,
eProcurement System
<?php echo esc_url( home_url() ); ?>
