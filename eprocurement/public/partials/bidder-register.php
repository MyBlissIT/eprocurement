<?php
/**
 * Bidder registration form.
 *
 * Collects bidder details and submits via AJAX to the REST API
 * /register endpoint. On success, a verification email is sent.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug      = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
$nav_items = Eprocurement_Public::get_nav_items();

// Redirect if already logged in as bidder
if ( is_user_logged_in() && Eprocurement_Roles::is_bidder() ) {
    wp_safe_redirect( home_url( "/{$slug}/my-account/" ) );
    exit;
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
                <a href="<?php echo esc_url( home_url( "/{$slug}/login/" ) ); ?>" class="eproc-btn eproc-btn-outline">
                    <?php echo esc_html__( 'Login', 'eprocurement' ); ?>
                </a>
            </div>
            <button class="eproc-navbar-toggle" aria-label="<?php echo esc_attr__( 'Toggle navigation', 'eprocurement' ); ?>">
                <span class="eproc-navbar-toggle-icon"></span>
            </button>
        </div>
    </nav>

    <!-- Registration Form -->
    <section class="eproc-auth-section">
        <div class="eproc-auth-container eproc-card">
            <div class="eproc-auth-header">
                <h1 class="eproc-auth-title"><?php echo esc_html__( 'Create your account', 'eprocurement' ); ?></h1>
                <p class="eproc-auth-subtitle">
                    <?php echo esc_html__( 'Register to submit queries, receive notifications, and submit bids for open tenders.', 'eprocurement' ); ?>
                </p>
            </div>

            <!-- Email Verification Info Box -->
            <div class="eproc-notice warning">
                <p>
                    <?php echo esc_html__( 'After registering, you will receive a verification email. You must verify your email address before you can submit queries to bid contacts.', 'eprocurement' ); ?>
                </p>
            </div>

            <!-- Feedback Area -->
            <div class="eproc-form-feedback" id="eproc-register-feedback" style="display:none;"></div>

            <form id="eproc-register-form" class="eproc-form" novalidate>
                <div class="eproc-form-row eproc-form-row--2col">
                    <div class="eproc-form-group">
                        <label for="eproc-reg-first-name" class="eproc-label">
                            <?php echo esc_html__( 'First Name', 'eprocurement' ); ?> <span class="eproc-required">*</span>
                        </label>
                        <input
                            type="text"
                            id="eproc-reg-first-name"
                            name="first_name"
                            class="eproc-input"
                            required
                            autocomplete="given-name"
                        />
                    </div>
                    <div class="eproc-form-group">
                        <label for="eproc-reg-last-name" class="eproc-label">
                            <?php echo esc_html__( 'Last Name', 'eprocurement' ); ?> <span class="eproc-required">*</span>
                        </label>
                        <input
                            type="text"
                            id="eproc-reg-last-name"
                            name="last_name"
                            class="eproc-input"
                            required
                            autocomplete="family-name"
                        />
                    </div>
                </div>

                <div class="eproc-form-row eproc-form-row--2col">
                    <div class="eproc-form-group">
                        <label for="eproc-reg-company" class="eproc-label">
                            <?php echo esc_html__( 'Company Name', 'eprocurement' ); ?> <span class="eproc-required">*</span>
                        </label>
                        <input
                            type="text"
                            id="eproc-reg-company"
                            name="company_name"
                            class="eproc-input"
                            required
                            autocomplete="organization"
                        />
                    </div>
                    <div class="eproc-form-group">
                        <label for="eproc-reg-company-reg" class="eproc-label">
                            <?php echo esc_html__( 'Company Reg Number', 'eprocurement' ); ?>
                            <span class="eproc-optional">(<?php echo esc_html__( 'optional', 'eprocurement' ); ?>)</span>
                        </label>
                        <input
                            type="text"
                            id="eproc-reg-company-reg"
                            name="company_reg"
                            class="eproc-input"
                        />
                    </div>
                </div>

                <div class="eproc-form-group">
                    <label for="eproc-reg-phone" class="eproc-label">
                        <?php echo esc_html__( 'Phone Number', 'eprocurement' ); ?> <span class="eproc-required">*</span>
                    </label>
                    <input
                        type="tel"
                        id="eproc-reg-phone"
                        name="phone"
                        class="eproc-input"
                        required
                        autocomplete="tel"
                    />
                </div>

                <div class="eproc-form-group">
                    <label for="eproc-reg-email" class="eproc-label">
                        <?php echo esc_html__( 'Email Address', 'eprocurement' ); ?> <span class="eproc-required">*</span>
                    </label>
                    <input
                        type="email"
                        id="eproc-reg-email"
                        name="email"
                        class="eproc-input"
                        required
                        autocomplete="email"
                    />
                </div>

                <div class="eproc-form-row eproc-form-row--2col">
                    <div class="eproc-form-group">
                        <label for="eproc-reg-password" class="eproc-form-label">
                            <?php echo esc_html__( 'Password', 'eprocurement' ); ?> <span class="eproc-required">*</span>
                        </label>
                        <div class="eproc-password-field">
                            <input
                                type="password"
                                id="eproc-reg-password"
                                name="password"
                                class="eproc-input"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="<?php echo esc_attr__( 'At least 8 characters', 'eprocurement' ); ?>"
                                data-strength-target="eproc-password-strength"
                            />
                            <button type="button" class="eproc-password-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'eprocurement' ); ?>" data-toggle-password="eproc-reg-password">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="eproc-icon-eye"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                                <svg viewBox="0 0 20 20" fill="currentColor" class="eproc-icon-eye-off" style="display:none;"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2 2 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                        <div class="eproc-password-strength" id="eproc-password-strength">
                            <div class="eproc-password-strength-bar"><div class="eproc-password-strength-fill"></div></div>
                            <span class="eproc-password-strength-label"></span>
                        </div>
                    </div>
                    <div class="eproc-form-group">
                        <label for="eproc-reg-password-confirm" class="eproc-form-label">
                            <?php echo esc_html__( 'Confirm Password', 'eprocurement' ); ?> <span class="eproc-required">*</span>
                        </label>
                        <div class="eproc-password-field">
                            <input
                                type="password"
                                id="eproc-reg-password-confirm"
                                name="password_confirm"
                                class="eproc-input"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="<?php echo esc_attr__( 'Re-enter your password', 'eprocurement' ); ?>"
                            />
                            <button type="button" class="eproc-password-toggle" aria-label="<?php echo esc_attr__( 'Show password', 'eprocurement' ); ?>" data-toggle-password="eproc-reg-password-confirm">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="eproc-icon-eye"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                                <svg viewBox="0 0 20 20" fill="currentColor" class="eproc-icon-eye-off" style="display:none;"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2 2 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="eproc-form-actions">
                    <button type="submit" class="eproc-btn eproc-btn-primary eproc-btn-lg eproc-btn-block" id="eproc-register-submit">
                        <?php echo esc_html__( 'Create Account', 'eprocurement' ); ?>
                    </button>
                </div>
            </form>

            <p class="eproc-auth-footer">
                <?php echo esc_html__( 'Already have an account?', 'eprocurement' ); ?>
                <a href="<?php echo esc_url( home_url( "/{$slug}/login/" ) ); ?>">
                    <?php echo esc_html__( 'Login here', 'eprocurement' ); ?>
                </a>
            </p>
        </div>
    </section>

</div><!-- .eproc-wrap -->

<script>
(function() {
    var form     = document.getElementById('eproc-register-form');
    var feedback = document.getElementById('eproc-register-feedback');
    var submitBtn = document.getElementById('eproc-register-submit');
    var slug     = eprocFrontend.slug || 'tenders';

    if ( ! form ) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        feedback.style.display = 'none';

        // Client-side validation
        var password        = form.querySelector('[name="password"]').value;
        var passwordConfirm = form.querySelector('[name="password_confirm"]').value;
        var email           = form.querySelector('[name="email"]').value;
        var firstName       = form.querySelector('[name="first_name"]').value.trim();
        var lastName        = form.querySelector('[name="last_name"]').value.trim();
        var company         = form.querySelector('[name="company_name"]').value.trim();

        if ( ! firstName || ! lastName || ! company || ! email ) {
            showFeedback('error', '<?php echo esc_js( __( 'Please fill in all required fields.', 'eprocurement' ) ); ?>');
            return;
        }

        if ( password.length < 8 ) {
            showFeedback('error', '<?php echo esc_js( __( 'Password must be at least 8 characters.', 'eprocurement' ) ); ?>');
            return;
        }

        if ( password !== passwordConfirm ) {
            showFeedback('error', '<?php echo esc_js( __( 'Passwords do not match.', 'eprocurement' ) ); ?>');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = eprocFrontend.strings.registering;

        var formData = {
            first_name:   firstName,
            last_name:    lastName,
            company_name: company,
            company_reg:  form.querySelector('[name="company_reg"]').value.trim(),
            phone:        form.querySelector('[name="phone"]').value.trim(),
            email:        email,
            password:     password
        };

        fetch( eprocFrontend.restUrl + 'register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': eprocFrontend.nonce
            },
            body: JSON.stringify(formData)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if ( data.success ) {
                showFeedback('success', eprocFrontend.strings.registered);
                form.reset();
                // Redirect to login after a short delay
                setTimeout(function() {
                    window.location.href = '/' + slug + '/login/?registered=1';
                }, 3000);
            } else {
                showFeedback('error', data.error || eprocFrontend.strings.error);
            }
            submitBtn.disabled = false;
            submitBtn.textContent = '<?php echo esc_js( __( 'Create Account & Send Verification Email', 'eprocurement' ) ); ?>';
        })
        .catch(function() {
            showFeedback('error', eprocFrontend.strings.error);
            submitBtn.disabled = false;
            submitBtn.textContent = '<?php echo esc_js( __( 'Create Account & Send Verification Email', 'eprocurement' ) ); ?>';
        });
    });

    function showFeedback(type, message) {
        feedback.className = 'eproc-form-feedback eproc-feedback-' + type;
        feedback.textContent = message;
        feedback.style.display = 'block';
        feedback.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
})();

// Password visibility toggle + strength meter (self-contained).
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

// Password strength meter.
var strengthInput = document.querySelector('[data-strength-target]');
if (strengthInput) {
    strengthInput.addEventListener('input', function () {
        var meterId = strengthInput.getAttribute('data-strength-target');
        var meter = document.getElementById(meterId);
        if (!meter) return;
        var pw = strengthInput.value;
        var score = 0;
        if (pw.length >= 8)  score++;
        if (pw.length >= 12) score++;
        if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
        if (/\d/.test(pw)) score++;
        if (/[^a-zA-Z0-9]/.test(pw)) score++;
        var label = '', cls = '';
        if (!pw) { label = ''; cls = ''; }
        else if (score < 2) { label = 'Weak';   cls = 'weak'; }
        else if (score < 3) { label = 'Fair';   cls = 'fair'; }
        else if (score < 4) { label = 'Good';   cls = 'good'; }
        else                { label = 'Strong'; cls = 'strong'; }
        meter.className = 'eproc-password-strength' + (cls ? ' eproc-password-strength--' + cls : '');
        var labelEl = meter.querySelector('.eproc-password-strength-label');
        if (labelEl) labelEl.textContent = label;
    });
}
</script>
