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
        <div class="eproc-auth-container">
            <h1 class="eproc-auth-title"><?php echo esc_html__( 'Bidder Registration', 'eprocurement' ); ?></h1>
            <p class="eproc-auth-subtitle">
                <?php echo esc_html__( 'Create an account to submit queries and track your bid activity.', 'eprocurement' ); ?>
            </p>

            <!-- Email Verification Info Box -->
            <div class="eproc-info-box eproc-info-box--warning">
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
                        <label for="eproc-reg-password" class="eproc-label">
                            <?php echo esc_html__( 'Password', 'eprocurement' ); ?> <span class="eproc-required">*</span>
                        </label>
                        <input
                            type="password"
                            id="eproc-reg-password"
                            name="password"
                            class="eproc-input"
                            required
                            minlength="8"
                            autocomplete="new-password"
                        />
                        <span class="eproc-form-hint">
                            <?php echo esc_html__( 'Minimum 8 characters', 'eprocurement' ); ?>
                        </span>
                    </div>
                    <div class="eproc-form-group">
                        <label for="eproc-reg-password-confirm" class="eproc-label">
                            <?php echo esc_html__( 'Confirm Password', 'eprocurement' ); ?> <span class="eproc-required">*</span>
                        </label>
                        <input
                            type="password"
                            id="eproc-reg-password-confirm"
                            name="password_confirm"
                            class="eproc-input"
                            required
                            minlength="8"
                            autocomplete="new-password"
                        />
                    </div>
                </div>

                <div class="eproc-form-actions">
                    <button type="submit" class="eproc-btn eproc-btn-primary eproc-btn-lg eproc-btn-block" id="eproc-register-submit">
                        <?php echo esc_html__( 'Create Account & Send Verification Email', 'eprocurement' ); ?>
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
</script>
