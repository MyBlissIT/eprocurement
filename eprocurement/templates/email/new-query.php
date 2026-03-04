<?php
/**
 * Email template: New query notification to contact person.
 *
 * Variables available:
 * - $contact_name  (string) Contact person's name.
 * - $bidder_name   (string) Bidder's display name.
 * - $bid_number    (string) Bid reference number.
 * - $bid_title     (string) Bid title.
 * - $visibility    (string) 'public' or 'private'.
 * - $message_text  (string) Query message (plain text).
 * - $admin_url     (string) URL to the thread in admin.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
Hello <?php echo esc_html( $contact_name ); ?>,

A new query has been submitted regarding <?php echo esc_html( $bid_number ); ?> — <?php echo esc_html( $bid_title ); ?>.

Submitted by: <?php echo esc_html( $bidder_name ); ?>

Visibility: <?php echo esc_html( ucfirst( $visibility ) ); ?>

Message:
<?php echo esc_html( $message_text ); ?>


Please log in to the admin area to respond:
<?php echo esc_url( $admin_url ); ?>


Regards,
eProcurement System
<?php echo esc_url( home_url() ); ?>
