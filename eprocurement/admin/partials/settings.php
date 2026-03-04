<?php
/**
 * Plugin settings page partial.
 *
 * Cloud storage configuration, retention settings, notification toggles.
 * Email section reorganized: registration verification always on,
 * other notifications toggleable.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Branding settings
$brand_name      = Eprocurement_Branding::brand_name();
$brand_url       = Eprocurement_Branding::brand_url();
$support_email   = Eprocurement_Branding::support_email();
$brand_logo      = get_option( 'eprocurement_brand_logo', '' );
$login_title     = Eprocurement_Branding::login_title();
$brand_colors    = Eprocurement_Branding::get_colors();

// Current settings
$cloud_provider   = get_option( 'eprocurement_cloud_provider', '' );
$retention_days   = get_option( 'eprocurement_closed_bid_retention_days', '' );
$compliance_title = Eprocurement_Compliance_Docs::get_section_title();
$frontend_slug    = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

// Notification settings
$notify_settings = get_option( 'eprocurement_notification_settings', '' );
if ( is_string( $notify_settings ) ) {
    $notify_settings = json_decode( $notify_settings, true );
}
if ( ! is_array( $notify_settings ) ) {
    $notify_settings = [];
}

// Check for OAuth callback messages
$oauth_success = isset( $_GET['oauth_success'] );
$oauth_error   = sanitize_text_field( $_GET['oauth_error'] ?? '' );

// OAuth redirect URI (for cloud providers)
$oauth_redirect_base = admin_url( 'admin.php' );

$providers = [
    'google_drive' => __( 'Google Drive', 'eprocurement' ),
    'onedrive'     => __( 'OneDrive', 'eprocurement' ),
    'dropbox'      => __( 'Dropbox', 'eprocurement' ),
    's3'           => __( 'Amazon S3 / S3-Compatible', 'eprocurement' ),
];
?>
<div class="eproc-wrap">
    <h1><?php esc_html_e( 'Settings', 'eprocurement' ); ?></h1>

    <?php if ( $oauth_success ) : ?>
        <div class="eproc-notice success">
            <p><?php esc_html_e( 'Cloud storage connected successfully!', 'eprocurement' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( $oauth_error ) : ?>
        <div class="eproc-notice error">
            <p><?php echo esc_html( sprintf( __( 'OAuth error: %s', 'eprocurement' ), $oauth_error ) ); ?></p>
        </div>
    <?php endif; ?>

    <div id="eproc-settings-notices"></div>

    <form id="eproc-settings-form" method="post" class="eproc-settings-container">
        <?php wp_nonce_field( 'eproc_admin_nonce', 'eproc_settings_nonce' ); ?>

        <!-- Branding -->
        <div class="eproc-card">
            <div class="eproc-card-header">
                <h2><?php esc_html_e( 'Branding', 'eprocurement' ); ?></h2>
            </div>
            <div class="eproc-card-body">
                <p class="eproc-text-muted eproc-text-sm" style="margin-bottom:16px;">
                    <?php esc_html_e( 'Customise the brand identity shown across the admin panel, frontend portal, emails, and login page.', 'eprocurement' ); ?>
                </p>

                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label for="brand_name"><?php esc_html_e( 'Brand Name', 'eprocurement' ); ?></label>
                        <input type="text" id="brand_name" name="brand_name"
                               value="<?php echo esc_attr( $brand_name ); ?>"
                               placeholder="MyBliss Technologies">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Organisation name displayed in headers, emails, and the sidebar.', 'eprocurement' ); ?></p>
                    </div>
                    <div class="eproc-form-group">
                        <label for="brand_url"><?php esc_html_e( 'Brand URL', 'eprocurement' ); ?></label>
                        <input type="url" id="brand_url" name="brand_url"
                               value="<?php echo esc_attr( $brand_url ); ?>"
                               placeholder="https://www.myblisstech.com">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Organisation website. Used for logo link and email footers.', 'eprocurement' ); ?></p>
                    </div>
                </div>

                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label for="support_email"><?php esc_html_e( 'Support Email', 'eprocurement' ); ?></label>
                        <input type="email" id="support_email" name="support_email"
                               value="<?php echo esc_attr( $support_email ); ?>"
                               placeholder="support@myblisstech.com">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Email address shown to bidders for support queries.', 'eprocurement' ); ?></p>
                    </div>
                    <div class="eproc-form-group">
                        <label for="login_title"><?php esc_html_e( 'Login Page Title', 'eprocurement' ); ?></label>
                        <input type="text" id="login_title" name="login_title"
                               value="<?php echo esc_attr( $login_title ); ?>"
                               placeholder="Client Portal">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Heading displayed on the WordPress login page.', 'eprocurement' ); ?></p>
                    </div>
                </div>

                <div class="eproc-form-row">
                    <div class="eproc-form-group">
                        <label for="brand_logo"><?php esc_html_e( 'Brand Logo', 'eprocurement' ); ?></label>
                        <input type="url" id="brand_logo" name="brand_logo"
                               value="<?php echo esc_attr( $brand_logo ); ?>"
                               placeholder="<?php esc_attr_e( 'https://example.com/logo.png  or  media attachment ID', 'eprocurement' ); ?>">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Full URL to the logo image, or a WordPress media attachment ID. Leave blank to use the default logo.', 'eprocurement' ); ?></p>
                    </div>
                </div>

                <hr class="eproc-divider">

                <h3 style="margin-bottom:12px;"><?php esc_html_e( 'Brand Colors', 'eprocurement' ); ?></h3>
                <p class="eproc-text-muted eproc-text-sm" style="margin-bottom:16px;">
                    <?php esc_html_e( 'Override the default colour scheme. Changes apply to buttons, links, the sidebar, and all accent elements.', 'eprocurement' ); ?>
                </p>

                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label for="color_primary"><?php esc_html_e( 'Primary Color', 'eprocurement' ); ?></label>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <input type="color" id="color_primary" name="color_primary"
                                   value="<?php echo esc_attr( $brand_colors['eproc-primary'] ); ?>"
                                   style="width:48px;height:36px;padding:2px;cursor:pointer;">
                            <code id="color_primary_hex"><?php echo esc_html( $brand_colors['eproc-primary'] ); ?></code>
                        </div>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Main brand colour for buttons, links, and accents.', 'eprocurement' ); ?></p>
                    </div>
                    <div class="eproc-form-group">
                        <label for="color_secondary"><?php esc_html_e( 'Secondary Color', 'eprocurement' ); ?></label>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <input type="color" id="color_secondary" name="color_secondary"
                                   value="<?php echo esc_attr( $brand_colors['eproc-secondary'] ); ?>"
                                   style="width:48px;height:36px;padding:2px;cursor:pointer;">
                            <code id="color_secondary_hex"><?php echo esc_html( $brand_colors['eproc-secondary'] ); ?></code>
                        </div>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Sidebar background and secondary accents.', 'eprocurement' ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- General Settings -->
        <div class="eproc-card">
            <div class="eproc-card-header">
                <h2><?php esc_html_e( 'General Settings', 'eprocurement' ); ?></h2>
            </div>
            <div class="eproc-card-body">
                <div class="eproc-form-row">
                    <div class="eproc-form-group">
                        <label for="frontend_page_slug"><?php esc_html_e( 'Frontend Page Slug', 'eprocurement' ); ?></label>
                        <div class="eproc-input-with-prefix">
                            <span class="eproc-input-prefix"><?php echo esc_html( home_url( '/' ) ); ?></span>
                            <input type="text" id="frontend_page_slug" name="frontend_page_slug"
                                   value="<?php echo esc_attr( $frontend_slug ); ?>">
                        </div>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'URL slug for the public-facing procurement page.', 'eprocurement' ); ?></p>
                    </div>
                </div>
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label for="bid_heading"><?php esc_html_e( 'Main Page Heading', 'eprocurement' ); ?></label>
                        <input type="text" id="bid_heading" name="bid_heading"
                               value="<?php echo esc_attr( get_option( 'eprocurement_bid_heading', __( 'Tenders & Bids', 'eprocurement' ) ) ); ?>"
                               placeholder="<?php esc_attr_e( 'Tenders & Bids', 'eprocurement' ); ?>">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Hero heading on the main tender listing page. Change per tenant.', 'eprocurement' ); ?></p>
                    </div>
                    <div class="eproc-form-group">
                        <label for="closed_bid_retention_days"><?php esc_html_e( 'Closed Bid Retention (days)', 'eprocurement' ); ?></label>
                        <input type="number" id="closed_bid_retention_days" name="closed_bid_retention_days" min="0"
                               value="<?php echo esc_attr( $retention_days ); ?>"
                               placeholder="&#8734; (forever)">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Closed bids will be automatically archived after this many days. Leave blank to keep forever.', 'eprocurement' ); ?></p>
                    </div>
                </div>
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label for="compliance_section_title"><?php esc_html_e( 'SCM Documents Section Title', 'eprocurement' ); ?></label>
                        <input type="text" id="compliance_section_title" name="compliance_section_title"
                               value="<?php echo esc_attr( $compliance_title ); ?>">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Title shown above the SCM documents section on the frontend.', 'eprocurement' ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bid Categories -->
        <div class="eproc-card">
            <div class="eproc-card-header">
                <h2><?php esc_html_e( 'Bid Categories', 'eprocurement' ); ?></h2>
            </div>
            <div class="eproc-card-body">
                <p class="eproc-text-muted eproc-text-sm" style="margin-bottom:16px;">
                    <?php esc_html_e( 'Enable additional bid categories. Each enabled category gets its own sidebar navigation item and simplified bid workflow (no Key Dates or Contact Person fields).', 'eprocurement' ); ?>
                </p>
                <div class="eproc-notification-item">
                    <div class="eproc-notification-info">
                        <label>
                            <input type="checkbox" name="category_briefing_register" value="1"
                                   <?php checked( get_option( 'eprocurement_category_briefing_register', '0' ), '1' ); ?>>
                            <strong><?php esc_html_e( 'Briefing Register', 'eprocurement' ); ?></strong>
                        </label>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Track briefing session records linked to bid numbers.', 'eprocurement' ); ?></p>
                    </div>
                </div>
                <div class="eproc-notification-item">
                    <div class="eproc-notification-info">
                        <label>
                            <input type="checkbox" name="category_closing_register" value="1"
                                   <?php checked( get_option( 'eprocurement_category_closing_register', '0' ), '1' ); ?>>
                            <strong><?php esc_html_e( 'Closing Register', 'eprocurement' ); ?></strong>
                        </label>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Track bid closing records and outcomes.', 'eprocurement' ); ?></p>
                    </div>
                </div>
                <div class="eproc-notification-item">
                    <div class="eproc-notification-info">
                        <label>
                            <input type="checkbox" name="category_appointments" value="1"
                                   <?php checked( get_option( 'eprocurement_category_appointments', '0' ), '1' ); ?>>
                            <strong><?php esc_html_e( 'Appointments', 'eprocurement' ); ?></strong>
                        </label>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Track appointment records related to procurement processes.', 'eprocurement' ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Notifications -->
        <div class="eproc-card">
            <div class="eproc-card-header">
                <h2><?php esc_html_e( 'Email Notifications', 'eprocurement' ); ?></h2>
            </div>
            <div class="eproc-card-body">
                <!-- Always-on notification -->
                <div class="eproc-notification-item eproc-notification-always-on">
                    <div class="eproc-notification-info">
                        <strong><?php esc_html_e( 'Registration Verification', 'eprocurement' ); ?></strong>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Email sent to new bidders to verify their email address. This is always enabled and cannot be turned off.', 'eprocurement' ); ?></p>
                    </div>
                    <span class="eproc-badge verified"><?php esc_html_e( 'Always On', 'eprocurement' ); ?></span>
                </div>

                <hr class="eproc-divider">

                <!-- Toggleable notifications -->
                <div class="eproc-notification-item">
                    <div class="eproc-notification-info">
                        <label>
                            <input type="checkbox" name="notify_new_bid" value="1"
                                   <?php checked( ! empty( $notify_settings['new_bid_notify_bidders'] ) ); ?>>
                            <strong><?php esc_html_e( 'New Bid Opened', 'eprocurement' ); ?></strong>
                        </label>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Notify all verified bidders when a new bid is opened.', 'eprocurement' ); ?></p>
                    </div>
                </div>

                <div class="eproc-notification-item">
                    <div class="eproc-notification-info">
                        <label>
                            <input type="checkbox" name="notify_query" value="1"
                                   <?php checked( ! empty( $notify_settings['query_notify_contact'] ) ); ?>>
                            <strong><?php esc_html_e( 'New Query Submitted', 'eprocurement' ); ?></strong>
                        </label>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Notify the assigned contact person when a bidder submits a new query.', 'eprocurement' ); ?></p>
                    </div>
                </div>

                <div class="eproc-notification-item">
                    <div class="eproc-notification-info">
                        <label>
                            <input type="checkbox" name="notify_reply" value="1"
                                   <?php checked( ! empty( $notify_settings['reply_notify_bidder'] ) ); ?>>
                            <strong><?php esc_html_e( 'Query Reply', 'eprocurement' ); ?></strong>
                        </label>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Notify the bidder when a staff member replies to their query.', 'eprocurement' ); ?></p>
                    </div>
                </div>

                <div class="eproc-notification-item">
                    <div class="eproc-notification-info">
                        <label>
                            <input type="checkbox" name="notify_status" value="1"
                                   <?php checked( ! empty( $notify_settings['status_change_notify'] ) ); ?>>
                            <strong><?php esc_html_e( 'Status Change', 'eprocurement' ); ?></strong>
                        </label>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Notify SCM Managers when a bid status changes.', 'eprocurement' ); ?></p>
                    </div>
                </div>

                <hr class="eproc-divider">

                <!-- Future: Weekly Digest -->
                <div class="eproc-notification-item eproc-notification-disabled">
                    <div class="eproc-notification-info">
                        <strong><?php esc_html_e( 'Weekly Digest Report', 'eprocurement' ); ?></strong>
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Summary of all activity sent to administrators weekly.', 'eprocurement' ); ?></p>
                    </div>
                    <span class="eproc-badge unverified"><?php esc_html_e( 'Coming Soon', 'eprocurement' ); ?></span>
                </div>
            </div>
        </div>

        <!-- Cloud Storage -->
        <div class="eproc-card">
            <div class="eproc-card-header">
                <h2><?php esc_html_e( 'Cloud Storage', 'eprocurement' ); ?></h2>
            </div>
            <div class="eproc-card-body">
                <div class="eproc-form-group">
                    <label for="cloud_provider"><?php esc_html_e( 'Storage Provider', 'eprocurement' ); ?></label>
                    <select id="cloud_provider" name="cloud_provider">
                        <option value=""><?php esc_html_e( '-- Select Provider --', 'eprocurement' ); ?></option>
                        <?php foreach ( $providers as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $cloud_provider, $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Google Drive Credentials -->
                <div id="eproc-creds-google_drive" class="eproc-provider-creds eproc-provider-section">
                    <h3><?php esc_html_e( 'Google Drive Configuration', 'eprocurement' ); ?></h3>
                    <div class="eproc-grid-2">
                        <div class="eproc-form-group">
                            <label for="gd_client_id"><?php esc_html_e( 'Client ID', 'eprocurement' ); ?></label>
                            <input type="text" id="gd_client_id" name="cloud_credentials[client_id]" autocomplete="off">
                        </div>
                        <div class="eproc-form-group">
                            <label for="gd_client_secret"><?php esc_html_e( 'Client Secret', 'eprocurement' ); ?></label>
                            <input type="password" id="gd_client_secret" name="cloud_credentials[client_secret]" autocomplete="off">
                        </div>
                    </div>
                    <div class="eproc-form-group">
                        <label for="gd_folder_id"><?php esc_html_e( 'Folder ID', 'eprocurement' ); ?></label>
                        <input type="text" id="gd_folder_id" name="cloud_credentials[folder_id]" autocomplete="off">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'The Google Drive folder ID where files will be stored.', 'eprocurement' ); ?></p>
                    </div>
                    <div class="eproc-form-group">
                        <label><?php esc_html_e( 'Authorization', 'eprocurement' ); ?></label>
                        <button type="button" class="eproc-btn eproc-connect-oauth" data-provider="google_drive">
                            <?php esc_html_e( 'Connect Google Drive', 'eprocurement' ); ?>
                        </button>
                        <p class="eproc-text-muted eproc-text-sm">
                            <?php esc_html_e( 'Redirect URI:', 'eprocurement' ); ?>
                            <code><?php echo esc_html( add_query_arg( 'eproc_oauth_callback', 'google_drive', $oauth_redirect_base ) ); ?></code>
                        </p>
                    </div>
                </div>

                <!-- OneDrive Credentials -->
                <div id="eproc-creds-onedrive" class="eproc-provider-creds eproc-provider-section">
                    <h3><?php esc_html_e( 'OneDrive Configuration', 'eprocurement' ); ?></h3>
                    <div class="eproc-grid-2">
                        <div class="eproc-form-group">
                            <label for="od_client_id"><?php esc_html_e( 'Client ID', 'eprocurement' ); ?></label>
                            <input type="text" id="od_client_id" name="cloud_credentials[client_id]" autocomplete="off">
                        </div>
                        <div class="eproc-form-group">
                            <label for="od_client_secret"><?php esc_html_e( 'Client Secret', 'eprocurement' ); ?></label>
                            <input type="password" id="od_client_secret" name="cloud_credentials[client_secret]" autocomplete="off">
                        </div>
                    </div>
                    <div class="eproc-form-group">
                        <label for="od_folder_path"><?php esc_html_e( 'Folder Path', 'eprocurement' ); ?></label>
                        <input type="text" id="od_folder_path" name="cloud_credentials[folder_path]" autocomplete="off"
                               placeholder="<?php esc_attr_e( '/eprocurement', 'eprocurement' ); ?>">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Path in OneDrive where files will be stored.', 'eprocurement' ); ?></p>
                    </div>
                    <div class="eproc-form-group">
                        <label><?php esc_html_e( 'Authorization', 'eprocurement' ); ?></label>
                        <button type="button" class="eproc-btn eproc-connect-oauth" data-provider="onedrive">
                            <?php esc_html_e( 'Connect OneDrive', 'eprocurement' ); ?>
                        </button>
                        <p class="eproc-text-muted eproc-text-sm">
                            <?php esc_html_e( 'Redirect URI:', 'eprocurement' ); ?>
                            <code><?php echo esc_html( add_query_arg( 'eproc_oauth_callback', 'onedrive', $oauth_redirect_base ) ); ?></code>
                        </p>
                    </div>
                </div>

                <!-- Dropbox Credentials -->
                <div id="eproc-creds-dropbox" class="eproc-provider-creds eproc-provider-section">
                    <h3><?php esc_html_e( 'Dropbox Configuration', 'eprocurement' ); ?></h3>
                    <div class="eproc-grid-2">
                        <div class="eproc-form-group">
                            <label for="db_app_key"><?php esc_html_e( 'App Key', 'eprocurement' ); ?></label>
                            <input type="text" id="db_app_key" name="cloud_credentials[app_key]" autocomplete="off">
                        </div>
                        <div class="eproc-form-group">
                            <label for="db_app_secret"><?php esc_html_e( 'App Secret', 'eprocurement' ); ?></label>
                            <input type="password" id="db_app_secret" name="cloud_credentials[app_secret]" autocomplete="off">
                        </div>
                    </div>
                    <div class="eproc-form-group">
                        <label for="db_folder_path"><?php esc_html_e( 'Folder Path', 'eprocurement' ); ?></label>
                        <input type="text" id="db_folder_path" name="cloud_credentials[folder_path]" autocomplete="off"
                               placeholder="<?php esc_attr_e( '/eprocurement', 'eprocurement' ); ?>">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Folder in Dropbox where files will be stored.', 'eprocurement' ); ?></p>
                    </div>
                    <div class="eproc-form-group">
                        <label><?php esc_html_e( 'Authorization', 'eprocurement' ); ?></label>
                        <button type="button" class="eproc-btn eproc-connect-oauth" data-provider="dropbox">
                            <?php esc_html_e( 'Connect Dropbox', 'eprocurement' ); ?>
                        </button>
                        <p class="eproc-text-muted eproc-text-sm">
                            <?php esc_html_e( 'Redirect URI:', 'eprocurement' ); ?>
                            <code><?php echo esc_html( add_query_arg( 'eproc_oauth_callback', 'dropbox', $oauth_redirect_base ) ); ?></code>
                        </p>
                    </div>
                </div>

                <!-- S3 Credentials -->
                <div id="eproc-creds-s3" class="eproc-provider-creds eproc-provider-section">
                    <h3><?php esc_html_e( 'S3 / S3-Compatible Configuration', 'eprocurement' ); ?></h3>
                    <div class="eproc-grid-2">
                        <div class="eproc-form-group">
                            <label for="s3_access_key"><?php esc_html_e( 'Access Key', 'eprocurement' ); ?></label>
                            <input type="text" id="s3_access_key" name="cloud_credentials[access_key]" autocomplete="off">
                        </div>
                        <div class="eproc-form-group">
                            <label for="s3_secret_key"><?php esc_html_e( 'Secret Key', 'eprocurement' ); ?></label>
                            <input type="password" id="s3_secret_key" name="cloud_credentials[secret_key]" autocomplete="off">
                        </div>
                    </div>
                    <div class="eproc-grid-2">
                        <div class="eproc-form-group">
                            <label for="s3_bucket"><?php esc_html_e( 'Bucket', 'eprocurement' ); ?></label>
                            <input type="text" id="s3_bucket" name="cloud_credentials[bucket]" autocomplete="off">
                        </div>
                        <div class="eproc-form-group">
                            <label for="s3_region"><?php esc_html_e( 'Region', 'eprocurement' ); ?></label>
                            <input type="text" id="s3_region" name="cloud_credentials[region]" autocomplete="off"
                                   placeholder="<?php esc_attr_e( 'us-east-1', 'eprocurement' ); ?>">
                        </div>
                    </div>
                    <div class="eproc-form-group">
                        <label for="s3_endpoint"><?php esc_html_e( 'Endpoint', 'eprocurement' ); ?></label>
                        <input type="url" id="s3_endpoint" name="cloud_credentials[endpoint]" autocomplete="off"
                               placeholder="<?php esc_attr_e( 'https://s3.amazonaws.com', 'eprocurement' ); ?>">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Leave blank for AWS S3. Set for S3-compatible services (e.g. MinIO, DigitalOcean Spaces).', 'eprocurement' ); ?></p>
                    </div>
                    <div class="eproc-form-group">
                        <label for="s3_prefix"><?php esc_html_e( 'Key Prefix', 'eprocurement' ); ?></label>
                        <input type="text" id="s3_prefix" name="cloud_credentials[prefix]" autocomplete="off"
                               placeholder="<?php esc_attr_e( 'eprocurement/', 'eprocurement' ); ?>">
                        <p class="eproc-text-muted eproc-text-sm"><?php esc_html_e( 'Optional prefix (folder) for all uploaded files.', 'eprocurement' ); ?></p>
                    </div>
                </div>

                <!-- Test Connection -->
                <div class="eproc-form-group eproc-test-connection-row">
                    <button type="button" id="eproc-test-connection" class="eproc-btn">
                        <?php esc_html_e( 'Test Connection', 'eprocurement' ); ?>
                    </button>
                    <span id="eproc-test-result"></span>
                </div>
            </div>
        </div>

        <!-- Save -->
        <div class="eproc-form-actions">
            <button type="submit" class="eproc-btn eproc-btn-primary eproc-btn-lg" id="eproc-save-settings">
                <?php esc_html_e( 'Save Settings', 'eprocurement' ); ?>
            </button>
        </div>
    </form>

    <?php if ( is_super_admin() ) : ?>
    <!-- Demo Data (outside form — separate actions) -->
    <?php
        require_once EPROC_PLUGIN_DIR . 'includes/class-demo-data.php';
        $demo_seeded = Eprocurement_Demo_Data::is_seeded();
    ?>
    <div class="eproc-card" style="margin-top:24px;">
        <div class="eproc-card-header">
            <h2><?php esc_html_e( 'Demo Data', 'eprocurement' ); ?></h2>
        </div>
        <div class="eproc-card-body">
            <p class="eproc-text-muted eproc-text-sm" style="margin-bottom:16px;">
                <?php esc_html_e( 'Seed the system with sample users, contacts, bids, and Q&A threads for demonstration purposes. All demo users use the password: Demo@2025', 'eprocurement' ); ?>
            </p>

            <div id="eproc-demo-notices"></div>

            <?php if ( $demo_seeded ) : ?>
                <div class="eproc-notice" style="margin-bottom:16px;">
                    <p><?php esc_html_e( 'Demo data is currently active.', 'eprocurement' ); ?></p>
                </div>
                <table class="eproc-table" style="margin-bottom:16px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Username', 'eprocurement' ); ?></th>
                            <th><?php esc_html_e( 'Role', 'eprocurement' ); ?></th>
                            <th><?php esc_html_e( 'Password', 'eprocurement' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>demo-scm-manager</td><td>SCM Manager</td><td>Demo@2025</td></tr>
                        <tr><td>demo-scm-official</td><td>SCM Official</td><td>Demo@2025</td></tr>
                        <tr><td>demo-unit-manager</td><td>Unit Manager</td><td>Demo@2025</td></tr>
                        <tr><td>demo-bidder</td><td>Bidder</td><td>Demo@2025</td></tr>
                    </tbody>
                </table>
                <button type="button" id="eproc-remove-demo" class="eproc-btn eproc-btn-danger">
                    <?php esc_html_e( 'Remove Demo Data', 'eprocurement' ); ?>
                </button>
            <?php else : ?>
                <button type="button" id="eproc-seed-demo" class="eproc-btn eproc-btn-primary">
                    <?php esc_html_e( 'Seed Demo Data', 'eprocurement' ); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
jQuery(function($) {

    // Show/hide provider credential fields
    function toggleProviderFields() {
        var provider = $('#cloud_provider').val();
        $('.eproc-provider-creds').hide();
        $('.eproc-provider-creds input').prop('disabled', true);

        if (provider) {
            var $section = $('#eproc-creds-' + provider);
            $section.show();
            $section.find('input').prop('disabled', false);
        }
    }

    $('#cloud_provider').on('change', toggleProviderFields);
    toggleProviderFields();

    // Sync color picker hex labels
    $('#color_primary').on('input', function() {
        $('#color_primary_hex').text($(this).val());
    });
    $('#color_secondary').on('input', function() {
        $('#color_secondary_hex').text($(this).val());
    });

    // Save settings
    $('#eproc-settings-form').on('submit', function(e) {
        e.preventDefault();

        var $btn = $('#eproc-save-settings');
        $btn.prop('disabled', true).text(eprocAdmin.strings.saving);

        var formData = {
            action:                    'eproc_save_settings',
            nonce:                     eprocAdmin.nonce,
            brand_name:                $('#brand_name').val(),
            brand_url:                 $('#brand_url').val(),
            support_email:             $('#support_email').val(),
            brand_logo:                $('#brand_logo').val(),
            login_title:               $('#login_title').val(),
            color_primary:             $('#color_primary').val(),
            color_secondary:           $('#color_secondary').val(),
            cloud_provider:            $('#cloud_provider').val(),
            closed_bid_retention_days: $('#closed_bid_retention_days').val(),
            compliance_section_title:  $('#compliance_section_title').val(),
            frontend_page_slug:        $('#frontend_page_slug').val(),
            bid_heading:               $('#bid_heading').val(),
            category_briefing_register: $('input[name="category_briefing_register"]').is(':checked') ? 1 : 0,
            category_closing_register:  $('input[name="category_closing_register"]').is(':checked') ? 1 : 0,
            category_appointments:      $('input[name="category_appointments"]').is(':checked') ? 1 : 0,
            notify_new_bid:            $('input[name="notify_new_bid"]').is(':checked') ? 1 : 0,
            notify_query:              $('input[name="notify_query"]').is(':checked') ? 1 : 0,
            notify_reply:              $('input[name="notify_reply"]').is(':checked') ? 1 : 0,
            notify_status:             $('input[name="notify_status"]').is(':checked') ? 1 : 0
        };

        var provider = $('#cloud_provider').val();
        if (provider) {
            var $creds = $('#eproc-creds-' + provider);
            $creds.find('input:not(:disabled)').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    formData[name] = $(this).val();
                }
            });
        }

        $.post(eprocAdmin.ajaxUrl, formData, function(response) {
            if (response.success) {
                $('#eproc-settings-notices').html(
                    '<div class="eproc-notice success"><p>' + response.data.message + '</p></div>'
                );
            } else {
                $('#eproc-settings-notices').html(
                    '<div class="eproc-notice error"><p>' + (response.data.message || eprocAdmin.strings.error) + '</p></div>'
                );
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }).fail(function() {
            $('#eproc-settings-notices').html(
                '<div class="eproc-notice error"><p>' + eprocAdmin.strings.error + '</p></div>'
            );
        }).always(function() {
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Save Settings', 'eprocurement' ) ); ?>');
        });
    });

    // Test connection
    $('#eproc-test-connection').on('click', function() {
        var $btn    = $(this);
        var $result = $('#eproc-test-result');

        $btn.prop('disabled', true);
        $result.text(eprocAdmin.strings.testing).css('color', '#64748b');

        $.post(eprocAdmin.ajaxUrl, {
            action: 'eproc_test_storage',
            nonce:  eprocAdmin.nonce
        }, function(response) {
            if (response.success) {
                $result.text(eprocAdmin.strings.connected).css('color', '#8b1a2b');
            } else {
                $result.text(response.data.message || eprocAdmin.strings.connection_fail).css('color', '#e74c3c');
            }
        }).fail(function() {
            $result.text(eprocAdmin.strings.connection_fail).css('color', '#e74c3c');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // OAuth connect buttons
    $(document).on('click', '.eproc-connect-oauth', function() {
        var provider = $(this).data('provider');
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Connecting...', 'eprocurement' ) ); ?>');

        $('#eproc-settings-form').trigger('submit');

        var oauthUrl = '<?php echo esc_url( admin_url( 'admin.php?page=eprocurement-settings&eproc_initiate_oauth=' ) ); ?>' + provider;
        setTimeout(function() {
            window.location.href = oauthUrl;
        }, 1500);
    });

    // Seed demo data
    $(document).on('click', '#eproc-seed-demo', function() {
        var $btn = $(this);
        if (!confirm('<?php echo esc_js( __( 'This will create demo users, contacts, bids, and messages. Continue?', 'eprocurement' ) ); ?>')) {
            return;
        }
        $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Seeding...', 'eprocurement' ) ); ?>');

        $.post(eprocAdmin.ajaxUrl, {
            action: 'eproc_seed_demo_data',
            nonce:  eprocAdmin.nonce
        }, function(response) {
            if (response.success) {
                $('#eproc-demo-notices').html(
                    '<div class="eproc-notice success"><p>' + response.data.message + '</p></div>'
                );
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                $('#eproc-demo-notices').html(
                    '<div class="eproc-notice error"><p>' + (response.data.message || 'Error') + '</p></div>'
                );
                $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Seed Demo Data', 'eprocurement' ) ); ?>');
            }
        }).fail(function() {
            $('#eproc-demo-notices').html(
                '<div class="eproc-notice error"><p>Request failed.</p></div>'
            );
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Seed Demo Data', 'eprocurement' ) ); ?>');
        });
    });

    // Remove demo data
    $(document).on('click', '#eproc-remove-demo', function() {
        var $btn = $(this);
        if (!confirm('<?php echo esc_js( __( 'This will permanently remove all demo users, bids, contacts, and messages. Continue?', 'eprocurement' ) ); ?>')) {
            return;
        }
        $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Removing...', 'eprocurement' ) ); ?>');

        $.post(eprocAdmin.ajaxUrl, {
            action: 'eproc_remove_demo_data',
            nonce:  eprocAdmin.nonce
        }, function(response) {
            if (response.success) {
                $('#eproc-demo-notices').html(
                    '<div class="eproc-notice success"><p>' + response.data.message + '</p></div>'
                );
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                $('#eproc-demo-notices').html(
                    '<div class="eproc-notice error"><p>' + (response.data.message || 'Error') + '</p></div>'
                );
                $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Remove Demo Data', 'eprocurement' ) ); ?>');
            }
        }).fail(function() {
            $('#eproc-demo-notices').html(
                '<div class="eproc-notice error"><p>Request failed.</p></div>'
            );
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Remove Demo Data', 'eprocurement' ) ); ?>');
        });
    });
});
</script>
