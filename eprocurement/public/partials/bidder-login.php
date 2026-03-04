<?php
/**
 * Bidder login form.
 *
 * Handles login via standard WordPress form POST with a nonce.
 * Shows success/error messages based on URL parameters.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug      = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$nav_items = Eprocurement_Public::get_nav_items();

// Redirect if already logged in
if ( is_user_logged_in() ) {
    if ( Eprocurement_Roles::is_staff() ) {
        wp_safe_redirect( home_url( "/{$slug}/manage/" ) );
        exit;
    } elseif ( Eprocurement_Roles::is_bidder() ) {
        wp_safe_redirect( home_url( "/{$slug}/my-account/" ) );
        exit;
    }
}

// Check for feedback messages from URL params
$verified           = isset( $_GET['verified'] ) && $_GET['verified'] === '1';
$verification_error = isset( $_GET['verification_error'] ) && $_GET['verification_error'] === '1';
$registered         = isset( $_GET['registered'] ) && $_GET['registered'] === '1';
$redirect_to        = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';

// Check for login error from transient
$email       = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
$login_error = '';
if ( $email ) {
    $transient_key = 'eproc_login_error_' . md5( $email );
    $login_error   = get_transient( $transient_key );
    if ( $login_error ) {
        delete_transient( $transient_key );
    }
}
?>
<div class="eproc-wrap">

    <!-- Navigation Bar -->
    <nav class="eproc-navbar">
        <div class="eproc-navbar-inner">
            <a href="<?php echo esc_url( home_url( "/{$slug}/" ) ); ?>" class="eproc-navbar-brand">
                <?php echo esc_html__( 'eProcurement Portal', 'eprocurement' ); ?>
            </a>
            <div class="eproc-navbar-links">
                <?php foreach ( $nav_items as $nav_item ) : ?>
                    <a href="<?php echo esc_url( $nav_item['url'] ); ?>" class="eproc-nav-link">
                        <?php echo esc_html( $nav_item['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="eproc-navbar-actions">
                <a href="<?php echo esc_url( home_url( "/{$slug}/register/" ) ); ?>" class="eproc-btn eproc-btn-primary">
                    <?php echo esc_html__( 'Register', 'eprocurement' ); ?>
                </a>
            </div>
            <button class="eproc-navbar-toggle" aria-label="<?php echo esc_attr__( 'Toggle navigation', 'eprocurement' ); ?>">
                <span class="eproc-navbar-toggle-icon"></span>
            </button>
        </div>
    </nav>

    <!-- Login Form -->
    <section class="eproc-auth-section">
        <div class="eproc-auth-container">
            <h1 class="eproc-auth-title"><?php echo esc_html__( 'eProcurement Login', 'eprocurement' ); ?></h1>
            <p class="eproc-auth-subtitle">
                <?php echo esc_html__( 'Sign in to access the eProcurement portal.', 'eprocurement' ); ?>
            </p>

            <!-- Success Messages -->
            <?php if ( $verified ) : ?>
                <div class="eproc-info-box eproc-info-box--success">
                    <p><?php echo esc_html__( 'Your email address has been verified successfully! You can now log in.', 'eprocurement' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $registered ) : ?>
                <div class="eproc-info-box eproc-info-box--success">
                    <p><?php echo esc_html__( 'Registration successful! Please check your email for the verification link, then log in.', 'eprocurement' ); ?></p>
                </div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if ( $verification_error ) : ?>
                <div class="eproc-info-box eproc-info-box--error">
                    <p><?php echo esc_html__( 'Email verification failed. The link may have expired. Please contact support or register again.', 'eprocurement' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $login_error ) : ?>
                <div class="eproc-info-box eproc-info-box--error">
                    <p><?php echo esc_html( $login_error ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="eproc-form" id="eproc-login-form">
                <?php wp_nonce_field( 'eproc_login', 'eproc_login_nonce' ); ?>

                <?php if ( $redirect_to ) : ?>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
                <?php endif; ?>

                <div class="eproc-form-group">
                    <label for="eproc-login-email" class="eproc-label">
                        <?php echo esc_html__( 'Email Address', 'eprocurement' ); ?>
                    </label>
                    <input
                        type="email"
                        id="eproc-login-email"
                        name="email"
                        class="eproc-input"
                        required
                        autocomplete="email"
                        value="<?php echo esc_attr( $email ); ?>"
                    />
                </div>

                <div class="eproc-form-group">
                    <label for="eproc-login-password" class="eproc-label">
                        <?php echo esc_html__( 'Password', 'eprocurement' ); ?>
                    </label>
                    <input
                        type="password"
                        id="eproc-login-password"
                        name="password"
                        class="eproc-input"
                        required
                        autocomplete="current-password"
                    />
                    <div class="eproc-form-help" style="text-align:right; margin-top:6px;">
                        <a href="<?php echo esc_url( wp_lostpassword_url( home_url( "/{$slug}/login/" ) ) ); ?>" class="eproc-link-muted">
                            <?php echo esc_html__( 'Forgot password?', 'eprocurement' ); ?>
                        </a>
                    </div>
                </div>

                <div class="eproc-form-actions">
                    <button type="submit" class="eproc-btn eproc-btn-primary eproc-btn-lg eproc-btn-block">
                        <?php echo esc_html__( 'Login', 'eprocurement' ); ?>
                    </button>
                </div>
            </form>

            <p class="eproc-auth-footer">
                <?php echo esc_html__( 'Don\'t have an account?', 'eprocurement' ); ?>
                <a href="<?php echo esc_url( home_url( "/{$slug}/register/" ) ); ?>">
                    <?php echo esc_html__( 'Register here', 'eprocurement' ); ?>
                </a>
            </p>
        </div>
    </section>

</div><!-- .eproc-wrap -->
