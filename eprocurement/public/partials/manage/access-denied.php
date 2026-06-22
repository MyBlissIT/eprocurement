<?php
/**
 * Access Denied page for the frontend admin panel.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
?>
<div class="eproc-wrap">
    <section class="eproc-auth-section">
        <div class="eproc-auth-container">
            <h1 class="eproc-auth-title"><?php esc_html_e( 'Access Denied', 'eprocurement' ); ?></h1>
            <p class="eproc-auth-subtitle">
                <?php esc_html_e( 'You do not have permission to access this page.', 'eprocurement' ); ?>
            </p>
            <div class="eproc-form-actions" style="margin-top: 24px;">
                <a href="<?php echo esc_url( home_url( "/{$slug}/" ) ); ?>" class="eproc-btn eproc-btn-primary">
                    <?php esc_html_e( 'Return to Portal', 'eprocurement' ); ?>
                </a>
            </div>
        </div>
    </section>
</div>
