<?php
/**
 * Frontend Admin — Settings (Super Admin only).
 *
 * Includes: General, Notifications, Cloud Storage, SMTP, External Database.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );

// Current settings
$cloud_provider    = get_option( 'eprocurement_cloud_provider', '' );
$retention_days    = get_option( 'eprocurement_closed_bid_retention_days', '' );
$section_title     = Eprocurement_Compliance_Docs::get_section_title();
$notification_json = get_option( 'eprocurement_notification_settings', '{}' );
$notifications     = json_decode( $notification_json, true ) ?: [];
$smtp_configured   = ! empty( get_option( 'eprocurement_smtp_settings' ) );
$ext_db_configured = ! empty( get_option( 'eprocurement_external_db_settings' ) );

// Category toggles
$cat_briefing     = get_option( 'eprocurement_category_briefing_register', '0' );
$cat_closing      = get_option( 'eprocurement_category_closing_register', '0' );
$cat_appointments = get_option( 'eprocurement_category_appointments', '0' );
?>
<div class="eproc-wrap">
    <h1><?php esc_html_e( 'Settings', 'eprocurement' ); ?></h1>

    <form id="eproc-settings-form">
        <!-- General Settings -->
        <div class="eproc-card">
            <div class="eproc-card-header"><h2><?php esc_html_e( 'General', 'eprocurement' ); ?></h2></div>
            <div class="eproc-card-body">
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'Frontend Page Slug', 'eprocurement' ); ?></label>
                        <input type="text" name="frontend_page_slug" class="eproc-input" value="<?php echo esc_attr( $slug ); ?>" />
                    </div>
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'SCM Documents Section Title', 'eprocurement' ); ?></label>
                        <input type="text" name="compliance_section_title" class="eproc-input" value="<?php echo esc_attr( $section_title ); ?>" />
                    </div>
                </div>
                <div class="eproc-form-group">
                    <label class="eproc-label"><?php esc_html_e( 'Closed Bid Retention (days)', 'eprocurement' ); ?></label>
                    <input type="number" name="closed_bid_retention_days" class="eproc-input" value="<?php echo esc_attr( $retention_days ); ?>" placeholder="<?php esc_attr_e( 'Leave empty to keep forever', 'eprocurement' ); ?>" />
                </div>
            </div>
        </div>

        <!-- Bid Categories -->
        <div class="eproc-card">
            <div class="eproc-card-header"><h2><?php esc_html_e( 'Bid Categories', 'eprocurement' ); ?></h2></div>
            <div class="eproc-card-body">
                <label class="eproc-checkbox"><input type="checkbox" name="category_briefing_register" value="1" <?php checked( $cat_briefing, '1' ); ?> /> <?php esc_html_e( 'Enable Briefing Register', 'eprocurement' ); ?></label>
                <label class="eproc-checkbox"><input type="checkbox" name="category_closing_register" value="1" <?php checked( $cat_closing, '1' ); ?> /> <?php esc_html_e( 'Enable Closing Register', 'eprocurement' ); ?></label>
                <label class="eproc-checkbox"><input type="checkbox" name="category_appointments" value="1" <?php checked( $cat_appointments, '1' ); ?> /> <?php esc_html_e( 'Enable Appointments', 'eprocurement' ); ?></label>
            </div>
        </div>

        <!-- Notifications -->
        <div class="eproc-card">
            <div class="eproc-card-header"><h2><?php esc_html_e( 'Notifications', 'eprocurement' ); ?></h2></div>
            <div class="eproc-card-body">
                <p class="eproc-text-muted"><?php esc_html_e( 'Email verification is always enabled and cannot be toggled.', 'eprocurement' ); ?></p>
                <label class="eproc-checkbox"><input type="checkbox" name="notify_new_bid" value="1" <?php checked( ! empty( $notifications['new_bid_notify_bidders'] ) ); ?> /> <?php esc_html_e( 'Notify bidders when a new bid is published', 'eprocurement' ); ?></label>
                <label class="eproc-checkbox"><input type="checkbox" name="notify_query" value="1" <?php checked( ! empty( $notifications['query_notify_contact'] ) ); ?> /> <?php esc_html_e( 'Notify contact person when a new query is submitted', 'eprocurement' ); ?></label>
                <label class="eproc-checkbox"><input type="checkbox" name="notify_reply" value="1" <?php checked( ! empty( $notifications['reply_notify_bidder'] ) ); ?> /> <?php esc_html_e( 'Notify bidder when a query is replied to', 'eprocurement' ); ?></label>
                <label class="eproc-checkbox"><input type="checkbox" name="notify_status" value="1" <?php checked( ! empty( $notifications['status_change_notify'] ) ); ?> /> <?php esc_html_e( 'Notify on bid status changes', 'eprocurement' ); ?></label>
            </div>
        </div>

        <!-- Cloud Storage -->
        <div class="eproc-card">
            <div class="eproc-card-header"><h2><?php esc_html_e( 'Cloud Storage', 'eprocurement' ); ?></h2></div>
            <div class="eproc-card-body">
                <div class="eproc-form-group">
                    <label class="eproc-label"><?php esc_html_e( 'Provider', 'eprocurement' ); ?></label>
                    <select name="cloud_provider" class="eproc-select">
                        <option value="" <?php selected( $cloud_provider, '' ); ?>><?php esc_html_e( '— Select —', 'eprocurement' ); ?></option>
                        <option value="s3" <?php selected( $cloud_provider, 's3' ); ?>>Amazon S3</option>
                        <option value="google_drive" <?php selected( $cloud_provider, 'google_drive' ); ?>>Google Drive</option>
                        <option value="onedrive" <?php selected( $cloud_provider, 'onedrive' ); ?>>OneDrive</option>
                        <option value="dropbox" <?php selected( $cloud_provider, 'dropbox' ); ?>>Dropbox</option>
                    </select>
                </div>
                <div id="eproc-storage-creds">
                    <p class="eproc-text-muted"><?php esc_html_e( 'Provider credentials are encrypted and stored securely. Enter new values to update.', 'eprocurement' ); ?></p>
                    <div class="eproc-form-row eproc-grid-2">
                        <div class="eproc-form-group">
                            <label class="eproc-label"><?php esc_html_e( 'Access Key / Client ID', 'eprocurement' ); ?></label>
                            <input type="password" name="cloud_cred_key" class="eproc-input" placeholder="<?php esc_attr_e( 'Leave blank to keep current', 'eprocurement' ); ?>" />
                        </div>
                        <div class="eproc-form-group">
                            <label class="eproc-label"><?php esc_html_e( 'Secret Key / Client Secret', 'eprocurement' ); ?></label>
                            <input type="password" name="cloud_cred_secret" class="eproc-input" placeholder="<?php esc_attr_e( 'Leave blank to keep current', 'eprocurement' ); ?>" />
                        </div>
                    </div>
                    <div class="eproc-form-row eproc-grid-2">
                        <div class="eproc-form-group">
                            <label class="eproc-label"><?php esc_html_e( 'Region / Bucket', 'eprocurement' ); ?></label>
                            <input type="text" name="cloud_cred_region" class="eproc-input" placeholder="<?php esc_attr_e( 'e.g. us-east-1 or bucket-name', 'eprocurement' ); ?>" />
                        </div>
                        <div class="eproc-form-group" style="display:flex;align-items:flex-end;">
                            <button type="button" class="eproc-btn" id="eproc-test-storage"><?php esc_html_e( 'Test Connection', 'eprocurement' ); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMTP -->
        <div class="eproc-card">
            <div class="eproc-card-header">
                <h2><?php esc_html_e( 'SMTP Configuration', 'eprocurement' ); ?></h2>
                <?php if ( $smtp_configured ) : ?>
                    <span class="eproc-badge verified"><?php esc_html_e( 'Configured', 'eprocurement' ); ?></span>
                <?php endif; ?>
            </div>
            <div class="eproc-card-body">
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'SMTP Host', 'eprocurement' ); ?></label>
                        <input type="text" name="smtp_host" class="eproc-input" placeholder="smtp.example.com" />
                    </div>
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'SMTP Port', 'eprocurement' ); ?></label>
                        <input type="number" name="smtp_port" class="eproc-input" placeholder="587" />
                    </div>
                </div>
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'Username', 'eprocurement' ); ?></label>
                        <input type="text" name="smtp_username" class="eproc-input" />
                    </div>
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'Password', 'eprocurement' ); ?></label>
                        <input type="password" name="smtp_password" class="eproc-input" placeholder="<?php esc_attr_e( 'Leave blank to keep current', 'eprocurement' ); ?>" />
                    </div>
                </div>
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'Encryption', 'eprocurement' ); ?></label>
                        <select name="smtp_encryption" class="eproc-select">
                            <option value=""><?php esc_html_e( 'None', 'eprocurement' ); ?></option>
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                        </select>
                    </div>
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'From Name', 'eprocurement' ); ?></label>
                        <input type="text" name="smtp_from_name" class="eproc-input" placeholder="eProcurement" />
                    </div>
                </div>
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'From Email', 'eprocurement' ); ?></label>
                        <input type="email" name="smtp_from_email" class="eproc-input" placeholder="noreply@example.com" />
                    </div>
                    <div class="eproc-form-group" style="display:flex;align-items:flex-end;">
                        <button type="button" class="eproc-btn" id="eproc-test-smtp"><?php esc_html_e( 'Send Test Email', 'eprocurement' ); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- External Database -->
        <div class="eproc-card">
            <div class="eproc-card-header">
                <h2><?php esc_html_e( 'External Database', 'eprocurement' ); ?></h2>
                <?php if ( $ext_db_configured ) : ?>
                    <span class="eproc-badge verified"><?php esc_html_e( 'Configured', 'eprocurement' ); ?></span>
                <?php endif; ?>
            </div>
            <div class="eproc-card-body">
                <p class="eproc-text-muted"><?php esc_html_e( 'Pull users from an external client database and provision them as WordPress users with eProcurement roles.', 'eprocurement' ); ?></p>
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'DB Host', 'eprocurement' ); ?></label>
                        <input type="text" name="ext_db_host" class="eproc-input" placeholder="localhost" />
                    </div>
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'DB Port', 'eprocurement' ); ?></label>
                        <input type="number" name="ext_db_port" class="eproc-input" placeholder="3306" />
                    </div>
                </div>
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'Database Name', 'eprocurement' ); ?></label>
                        <input type="text" name="ext_db_name" class="eproc-input" />
                    </div>
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'User Table', 'eprocurement' ); ?></label>
                        <input type="text" name="ext_db_table" class="eproc-input" placeholder="users" />
                    </div>
                </div>
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'DB Username', 'eprocurement' ); ?></label>
                        <input type="text" name="ext_db_username" class="eproc-input" />
                    </div>
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'DB Password', 'eprocurement' ); ?></label>
                        <input type="password" name="ext_db_password" class="eproc-input" placeholder="<?php esc_attr_e( 'Leave blank to keep current', 'eprocurement' ); ?>" />
                    </div>
                </div>
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'Email Column', 'eprocurement' ); ?></label>
                        <input type="text" name="ext_db_email_col" class="eproc-input" placeholder="email" />
                    </div>
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'Name Column', 'eprocurement' ); ?></label>
                        <input type="text" name="ext_db_name_col" class="eproc-input" placeholder="full_name" />
                    </div>
                </div>
                <div class="eproc-form-row eproc-grid-2">
                    <div class="eproc-form-group">
                        <label class="eproc-label"><?php esc_html_e( 'Default Role', 'eprocurement' ); ?></label>
                        <select name="ext_db_default_role" class="eproc-select">
                            <option value="eprocurement_scm_official"><?php esc_html_e( 'SCM Official', 'eprocurement' ); ?></option>
                            <option value="eprocurement_scm_manager"><?php esc_html_e( 'SCM Manager', 'eprocurement' ); ?></option>
                            <option value="eprocurement_unit_manager"><?php esc_html_e( 'Unit Manager', 'eprocurement' ); ?></option>
                        </select>
                    </div>
                    <div class="eproc-form-group" style="display:flex;gap:8px;align-items:flex-end;">
                        <button type="button" class="eproc-btn" id="eproc-test-extdb"><?php esc_html_e( 'Test Connection', 'eprocurement' ); ?></button>
                        <button type="button" class="eproc-btn eproc-btn-primary" id="eproc-sync-extdb"><?php esc_html_e( 'Sync Now', 'eprocurement' ); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- CORS / Multi-Tenant -->
        <div class="eproc-card">
            <div class="eproc-card-header"><h2><?php esc_html_e( 'Cross-Origin Access (CORS)', 'eprocurement' ); ?></h2></div>
            <div class="eproc-card-body">
                <p class="eproc-text-muted"><?php esc_html_e( 'Allow external domains to access the eProcurement REST API. Leave empty for same-origin only. Use * to allow all origins, or enter a comma-separated list of allowed origins.', 'eprocurement' ); ?></p>
                <div class="eproc-form-group">
                    <label class="eproc-label"><?php esc_html_e( 'Allowed Origins', 'eprocurement' ); ?></label>
                    <input type="text" name="cors_origins" class="eproc-input" value="<?php echo esc_attr( get_option( 'eprocurement_cors_origins', '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. https://app.example.com, https://other.example.com', 'eprocurement' ); ?>" />
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="eproc-form-actions">
            <button type="submit" class="eproc-btn eproc-btn-primary eproc-btn-lg" id="eproc-save-settings">
                <?php esc_html_e( 'Save Settings', 'eprocurement' ); ?>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Save settings
    document.getElementById('eproc-settings-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        var btn = document.getElementById('eproc-save-settings');
        eprocSetLoading(btn, true);

        var form = this;
        var body = {
            frontend_page_slug: form.frontend_page_slug.value,
            compliance_section_title: form.compliance_section_title.value,
            closed_bid_retention_days: form.closed_bid_retention_days.value,
            cloud_provider: form.cloud_provider.value,
            category_briefing_register: form.category_briefing_register.checked,
            category_closing_register: form.category_closing_register.checked,
            category_appointments: form.category_appointments.checked,
            notifications: {
                new_bid_notify_bidders: form.notify_new_bid.checked,
                query_notify_contact: form.notify_query.checked,
                reply_notify_bidder: form.notify_reply.checked,
                status_change_notify: form.notify_status.checked,
            },
            cors_origins: form.cors_origins.value,
        };

        // Cloud credentials (only if values entered)
        if (form.cloud_cred_key.value || form.cloud_cred_secret.value) {
            body.cloud_credentials = {
                key: form.cloud_cred_key.value,
                secret: form.cloud_cred_secret.value,
                region: form.cloud_cred_region.value,
            };
        }

        // SMTP settings
        if (form.smtp_host.value) {
            body.smtp = {
                host: form.smtp_host.value,
                port: form.smtp_port.value,
                username: form.smtp_username.value,
                password: form.smtp_password.value,
                encryption: form.smtp_encryption.value,
                from_name: form.smtp_from_name.value,
                from_email: form.smtp_from_email.value,
            };
        }

        // External DB settings
        if (form.ext_db_host.value) {
            body.external_db = {
                host: form.ext_db_host.value,
                port: form.ext_db_port.value,
                database: form.ext_db_name.value,
                table: form.ext_db_table.value,
                username: form.ext_db_username.value,
                password: form.ext_db_password.value,
                email_column: form.ext_db_email_col.value,
                name_column: form.ext_db_name_col.value,
                default_role: form.ext_db_default_role.value,
            };
        }

        try {
            await eprocAPI.post('admin/settings', body);
            eprocToast('<?php echo esc_js( __( 'Settings saved.', 'eprocurement' ) ); ?>');
        } catch (err) {
            eprocToast(err.message, 'error');
        } finally {
            eprocSetLoading(btn, false);
        }
    });

    // Test storage
    document.getElementById('eproc-test-storage').addEventListener('click', async function() {
        eprocSetLoading(this, true);
        try {
            await eprocAPI.post('admin/settings/test-storage', {});
            eprocToast('<?php echo esc_js( __( 'Connection successful!', 'eprocurement' ) ); ?>');
        } catch (err) {
            eprocToast(err.message, 'error');
        } finally {
            eprocSetLoading(this, false);
        }
    });

    // Test SMTP
    document.getElementById('eproc-test-smtp').addEventListener('click', async function() {
        var email = prompt('<?php echo esc_js( __( 'Send test email to:', 'eprocurement' ) ); ?>');
        if (!email) return;
        eprocSetLoading(this, true);
        try {
            await eprocAPI.post('admin/settings/test-smtp', { to: email });
            eprocToast('<?php echo esc_js( __( 'Test email sent!', 'eprocurement' ) ); ?>');
        } catch (err) {
            eprocToast(err.message, 'error');
        } finally {
            eprocSetLoading(this, false);
        }
    });

    // Test External DB
    document.getElementById('eproc-test-extdb').addEventListener('click', async function() {
        eprocSetLoading(this, true);
        try {
            await eprocAPI.post('admin/settings/test-external-db', {});
            eprocToast('<?php echo esc_js( __( 'Connection successful!', 'eprocurement' ) ); ?>');
        } catch (err) {
            eprocToast(err.message, 'error');
        } finally {
            eprocSetLoading(this, false);
        }
    });

    // Sync External DB
    document.getElementById('eproc-sync-extdb').addEventListener('click', async function() {
        if (!confirm('<?php echo esc_js( __( 'This will sync users from the external database. Continue?', 'eprocurement' ) ); ?>')) return;
        eprocSetLoading(this, true);
        try {
            var result = await eprocAPI.post('admin/settings/sync-external-db', {});
            eprocToast('Created: ' + result.created + ', Updated: ' + result.updated + ', Skipped: ' + result.skipped);
        } catch (err) {
            eprocToast(err.message, 'error');
        } finally {
            eprocSetLoading(this, false);
        }
    });
});
</script>
