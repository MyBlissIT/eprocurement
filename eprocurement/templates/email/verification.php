<?php
/**
 * Email template: Email verification.
 *
 * Variables available:
 * - $user_name    (string) User's display name.
 * - $verify_url   (string) Verification URL.
 * - $expiry_hours (int)    Hours until link expires.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
Hello <?php echo esc_html( $user_name ); ?>,

Thank you for registering on the eProcurement Portal.

Please click the link below to verify your email address:

<?php echo esc_url( $verify_url ); ?>


This link will expire in <?php echo absint( $expiry_hours ); ?> hours.

If you did not register for this account, please ignore this email.

Regards,
eProcurement System
<?php echo esc_url( home_url() ); ?>
