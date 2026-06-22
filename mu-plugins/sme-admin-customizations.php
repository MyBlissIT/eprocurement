<?php
/**
 * MyBliss Technologies Admin Customizer v4.0
 * Must-Use Plugin — Complete WP Admin Overhaul
 * wp-content/mu-plugins/sme-admin-customizations.php
 * Logo: wp-content/mu-plugins/sme-assets/mybliss-logo.png
 *
 * Transforms the WordPress admin into a fully custom MyBliss-branded interface.
 * No WordPress resemblance — custom admin bar, hidden WP sidebar, branded profile page.
 *
 * Adapted for eProcurement dev environment.
 */
if (!defined('ABSPATH')) exit;

define('SME_BRAND', 'MyBliss Technologies');
define('SME_URL', 'https://www.myblisstech.com');
define('SME_EMAIL', 'support@myblisstech.com');
define('SME_MAROON', '#8b1a2b');
define('SME_MAROON_L', '#a52040');
define('SME_NAVY', '#1a1a5e');
define('SME_NAVY_L', '#2d2d7a');
define('SME_DARK', '#0a0a14');
define('SME_CARD', '#12122a');
define('SME_ACCENT', '#1a1a38');
define('SME_BORDER', '#2a2a50');
define('SME_TXT', '#e8e8f0');
define('SME_MUT', '#9090a8');

function sme_logo() {
    $f = WPMU_PLUGIN_DIR . '/sme-assets/mybliss-logo.png';
    if (file_exists($f)) return content_url('mu-plugins/sme-assets/mybliss-logo.png');
    $id = get_theme_mod('custom_logo');
    return $id ? wp_get_attachment_image_url($id, 'full') : '';
}

/**
 * Dynamic branding helper.
 *
 * Reads values from Eprocurement_Branding (if loaded) so that MU-plugin
 * branding adapts to per-tenant overrides stored in WordPress options.
 * Falls back to the hardcoded SME_* constants when the class is unavailable
 * (e.g. before plugins_loaded or if the eProcurement plugin is deactivated).
 *
 * @param string $key     One of: name, url, email, logo, primary, primary_hover,
 *                        secondary, secondary_light, login_title.
 * @param string $default Fallback value (typically the matching SME_* constant).
 * @return string
 */
function sme_get_brand( $key, $default = '' ) {
    if ( class_exists( 'Eprocurement_Branding' ) ) {
        switch ( $key ) {
            case 'name':            return Eprocurement_Branding::brand_name();
            case 'url':             return Eprocurement_Branding::brand_url();
            case 'email':           return Eprocurement_Branding::support_email();
            case 'logo':            return Eprocurement_Branding::logo_url();
            case 'primary':         return Eprocurement_Branding::color_primary();
            case 'primary_hover':   return Eprocurement_Branding::color_primary_hover();
            case 'secondary':       return Eprocurement_Branding::color_secondary();
            case 'secondary_light': return Eprocurement_Branding::color_secondary_light();
            case 'login_title':     return Eprocurement_Branding::login_title();
        }
    }
    return $default;
}

// ══════════════════════════════════════════
// 1. REDIRECT WP DASHBOARD → ePROCUREMENT
// ══════════════════════════════════════════
add_action('admin_init', function() {
    global $pagenow;
    // Redirect index.php (WP Dashboard) to eProcurement Dashboard
    // Super Admin can access WP Dashboard directly (no redirect)
    // All other roles get redirected to eProcurement
    if ($pagenow === 'index.php' && empty($_GET['page']) && !is_super_admin()) {
        wp_safe_redirect(admin_url('admin.php?page=eprocurement'));
        exit;
    }
});

// ══════════════════════════════════════════
// 2. ADMIN BAR — HIDE COMPLETELY
// ══════════════════════════════════════════
// The WP admin bar is a dead giveaway for WordPress.
// User info is moved into the eProcurement sidebar instead.
add_filter('show_admin_bar', '__return_false');

// ══════════════════════════════════════════
// 3. WP SIDEBAR MENUS — Trim per role
// ══════════════════════════════════════════
add_action('admin_menu', function() {
    // Remove Comments for everyone (not used)
    remove_menu_page('edit-comments.php');

    // Non-Super-Admin: remove everything except eProcurement + Profile
    if (!is_super_admin()) {
        remove_menu_page('index.php');         // Dashboard
        remove_menu_page('edit.php');           // Posts
        remove_menu_page('upload.php');         // Media
        remove_menu_page('edit.php?post_type=page'); // Pages
        remove_menu_page('plugins.php');        // Plugins
        remove_menu_page('themes.php');         // Appearance
        remove_menu_page('tools.php');          // Tools
        remove_menu_page('options-general.php'); // Settings
        remove_menu_page('users.php');          // Users
    } else {
        // Super Admin: remove clutter but keep core WP features
        remove_menu_page('edit.php');           // Posts (not used)
    }
}, 999);

// Add body class to distinguish eProcurement pages from WP native pages
add_filter('admin_body_class', function($classes) {
    $screen = get_current_screen();
    if ($screen && strpos($screen->id, 'eprocurement') !== false) {
        $classes .= ' eproc-admin-page';
    }
    return $classes;
});

// ══════════════════════════════════════════
// 4. SCREEN OPTIONS + HELP — HIDDEN
// ══════════════════════════════════════════
add_action('admin_head', function() {
    echo '<style>#screen-options-link-wrap,#contextual-help-link-wrap,#screen-meta,#screen-meta-links{display:none!important}</style>';
});

// ══════════════════════════════════════════
// 5. UPDATES — HIDDEN FROM UI
// ══════════════════════════════════════════
add_action('admin_init', function() {
    remove_action('admin_notices', 'update_nag', 3);
    remove_action('admin_notices', 'maintenance_nag', 10);
});
add_action('admin_menu', function() {
    remove_submenu_page('index.php', 'update-core.php');
}, 999);
add_filter('pre_site_transient_update_core', '__return_null');
add_filter('pre_site_transient_update_plugins', '__return_null');
add_filter('pre_site_transient_update_themes', '__return_null');

// ══════════════════════════════════════════
// 6. CLEAN PROFILE PAGE
// ══════════════════════════════════════════
add_action('admin_head-profile.php', function() {
    $logo    = sme_logo();
    $primary = sme_get_brand( 'primary', SME_MAROON );
    $primary_hover = sme_get_brand( 'primary_hover', '#6d1522' );
    ?>
    <style>
        /* Hide all unnecessary profile fields */
        .user-rich-editing-wrap,
        .user-syntax-highlighting-wrap,
        .user-comment-shortcuts-wrap,
        .user-admin-bar-front-wrap,
        .user-language-wrap,
        .user-admin-color-wrap,
        .user-description-wrap,
        .user-url-wrap,
        .user-profile-picture,
        .user-sessions-wrap,
        #application-passwords-section,
        /* Social fields */
        .user-googleplus-wrap,.user-twitter-wrap,.user-facebook-wrap,
        .user-instagram-wrap,.user-linkedin-wrap,.user-myspace-wrap,
        .user-soundcloud-wrap,.user-tumblr-wrap,.user-youtube-wrap,
        .user-wikipedia-wrap,.user-pinterest-wrap,
        /* WP toolbar & keyboard shortcuts */
        .show-admin-bar,
        /* Visual editor / code editor prefs */
        .user-syntax-highlighting-wrap {
            display: none !important;
        }

        /* Restyle the profile page to match MyBliss branding */
        #your-profile {
            max-width: 680px;
            margin: 0 auto;
            padding: 32px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        #your-profile h2 {
            font-size: 18px;
            font-weight: 600;
            color: <?php echo SME_DARK; ?>;
            border-bottom: 2px solid <?php echo $primary; ?>;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        #your-profile .form-table th {
            font-weight: 500;
            color: #334155;
            padding: 12px 10px 12px 0;
        }

        #your-profile .form-table td {
            padding: 12px 10px;
        }

        #your-profile input[type="text"],
        #your-profile input[type="email"],
        #your-profile input[type="password"] {
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
            max-width: 400px;
        }

        #your-profile input[type="text"]:focus,
        #your-profile input[type="email"]:focus,
        #your-profile input[type="password"]:focus {
            border-color: <?php echo $primary; ?>;
            box-shadow: 0 0 0 3px <?php echo $primary; ?>1a;
            outline: none;
        }

        #your-profile .button-primary {
            background: <?php echo $primary; ?> !important;
            border-color: <?php echo $primary; ?> !important;
            border-radius: 8px !important;
            padding: 8px 24px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            text-shadow: none !important;
            box-shadow: none !important;
            transition: all 0.2s !important;
        }
        #your-profile .button-primary:hover {
            background: <?php echo $primary_hover; ?> !important;
            border-color: <?php echo $primary_hover; ?> !important;
            transform: translateY(-1px);
        }

        /* Profile header branding */
        #profile-page .wrap > h1::before {
            content: '';
            display: inline-block;
            width: 28px;
            height: 28px;
            background: <?php echo $primary; ?>;
            border-radius: 50%;
            margin-right: 10px;
            vertical-align: middle;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Rename section headers
        var headings = document.querySelectorAll('#your-profile h2');
        if (headings[0]) headings[0].textContent = 'Your Account';

        // Rename "Account Management" to "Change Password"
        for (var h = 0; h < headings.length; h++) {
            if (headings[h].textContent.trim() === 'Account Management') {
                headings[h].textContent = 'Change Password';
            }
        }

        // Hide sections AFTER "Account Management" / "Change Password" (sessions, app passwords)
        // Keep headings[0] (Name), headings[1] (Contact Info), headings[2] (Account Management/Password)
        for (var i = 3; i < headings.length; i++) {
            var el = headings[i];
            while (el) {
                el.style.display = 'none';
                el = el.nextElementSibling;
                if (el && el.tagName === 'H2') break;
            }
        }

        // Update the page title
        var pageTitle = document.querySelector('.wrap > h1');
        if (pageTitle) pageTitle.textContent = 'Account Settings';
    });
    </script>
    <?php
});

// ══════════════════════════════════════════
// 7. ADMIN FOOTER
// ══════════════════════════════════════════
add_filter('admin_footer_text', function() {
    $brand = sme_get_brand( 'name', SME_BRAND );
    $url   = sme_get_brand( 'url', SME_URL );
    $color = sme_get_brand( 'primary', SME_MAROON );
    return '<span style="color:#64748b">Powered by <a href="' . esc_url( $url ) . '" target="_blank" style="text-decoration:none;font-weight:600;color:' . esc_attr( $color ) . '">' . esc_html( $brand ) . '</a></span>';
});
add_filter('update_footer', '__return_empty_string', 999);

// ══════════════════════════════════════════
// 8. COMPLETE ADMIN CSS OVERHAUL
// ══════════════════════════════════════════
add_action('admin_head', function() {
    $primary      = sme_get_brand( 'primary', SME_MAROON );
    $primary_hover = sme_get_brand( 'primary_hover', '#6d1522' );
    $secondary     = sme_get_brand( 'secondary', SME_NAVY );
    $secondary_lt  = sme_get_brand( 'secondary_light', SME_NAVY_L );
    ?>
    <style>
    /* ─── Import clean font ─── */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

    /* ─── ADMIN BAR: Hidden entirely ─── */
    #wpadminbar {
        display: none !important;
    }
    html.wp-toolbar {
        padding-top: 0 !important;
    }

    /* ─── HIDE WP SIDEBAR on eProcurement pages only ─── */
    .eproc-admin-page #adminmenuwrap,
    .eproc-admin-page #adminmenuback,
    .eproc-admin-page #adminmenu {
        display: none !important;
    }

    /* eProcurement pages: full width (no WP sidebar) */
    .eproc-admin-page #wpcontent {
        margin-left: 0 !important;
        padding-left: 0 !important;
    }

    #wpbody-content {
        padding-bottom: 20px;
    }

    /* Hide collapse button on eProcurement pages */
    .eproc-admin-page #collapse-menu,
    .eproc-admin-page #collapse-button {
        display: none !important;
    }

    /* ─── RESTYLE WP SIDEBAR on native pages ─── */
    #adminmenuback,
    #adminmenuwrap {
        background: <?php echo $secondary; ?> !important;
    }

    #adminmenu {
        background: <?php echo $secondary; ?> !important;
    }

    #adminmenu a {
        color: rgba(255,255,255,0.75) !important;
        font-family: 'Inter', -apple-system, sans-serif !important;
    }

    #adminmenu .wp-submenu {
        background: <?php echo $secondary_lt; ?> !important;
    }
    #adminmenu .wp-submenu a {
        color: rgba(255,255,255,0.65) !important;
    }
    #adminmenu .wp-submenu a:hover,
    #adminmenu .wp-submenu a:focus {
        color: #fff !important;
    }

    /* Active / current menu item */
    #adminmenu .current a.menu-top,
    #adminmenu li.current a.menu-top {
        background: <?php echo $primary; ?> !important;
        color: #fff !important;
    }

    /* Current submenu dropdown stays secondary */
    #adminmenu .wp-has-current-submenu .wp-submenu {
        background: <?php echo $secondary_lt; ?> !important;
    }
    #adminmenu .wp-has-current-submenu .wp-submenu .wp-submenu-head {
        display: none !important;
    }

    #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu {
        background: <?php echo $primary; ?> !important;
        color: #fff !important;
    }

    /* Hover state */
    #adminmenu li.menu-top:hover,
    #adminmenu li > a.menu-top:hover {
        background: <?php echo $primary; ?>66 !important;
        color: #fff !important;
    }

    /* Menu icons */
    #adminmenu div.wp-menu-image::before {
        color: rgba(255,255,255,0.6) !important;
    }
    #adminmenu .current div.wp-menu-image::before,
    #adminmenu .wp-has-current-submenu div.wp-menu-image::before,
    #adminmenu li.menu-top:hover div.wp-menu-image::before {
        color: #fff !important;
    }

    /* Menu separators */
    #adminmenu li.wp-menu-separator {
        background: rgba(255,255,255,0.08) !important;
    }

    /* Collapse button styling */
    #collapse-menu {
        color: rgba(255,255,255,0.5) !important;
    }
    #collapse-menu:hover {
        color: #fff !important;
    }
    #collapse-button {
        background: transparent !important;
    }
    #collapse-button div::after {
        color: rgba(255,255,255,0.5) !important;
    }

    /* ─── ADMIN BODY BACKGROUND ─── */
    #wpwrap {
        background: #f1f5f9 !important;
    }

    /* ─── ADMIN NOTICES: Rebrand ─── */
    .notice, .updated, .error, .update-nag {
        border-radius: 6px !important;
        border-left-width: 4px !important;
        font-family: 'Inter', -apple-system, sans-serif !important;
    }

    .notice-success, .updated {
        border-left-color: #27ae60 !important;
    }
    .notice-error, .error {
        border-left-color: #e74c3c !important;
    }
    .notice-warning {
        border-left-color: #f39c12 !important;
    }
    .notice-info {
        border-left-color: <?php echo $primary; ?> !important;
    }

    /* ─── WP BUTTONS: Rebrand ─── */
    .button-primary,
    .button.button-primary {
        background: <?php echo $primary; ?> !important;
        border-color: <?php echo $primary; ?> !important;
        color: #fff !important;
        text-shadow: none !important;
        box-shadow: none !important;
        border-radius: 6px !important;
    }
    .button-primary:hover,
    .button.button-primary:hover {
        background: <?php echo $primary_hover; ?> !important;
        border-color: <?php echo $primary_hover; ?> !important;
    }
    .button-primary:focus,
    .button.button-primary:focus {
        box-shadow: 0 0 0 3px <?php echo $primary; ?>33 !important;
    }

    /* Secondary buttons */
    .button, .button-secondary {
        border-radius: 6px !important;
    }
    .button:focus, .button-secondary:focus {
        border-color: <?php echo $primary; ?> !important;
        box-shadow: 0 0 0 1px <?php echo $primary; ?> !important;
    }

    /* ─── WP LINKS: Rebrand (exclude eProcurement sidebar) ─── */
    #wpbody a:not(.eproc-nav-item):not(.eproc-sidebar-user-link) {
        color: <?php echo $primary; ?>;
    }
    #wpbody a:not(.eproc-nav-item):not(.eproc-sidebar-user-link):hover {
        color: <?php echo $primary_hover; ?>;
    }

    /* ─── HIDE UPDATE BADGES & COUNTS ─── */
    .update-plugins, .update-count,
    #wp-admin-bar-updates, .plugin-count, .theme-count,
    div.update-nag, .notice-warning.update-message {
        display: none !important;
    }

    /* ─── ADMIN PAGES: Page title styling ─── */
    .wrap > h1,
    .wrap > h2 {
        font-family: 'Inter', -apple-system, sans-serif !important;
        font-weight: 600 !important;
        color: <?php echo SME_DARK; ?> !important;
    }

    /* ─── Scrollbar styling ─── */
    #wpwrap ::-webkit-scrollbar {
        width: 6px;
    }
    #wpwrap ::-webkit-scrollbar-track {
        background: transparent;
    }
    #wpwrap ::-webkit-scrollbar-thumb {
        background: <?php echo $primary; ?>33;
        border-radius: 3px;
    }
    #wpwrap ::-webkit-scrollbar-thumb:hover {
        background: <?php echo $primary; ?>66;
    }

    /* ─── WP DASHBOARD PAGE: Custom restyle ─── */
    .index-php #dashboard-widgets-wrap {
        padding: 20px;
    }

    /* Quick Links widget restyle */
    .postbox {
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        box-shadow: none !important;
    }
    .postbox .postbox-header,
    .postbox .hndle {
        border-bottom: 1px solid #e2e8f0 !important;
        font-family: 'Inter', -apple-system, sans-serif !important;
    }

    /* ─── WP LIST TABLES: Rebrand links ─── */
    .wp-list-table .row-actions a:hover,
    .wp-list-table .row-title:hover {
        color: <?php echo $primary; ?> !important;
    }

    /* Pagination active */
    .tablenav .tablenav-pages .current-page {
        border-color: <?php echo $primary; ?> !important;
    }

    /* Check/radio accent */
    input[type="checkbox"]:checked::before {
        color: <?php echo $primary; ?> !important;
    }

    /* ─── PROFILE PAGE ON NON-PROFILE.PHP ─── */
    .user-edit-php #your-profile,
    .profile-php #your-profile {
        font-family: 'Inter', -apple-system, sans-serif !important;
    }

    /* ─── ADMIN FOOTER: Align properly ─── */
    #wpfooter {
        padding: 12px 20px !important;
        text-align: center !important;
    }

    /* ─── FLOATING NAV BAR for WP native pages ─── */
    .sme-floating-nav {
        position: fixed;
        top: 0;
        left: 160px;
        right: 0;
        z-index: 99999;
        background: <?php echo $primary; ?>;
        padding: 10px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        font-family: 'Inter', -apple-system, sans-serif;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .sme-floating-nav a {
        color: #fff !important;
        text-decoration: none !important;
        font-size: 13px;
        font-weight: 500;
        padding: 6px 14px;
        border-radius: 6px;
        transition: background 0.2s;
    }
    .sme-floating-nav a:hover {
        background: rgba(255,255,255,0.15);
    }
    .sme-floating-nav .sme-nav-brand {
        font-weight: 700;
        font-size: 14px;
        color: #fff !important;
        margin-right: auto;
    }
    /* Push content down when floating nav is present */
    body.sme-has-floating-nav #wpcontent {
        padding-top: 50px !important;
    }
    body.sme-has-floating-nav #adminmenuwrap {
        padding-top: 46px !important;
    }
    </style>
    <?php
});

// ══════════════════════════════════════════
// 8b. FLOATING NAV BAR FOR WP NATIVE PAGES
// ══════════════════════════════════════════
// On pages like users.php, profile.php, etc. the eProcurement sidebar is absent.
// Show a fixed top navigation bar so users can navigate back.
add_action('in_admin_header', function() {
    // Only show on WP native pages (not on eProcurement plugin pages)
    $screen = get_current_screen();
    if ( ! $screen ) return;

    // eProcurement pages have screen IDs containing 'eprocurement'
    if ( strpos( $screen->id, 'eprocurement' ) !== false ) return;

    $eproc_url   = admin_url( 'admin.php?page=eprocurement' );
    $portal_slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
    $portal_url  = home_url( "/{$portal_slug}/" );

    echo '<script>document.body.classList.add("sme-has-floating-nav");</script>';
    echo '<div class="sme-floating-nav">';
    echo '<a href="' . esc_url( $eproc_url ) . '" class="sme-nav-brand">eProcurement</a>';
    echo '<a href="' . esc_url( $eproc_url ) . '">&larr; Back to eProcurement</a>';
    echo '<a href="' . esc_url( $portal_url ) . '" target="_blank">View Portal</a>';
    echo '</div>';
});

// ══════════════════════════════════════════
// 9. CLIENT FOOTER (Frontend)
// ══════════════════════════════════════════
add_action('wp_footer', function() {
    $brand     = sme_get_brand( 'name', SME_BRAND );
    $url       = sme_get_brand( 'url', SME_URL );
    $secondary = sme_get_brand( 'secondary', SME_NAVY );
    printf('<div style="text-align:center;padding:16px 0;background:%s;font-size:13px;color:rgba(255,255,255,0.7);font-family:-apple-system,sans-serif">Powered by <a href="%s" target="_blank" rel="noopener" style="color:#fff;text-decoration:none;font-weight:600">%s</a></div>',
        esc_attr( $secondary ), esc_url( $url ), esc_html( $brand ));
});

// ══════════════════════════════════════════
// 10. LOGIN PAGE — SUNSET CITYSCAPE
// ══════════════════════════════════════════
add_action('login_enqueue_scripts', function() {
    $logo          = sme_logo();
    $primary       = sme_get_brand( 'primary', SME_MAROON );
    $primary_light = sme_get_brand( 'primary_hover', SME_MAROON_L ); // lighter variant for focus states
    $secondary     = sme_get_brand( 'secondary', SME_NAVY );
    $secondary_lt  = sme_get_brand( 'secondary_light', SME_NAVY_L );
    $login_title   = sme_get_brand( 'login_title', 'Client Portal' );
    ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap');
        *{box-sizing:border-box}

        body.login{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:'Outfit',-apple-system,sans-serif!important;overflow:hidden;background:#060610}

        /* Sky */
        body.login::before{content:'';position:fixed;top:0;left:0;right:0;bottom:0;background:linear-gradient(180deg,#060610 0%,#0d0d24 15%,#141440 28%,<?php echo $secondary; ?> 38%,#2d1a3e 48%,#5a1a30 58%,<?php echo $primary; ?> 68%,<?php echo $primary_light; ?> 75%,#c4503a 82%,#d4784a 88%,#e8a060 93%,#f0c878 97%,#f8e8a0 100%);z-index:0}

        /* Sun glow */
        body.login::after{content:'';position:fixed;bottom:12%;left:50%;transform:translateX(-50%);width:300px;height:150px;background:radial-gradient(ellipse,rgba(248,232,160,.6) 0%,rgba(212,120,74,.3) 40%,transparent 70%);border-radius:50%;z-index:1;filter:blur(20px)}

        #sme-stars{position:fixed;top:0;left:0;right:0;height:50vh;z-index:1;overflow:hidden}
        #sme-stars span{position:absolute;background:rgba(255,255,255,.6);border-radius:50%;animation:sme-tw 3s infinite ease-in-out alternate}
        @keyframes sme-tw{0%{opacity:.2}100%{opacity:1}}
        #sme-haze{position:fixed;bottom:0;left:0;right:0;height:40vh;background:linear-gradient(0deg,rgba(6,6,16,.7) 0%,transparent 100%);z-index:3}
        #sme-city{position:fixed;bottom:0;left:0;right:0;height:35vh;z-index:2}
        #sme-city svg{width:100%;height:100%;display:block}

        /* Login container */
        #login{width:420px!important;padding:0!important;margin:0!important;position:relative;z-index:10}

        /* Logo */
        #login h1{margin:0 0 6px;padding:0;text-align:center}
        #login h1 a{
            <?php if($logo):?>background-image:url('<?php echo esc_url($logo);?>')!important;<?php endif;?>
            background-size:contain!important;background-repeat:no-repeat!important;background-position:center!important;
            width:240px!important;height:80px!important;display:block!important;margin:0 auto!important;text-indent:-9999px;outline:none;
            filter:drop-shadow(0 2px 12px rgba(0,0,0,.5))
        }
        #login h1::after{content:'<?php echo esc_attr( $login_title ); ?>';display:inline-block;margin-top:10px;padding:5px 20px;border:1px solid <?php echo $primary; ?>66;border-radius:20px;color:rgba(255,255,255,.6);font-size:10px;letter-spacing:5px;text-transform:uppercase;font-weight:500;background:<?php echo $primary; ?>1a;font-family:'Outfit',sans-serif}

        /* Card */
        .login form{
            background:linear-gradient(145deg,rgba(18,18,50,.75),rgba(10,10,30,.85))!important;
            border:1px solid rgba(255,255,255,.06)!important;border-radius:24px!important;
            box-shadow:0 30px 80px rgba(0,0,0,.5),inset 0 1px 0 rgba(255,255,255,.04)!important;
            padding:36px 32px 32px!important;margin-top:16px!important;
            backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
            position:relative;overflow:hidden
        }
        .login form::before{content:'';position:absolute;top:0;left:20%;right:20%;height:2px;background:linear-gradient(90deg,transparent,<?php echo $primary; ?>,<?php echo $secondary; ?>,transparent);border-radius:2px}

        /* Hide default labels */
        .login form label,.login label{color:transparent!important;font-size:0!important;height:0;overflow:hidden;display:block;margin:0!important;padding:0!important}

        /* Welcome text */
        #sme-welcome{text-align:center;margin-bottom:28px}
        #sme-welcome h2{color:#fff;font-size:22px;font-weight:600;letter-spacing:.5px;margin:0 0 6px;font-family:'Outfit',sans-serif}
        #sme-welcome p{color:rgba(255,255,255,.45);font-size:13px;font-weight:300;margin:0;font-family:'Outfit',sans-serif}

        /* Input wrapper */
        .input,.login form .input,
        .login form input[type="text"],
        .login form input[type="password"]{
            width:100%!important;background:rgba(255,255,255,.04)!important;
            border:1.5px solid rgba(255,255,255,.08)!important;color:#fff!important;
            border-radius:14px!important;padding:16px 16px 16px 50px!important;
            font-size:14px!important;font-family:'Outfit',sans-serif!important;
            height:auto!important;transition:all .3s ease!important;outline:none!important;
            letter-spacing:.3px;margin-bottom:4px!important
        }
        .login form input:focus{border-color:<?php echo $primary; ?>99!important;background:rgba(255,255,255,.06)!important;box-shadow:0 0 0 4px <?php echo $primary; ?>1a,0 0 30px <?php echo $primary; ?>0f!important}
        .login form input::placeholder{color:rgba(255,255,255,.25)!important;font-weight:300}

        /* Input icons */
        .sme-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.3);pointer-events:none;z-index:2;transition:color .3s}
        .sme-input-wrap{position:relative;margin-bottom:18px}
        .sme-input-wrap:focus-within .sme-icon{color:<?php echo $primary_light; ?>}

        /* Remember me */
        .login .forgetmenot{display:none!important}
        .login .submit{display:none!important}
        #sme-options{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
        .sme-check{display:flex;align-items:center;gap:10px;cursor:pointer}
        .sme-check input{display:none}
        .sme-checkbox{width:20px;height:20px;border:1.5px solid rgba(255,255,255,.15);border-radius:6px;display:flex;align-items:center;justify-content:center;transition:all .2s;background:rgba(255,255,255,.03)}
        .sme-check input:checked~.sme-checkbox{background:linear-gradient(135deg,<?php echo $primary; ?>,<?php echo $secondary; ?>);border-color:transparent;box-shadow:0 2px 8px <?php echo $primary; ?>4d}
        .sme-checkbox svg{opacity:0;transform:scale(.5);transition:all .2s}
        .sme-check input:checked~.sme-checkbox svg{opacity:1;transform:scale(1)}
        .sme-check-label{color:rgba(255,255,255,.5);font-size:13px;font-family:'Outfit',sans-serif}
        .sme-forgot{color:rgba(255,255,255,.4);font-size:12px;text-decoration:none;transition:color .2s;font-family:'Outfit',sans-serif}
        .sme-forgot:hover{color:<?php echo $primary_light; ?>}

        /* Submit button */
        #sme-submit{
            width:100%;background:linear-gradient(135deg,<?php echo $primary; ?> 0%,#4a1a5e 50%,<?php echo $secondary; ?> 100%);
            border:none;border-radius:14px;color:#fff;font-weight:600;font-size:15px;
            padding:16px 28px;font-family:'Outfit',sans-serif;cursor:pointer;
            transition:all .4s ease;letter-spacing:2px;text-transform:uppercase;
            position:relative;overflow:hidden;display:block;margin-bottom:0
        }
        #sme-submit::before{content:'';position:absolute;top:0;left:-100%;right:0;bottom:0;width:300%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.1),transparent);transition:left .6s ease}
        #sme-submit:hover::before{left:100%}
        #sme-submit:hover{box-shadow:0 10px 40px <?php echo $primary; ?>66;transform:translateY(-2px)}

        /* Divider */
        .sme-divider{display:flex;align-items:center;margin:24px 0;gap:16px}
        .sme-divider-line{flex:1;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.08),transparent)}
        .sme-divider-dot{width:4px;height:4px;background:<?php echo $primary; ?>80;border-radius:50%}

        /* Back link */
        .sme-back{display:flex;align-items:center;justify-content:center;gap:8px;color:rgba(255,255,255,.4);text-decoration:none;font-size:13px;transition:all .2s;font-family:'Outfit',sans-serif}
        .sme-back:hover{color:rgba(255,255,255,.8)}
        .sme-back:hover svg{transform:translateX(-3px)}
        .sme-back svg{transition:transform .2s}

        /* Hide default WP links */
        .login #backtoblog,.login #nav{display:none!important}

        /* Support */
        #sme-support{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:24px;padding:12px 16px;background:rgba(18,18,42,.5);border:1px solid rgba(255,255,255,.06);border-radius:14px;color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;transition:all .2s;font-family:'Outfit',sans-serif;backdrop-filter:blur(8px)}
        #sme-support:hover{border-color:<?php echo $primary; ?>66;color:rgba(255,255,255,.8);background:<?php echo $primary; ?>14}

        /* Footer */
        #sme-footer{text-align:center;margin-top:18px;color:rgba(255,255,255,.35);font-size:11px;letter-spacing:.5px;font-family:'Outfit',sans-serif;text-shadow:0 1px 4px rgba(0,0,0,.4)}
        #sme-footer a{color:rgba(255,255,255,.5);text-decoration:none}
        #sme-footer a:hover{color:rgba(255,255,255,.8)}

        /* Error */
        .login #login_error{background:rgba(220,53,69,.08)!important;border:1px solid rgba(220,53,69,.2)!important;border-left:1px solid rgba(220,53,69,.2)!important;border-radius:12px!important;padding:14px 18px!important;color:#ff8a8a!important;font-family:'Outfit',sans-serif!important;font-size:13px!important;animation:sme-shake .4s ease;backdrop-filter:blur(10px)}
        .login #login_error a{color:#ff8a8a!important}
        .login .message,.login .success{border:1px solid <?php echo $primary; ?>4d!important;border-left:1px solid <?php echo $primary; ?>4d!important;background:<?php echo $secondary; ?>e6!important;color:#e8e8f0!important;border-radius:12px!important;padding:14px 18px!important;font-family:'Outfit',sans-serif!important;box-shadow:none!important}
        @keyframes sme-shake{0%,100%{transform:translateX(0)}20%{transform:translateX(-6px)}40%{transform:translateX(6px)}60%{transform:translateX(-4px)}80%{transform:translateX(4px)}}

        .language-switcher{display:none!important}
    </style>
    <?php
});

add_action('login_footer', function() {
    $email = sme_get_brand( 'email', SME_EMAIL );
    $url   = sme_get_brand( 'url', SME_URL );
    $brand = sme_get_brand( 'name', SME_BRAND );
    ?>
    <div id="sme-stars"></div>
    <div id="sme-haze"></div>
    <div id="sme-city">
    <svg viewBox="0 0 1600 400" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <g fill="#08081a" opacity=".6"><rect x="50" y="180" width="60" height="220"/><rect x="130" y="200" width="45" height="200"/><rect x="200" y="160" width="55" height="240"/><rect x="400" y="190" width="50" height="210"/><rect x="520" y="170" width="40" height="230"/><rect x="900" y="185" width="55" height="215"/><rect x="1050" y="195" width="45" height="205"/><rect x="1200" y="175" width="50" height="225"/><rect x="1350" y="200" width="60" height="200"/><rect x="1480" y="180" width="45" height="220"/></g>
        <g fill="#060612"><rect x="80" y="220" width="70" height="180"/><rect x="160" y="180" width="50" height="220"/><rect x="220" y="240" width="65" height="160"/><rect x="300" y="200" width="40" height="200"/><rect x="350" y="160" width="55" height="240"/><rect x="350" y="155" width="55" height="10" rx="2"/><rect x="620" y="100" width="60" height="300"/><rect x="630" y="90" width="40" height="15" rx="3"/><rect x="645" y="60" width="10" height="30"/><rect x="580" y="200" width="40" height="200"/><rect x="690" y="220" width="50" height="180"/><rect x="850" y="190" width="55" height="210"/><rect x="920" y="150" width="65" height="250"/><rect x="920" y="145" width="65" height="8" rx="2"/><rect x="1000" y="230" width="45" height="170"/><rect x="1100" y="170" width="50" height="230"/><rect x="1160" y="210" width="60" height="190"/><rect x="1240" y="180" width="45" height="220"/><rect x="1300" y="240" width="55" height="160"/><rect x="1400" y="200" width="70" height="200"/><rect x="1400" y="195" width="70" height="8" rx="2"/><rect x="1490" y="230" width="50" height="170"/><rect x="1550" y="210" width="50" height="190"/></g>
        <g fill="#040410"><rect x="0" y="280" width="90" height="120"/><rect x="100" y="260" width="80" height="140"/><rect x="270" y="270" width="60" height="130"/><rect x="440" y="250" width="75" height="150"/><rect x="530" y="280" width="55" height="120"/><rect x="740" y="260" width="70" height="140"/><rect x="830" y="280" width="50" height="120"/><rect x="1040" y="265" width="65" height="135"/><rect x="1280" y="270" width="60" height="130"/><rect x="1440" y="280" width="70" height="120"/></g>
        <g fill="#f8e8a0" opacity=".7"><rect x="635" y="115" width="4" height="4" rx="1"/><rect x="645" y="115" width="4" height="4" rx="1"/><rect x="660" y="115" width="4" height="4" rx="1"/><rect x="635" y="130" width="4" height="4" rx="1"/><rect x="655" y="130" width="4" height="4" rx="1"/><rect x="645" y="150" width="4" height="4" rx="1"/><rect x="665" y="150" width="4" height="4" rx="1"/><rect x="635" y="170" width="4" height="4" rx="1"/><rect x="660" y="170" width="4" height="4" rx="1"/><rect x="645" y="190" width="4" height="4" rx="1"/><rect x="170" y="200" width="3" height="3" rx="1"/><rect x="180" y="200" width="3" height="3" rx="1"/><rect x="365" y="180" width="3" height="3" rx="1"/><rect x="375" y="180" width="3" height="3" rx="1"/><rect x="935" y="170" width="3" height="3" rx="1"/><rect x="945" y="170" width="3" height="3" rx="1"/><rect x="1115" y="190" width="3" height="3" rx="1"/><rect x="1415" y="220" width="3" height="3" rx="1"/><rect x="1430" y="220" width="3" height="3" rx="1"/></g>
        <rect x="0" y="380" width="1600" height="20" fill="#030308"/>
    </svg>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Stars
        var st=document.getElementById('sme-stars');
        for(var i=0;i<50;i++){var s=document.createElement('span');s.style.left=Math.random()*100+'%';s.style.top=Math.random()*100+'%';var z=(Math.random()*2+1)+'px';s.style.width=z;s.style.height=z;s.style.animationDelay=(Math.random()*3)+'s';s.style.animationDuration=(Math.random()*2+2)+'s';st.appendChild(s);}

        var form=document.querySelector('#loginform');
        if(!form) return;

        // Welcome text
        var w=document.createElement('div');w.id='sme-welcome';
        w.innerHTML='<h2>Welcome back</h2><p>Sign in to manage your website</p>';
        form.insertBefore(w,form.firstChild);

        // Wrap inputs with icons
        var user=document.getElementById('user_login');
        var pass=document.getElementById('user_pass');

        if(user){
            var uw=document.createElement('div');uw.className='sme-input-wrap';
            user.parentNode.insertBefore(uw,user);uw.appendChild(user);
            user.placeholder='Username or email';
            var ui=document.createElement('div');ui.className='sme-icon';
            ui.innerHTML='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
            uw.appendChild(ui);
        }

        if(pass){
            var pw=document.createElement('div');pw.className='sme-input-wrap';
            pass.parentNode.insertBefore(pw,pass);pw.appendChild(pass);
            pass.placeholder='Password';
            var pi=document.createElement('div');pi.className='sme-icon';
            pi.innerHTML='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
            pw.appendChild(pi);
            var wpt=pass.parentNode.querySelector('.wp-hide-pw');
            if(wpt)wpt.style.display='none';
        }

        // Custom options row
        var op=document.createElement('div');op.id='sme-options';
        var forgotHref = document.querySelector('.login #nav a');
        op.innerHTML='<label class="sme-check"><input type="checkbox" name="rememberme" value="forever" checked><div class="sme-checkbox"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg></div><span class="sme-check-label">Remember me</span></label><a href="'+(forgotHref ? forgotHref.href : '#')+'" class="sme-forgot">Forgot password?</a>';
        form.appendChild(op);

        // Custom submit
        var btn=document.createElement('button');btn.id='sme-submit';btn.type='submit';
        btn.innerHTML='Sign In <span style="margin-left:10px;transition:transform .3s;display:inline-block">&rarr;</span>';
        form.appendChild(btn);

        // Divider + back link
        var dv=document.createElement('div');dv.className='sme-divider';
        dv.innerHTML='<div class="sme-divider-line"></div><div class="sme-divider-dot"></div><div class="sme-divider-line"></div>';
        form.appendChild(dv);

        var bk=document.createElement('a');bk.className='sme-back';bk.href='<?php echo esc_url(home_url('/')); ?>';
        bk.innerHTML='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>Back to website';
        form.appendChild(bk);

        // Support + footer outside form
        var el=document.getElementById('login');
        var sp=document.createElement('a');sp.id='sme-support';
        sp.href='mailto:<?php echo esc_attr( $email );?>?subject=Website Support';
        sp.innerHTML='<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Need help? <strong><?php echo esc_html( $email );?></strong>';
        el.appendChild(sp);
        var ft=document.createElement('div');ft.id='sme-footer';
        ft.innerHTML='Powered by <a href="<?php echo esc_url( $url );?>" target="_blank"><?php echo esc_html( $brand );?></a>';
        el.appendChild(ft);
    });
    </script>
<?php });

add_filter('login_headerurl', function() { return home_url('/'); });
add_filter('login_headertext', function() { return get_bloginfo('name'); });

// ══════════════════════════════════════════
// 11. IMAGE COMPRESSION
// ══════════════════════════════════════════
add_filter('wp_handle_upload', function($file) {
    if(!isset($file['file'])) return $file;
    $p=$file['file'];$t=$file['type']??'';
    if(!in_array($t,array('image/jpeg','image/png','image/webp'))) return $file;
    $info=getimagesize($p);if(!$info) return $file;
    $w=$info[0];$h=$info[1];$mx=1920;
    if($w<=$mx&&filesize($p)<200000) return $file;
    $nw=$w;$nh=$h;if($w>$mx){$r=$mx/$w;$nw=$mx;$nh=round($h*$r);}
    switch($t){case 'image/jpeg':$img=@imagecreatefromjpeg($p);break;case 'image/png':$img=@imagecreatefrompng($p);break;case 'image/webp':$img=@imagecreatefromwebp($p);break;default:return $file;}
    if(!$img) return $file;
    if($nw!==$w||$nh!==$h){$rs=imagecreatetruecolor($nw,$nh);if($t==='image/png'){imagealphablending($rs,false);imagesavealpha($rs,true);imagefilledrectangle($rs,0,0,$nw,$nh,imagecolorallocatealpha($rs,0,0,0,127));}imagecopyresampled($rs,$img,0,0,0,0,$nw,$nh,$w,$h);imagedestroy($img);$img=$rs;}
    switch($t){case 'image/jpeg':imagejpeg($img,$p,80);break;case 'image/png':imagesavealpha($img,true);imagepng($img,$p,6);break;case 'image/webp':imagewebp($img,$p,80);break;}
    imagedestroy($img);return $file;
});

add_filter('wp_generate_attachment_metadata', function($m,$id) {
    if(!isset($m['sizes'])) return $m;
    $d=trailingslashit(wp_upload_dir()['basedir']);$s=isset($m['file'])?trailingslashit(dirname($m['file'])):'';
    foreach($m['sizes'] as $data){$p=$d.$s.$data['file'];if(!file_exists($p))continue;$t=$data['mime-type']??'';
        switch($t){case 'image/jpeg':$i=@imagecreatefromjpeg($p);if($i){imagejpeg($i,$p,80);imagedestroy($i);}break;case 'image/png':$i=@imagecreatefrompng($p);if($i){imagesavealpha($i,true);imagepng($i,$p,6);imagedestroy($i);}break;case 'image/webp':$i=@imagecreatefromwebp($p);if($i){imagewebp($i,$p,80);imagedestroy($i);}break;}}
    return $m;
}, 10, 2);

// ══════════════════════════════════════════
// 12. SECURITY HARDENING
// ══════════════════════════════════════════
add_filter('xmlrpc_enabled', '__return_false');
add_filter('wp_headers', function($h) { unset($h['X-Pingback']); return $h; });
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');
add_filter('style_loader_src', function($s) { return $s ? remove_query_arg('ver', $s) : $s; }, 999);
add_filter('script_loader_src', function($s) { return $s ? remove_query_arg('ver', $s) : $s; }, 999);
if (!defined('DISALLOW_FILE_EDIT')) define('DISALLOW_FILE_EDIT', true);
