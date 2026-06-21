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
        <div class="eproc-auth-container eproc-card">
            <div class="eproc-auth-header">
                <h1 class="eproc-auth-title"><?php echo esc_html__( 'Welcome back', 'eprocurement' ); ?></h1>
                <p class="eproc-auth-subtitle">
                    <?php echo esc_html__( 'Sign in to your eProcurement portal to manage bids and submissions.', 'eprocurement' ); ?>
                </p>
            </div>

            <!-- Success Messages -->
            <?php if ( $verified ) : ?>
                <div class="eproc-notice success">
                    <p><?php echo esc_html__( 'Your email address has been verified successfully! You can now log in.', 'eprocurement' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $registered ) : ?>
                <div class="eproc-notice success">
                    <p><?php echo esc_html__( 'Registration successful! Please check your email for the verification link, then log in.', 'eprocurement' ); ?></p>
                </div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if ( $verification_error ) : ?>
                <div class="eproc-notice error">
                    <p><?php echo esc_html__( 'Email verification failed. The link may have expired. Please contact support or register again.', 'eprocurement' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $login_error ) : ?>
                <div class="eproc-notice error">
                    <p><?php echo esc_html( $login_error ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="eproc-form" id="eproc-login-form">
                <?php wp_nonce_field( 'eproc_login', 'eproc_login_nonce' ); ?>

                <?php if ( $redirect_to ) : ?>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
                <?php endif; ?>

                <div class="eproc-form-group">
                    <label for="eproc-login-email" class="eproc-form-label">
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
                        placeholder="<?php echo esc_attr__( 'you@company.com', 'eprocurement' ); ?>"
                    />
                </div>

                <div class="eproc-form-group">
                    <label for="eproc-login-password" class="eproc-form-label">
                        <?php echo esc_html__( 'Password', 'eprocurement' ); ?>
                    </label>
                    <div class="eproc-password-field">
                        <input
                            type="password"
                            id="eproc-login-password"
                            name="password"
                            class="eproc-input"
                            required
                            autocomplete="current-password"
                            placeholder="<?php echo esc_attr__( 'Enter your password', 'eprocurement' ); ?>"
                        />
                        <button type="button" class="eproc-password-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'eprocurement' ); ?>" data-toggle-password="eproc-login-password">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="eproc-icon-eye"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                            <svg viewBox="0 0 20 20" fill="currentColor" class="eproc-icon-eye-off" style="display:none;"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2 2 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M9.013 4.044L11 6.03a4 4 0 014.95 4.95l1.617 1.617A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-2.987.044z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                    <div class="eproc-form-help" style="text-align:right; margin-top:6px;">
                        <a href="<?php echo esc_url( wp_lostpassword_url( home_url( "/{$slug}/login/" ) ) ); ?>" class="eproc-link-muted">
                            <?php echo esc_html__( 'Forgot password?', 'eprocurement' ); ?>
                        </a>
                    </div>
                </div>

                <div class="eproc-form-actions">
                    <button type="submit" class="eproc-btn eproc-btn-primary eproc-btn-lg eproc-btn-block">
                        <?php echo esc_html__( 'Sign in', 'eprocurement' ); ?>
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

<script>
// Password visibility toggle (self-contained — page doesn't load frontend-admin.js).
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var inputId = btn.getAttribute('data-toggle-password');
        var input = document.getElementById(inputId);
        if (!input) return;
        var eye = btn.querySelector('.eproc-icon-eye');
        var eyeOff = btn.querySelector('.eproc-icon-eye-off');
        if (input.type === 'password') {
            input.type = 'text';
            if (eye) eye.style.display = 'none';
            if (eyeOff) eyeOff.style.display = 'inline-block';
            btn.setAttribute('aria-label', '<?php echo esc_js( __( 'Hide password', 'eprocurement' ) ); ?>');
        } else {
            input.type = 'password';
            if (eye) eye.style.display = 'inline-block';
            if (eyeOff) eyeOff.style.display = 'none';
            btn.setAttribute('aria-label', '<?php echo esc_js( __( 'Show password', 'eprocurement' ) ); ?>');
        }
    });
});
</script>
