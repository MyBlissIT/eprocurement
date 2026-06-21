<?php
/**
 * Email template: Shared footer.
 *
 * Closes the body section, adds footer with branding + support link.
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$brand_name    = get_option( 'eprocurement_brand_name', 'eProcurement' );
$brand_url     = get_option( 'eprocurement_brand_url', home_url() );
$support_email = get_option( 'eprocurement_support_email', '' );
$brand_tagline = get_option( 'eprocurement_brand_tagline', __( 'Procurement made simple.', 'eprocurement' ) );
?>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="padding:24px 40px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 14px 14px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="color:#64748b;font-size:12px;line-height:1.6;">
                                    <p style="margin:0 0 8px;font-weight:600;color:#475569;font-size:13px;">
                                        <?php echo esc_html( $brand_name ); ?>
                                    </p>
                                    <p style="margin:0 0 8px;">
                                        <?php echo esc_html( $brand_tagline ); ?>
                                    </p>
                                    <p style="margin:0;">
                                        <?php if ( $support_email ) : ?>
                                            <a href="mailto:<?php echo esc_attr( $support_email ); ?>" style="color:#64748b;"><?php echo esc_html( $support_email ); ?></a>
                                            <span style="color:#cbd5e1;margin:0 6px;">·</span>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url( $brand_url ); ?>" style="color:#64748b;"><?php echo esc_html( $brand_url ); ?></a>
                                    </p>
                                    <p style="margin:12px 0 0;color:#94a3b8;font-size:11px;">
                                        <?php echo esc_html__( 'This is an automated message from the eProcurement system. Please do not reply directly to this email.', 'eprocurement' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

            </table>
            <!-- /Container -->

        </td>
    </tr>
</table>
<!-- /Wrapper -->

</body>
</html>
