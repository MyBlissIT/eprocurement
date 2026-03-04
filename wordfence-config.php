<?php
/**
 * Wordfence Security Configuration Script
 *
 * Run inside Docker container:
 * docker exec eproc-wp php /var/www/html/wp-content/plugins/eprocurement/../../wordfence-config.php
 *
 * Or copy to container and run:
 * docker cp wordfence-config.php eproc-wp:/tmp/wordfence-config.php
 * docker exec eproc-wp php /tmp/wordfence-config.php
 */

// Bootstrap WordPress
require_once '/var/www/html/wp-load.php';

// Ensure Wordfence is active
if ( ! class_exists( 'wfConfig' ) ) {
    $plugin_path = '/var/www/html/wp-content/plugins/wordfence/wordfence.php';
    if ( file_exists( $plugin_path ) ) {
        require_once $plugin_path;
    }
    if ( ! class_exists( 'wfConfig' ) ) {
        echo "ERROR: Wordfence is not installed or active.\n";
        exit( 1 );
    }
}

echo "Configuring Wordfence Security...\n";

$settings = [
    // ── Firewall ──
    'firewallEnabled'              => 1,
    'wafStatus'                    => 'learning-mode', // Start in learning mode, switch to 'enabled' after 1 week
    'learningModeGracePeriodEnabled' => 1,
    'learningModeGracePeriod'      => time() + ( 7 * 86400 ), // 7 days from now
    'blockFakeBots'                => 1,
    'neverBlockBG'                 => 'neverBlockVerified',

    // ── Rate Limiting ──
    'maxGlobalRequests'            => '240',
    'maxGlobalRequests_action'     => 'throttle',
    'maxRequestsCrawlers'          => '120',
    'maxRequestsCrawlers_action'   => 'throttle',
    'max404Crawlers'               => '60',
    'max404Crawlers_action'        => 'throttle',
    'max404Humans'                 => '30',
    'max404Humans_action'          => 'throttle',
    'maxRequestsHumans'            => '240',
    'maxRequestsHumans_action'     => 'throttle',

    // ── Brute Force Protection ──
    'loginSec_lockInvalidUsers'    => 1,
    'loginSec_maxFailures'         => 5,
    'loginSec_maxForgotPasswd'     => 3,
    'loginSec_countFailMins'       => 30,
    'loginSec_lockoutMins'         => 60,
    'loginSec_strongPasswds'       => 'pubs',
    'loginSec_breachPasswds'       => 1,
    'loginSec_maskLoginErrors'     => 1,
    'loginSec_blockAdminReg'       => 1,
    'loginSec_disableAuthorScan'   => 1,

    // ── Scanning ──
    'scheduleScan'                 => 1,
    'scansEnabled_core'            => 1,
    'scansEnabled_themes'          => 1,
    'scansEnabled_plugins'         => 1,
    'scansEnabled_malware'         => 1,
    'scansEnabled_fileContents'    => 1,
    'scansEnabled_fileContentsGSB' => 1,
    'scansEnabled_posts'           => 1,
    'scansEnabled_comments'        => 1,
    'scansEnabled_suspectedFiles'  => 1,
    'scansEnabled_coreUnknown'     => 1,
    'scansEnabled_knownFiles'      => 1,
    'scansEnabled_options'         => 1,
    'scansEnabled_dns'             => 1,
    'scansEnabled_oldVersions'     => 1,
    'scansEnabled_suspiciousAdminUsers' => 1,
    'other_scanOutside'            => 1,
    'scansEnabled_highSense'       => 0, // Low false positive mode

    // ── Alerts ──
    'alertOn_critical'             => 1,
    'alertOn_warnings'             => 0,
    'alertOn_block'                => 0,
    'alertOn_loginLockout'         => 1,
    'alertOn_lostPasswdForm'       => 0,
    'alertOn_adminLogin'           => 1,
    'alertOn_nonAdminLogin'        => 0,
    'alertOn_wordfenceDeactivated' => 1,
    'alertOn_update'               => 1,
    'alertOn_scanIssues'           => 1,

    // ── General ──
    'autoUpdate'                   => 0,       // Manual updates only
    'disableCodeExecutionUploads'  => 1,       // Block PHP execution in uploads/
    'liveTrafficEnabled'           => 0,       // Disable live traffic (performance)
    'other_hideWPVersion'          => 1,
    'deleteTablesOnDeact'          => 0,       // Keep data on deactivation

    // ── 2FA ──
    'loginSec_enableSeparateTwoFactor' => 1,
];

$count = 0;
foreach ( $settings as $key => $value ) {
    wfConfig::set( $key, $value );
    $count++;
}

// Apply .htaccess protection to uploads directory
if ( method_exists( 'wfConfig', 'disableCodeExecutionForUploads' ) ) {
    wfConfig::disableCodeExecutionForUploads();
    echo "Applied .htaccess protection to uploads directory.\n";
}

echo "SUCCESS: Configured {$count} Wordfence settings.\n";
echo "NOTE: WAF is in learning mode for 7 days. After that, switch to 'Enabled and Protecting'.\n";
