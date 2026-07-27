<?php
/**
 * Self-update via GitHub Releases.
 *
 * Hooks into WordPress's native plugin update system to check for new
 * versions on a public GitHub repo. When a new tagged release is found,
 * WordPress shows "Update Available" and the admin can one-click update.
 *
 * @package Eprocurement
 * @since   2.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Eprocurement_Updater {

    /** GitHub repository in owner/repo format. */
    private string $github_repo;

    /** Plugin basename (e.g., eprocurement/eprocurement.php). */
    private string $plugin_basename;

    /** Plugin slug (directory name). */
    private string $plugin_slug = 'eprocurement';

    /** Cached GitHub release response. */
    private ?object $github_release = null;

    /**
     * @param string $github_repo GitHub repo in "owner/repo" format.
     */
    public function __construct( string $github_repo ) {
        $this->github_repo    = $github_repo;
        $this->plugin_basename = EPROC_PLUGIN_BASENAME;

        // Hook into WordPress update system
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );

        // Audit fix A17: verify the downloaded ZIP's SHA-256 BEFORE
        // WordPress extracts it. The previous post_install hook ran
        // hash_file() on $result['source'] which is the EXTRACTED
        // DIRECTORY (returns false), making the integrity check dead code.
        // upgrader_source_selection fires after download but before
        // extraction, with $source = path to the downloaded ZIP file.
        add_filter( 'upgrader_source_selection', [ $this, 'verify_package_checksum' ], 10, 4 );

        // Show update notification details
        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
    }

    /**
     * Fetch the latest release from GitHub API.
     *
     * Results are cached in a transient for 12 hours.
     */
    private function fetch_latest_release(): ?object {
        if ( $this->github_release !== null ) {
            return $this->github_release;
        }

        // Check transient cache
        $transient_key = 'eproc_github_latest_release';
        $cached        = get_transient( $transient_key );

        if ( false !== $cached ) {
            $this->github_release = $cached;
            return $this->github_release;
        }

        $url     = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
        $headers = [
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'eProcurement-WP-Updater/' . EPROC_VERSION,
        ];

        $response = wp_remote_get( $url, [
            'headers' => $headers,
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $release || ! isset( $release->tag_name ) ) {
            return null;
        }

        $this->github_release = $release;
        set_transient( $transient_key, $release, 12 * HOUR_IN_SECONDS );

        return $this->github_release;
    }

    /**
     * Find the plugin ZIP download URL from a release.
     *
     * Looks for an asset named "eprocurement.zip" first, then falls back
     * to GitHub's auto-generated zipball.
     */
    private function get_download_url( object $release ): string {
        // Look for our custom-built ZIP asset
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( $asset->name === 'eprocurement.zip' ) {
                    return $asset->browser_download_url;
                }
            }
        }

        // Fallback: GitHub's auto-generated source zip
        return $release->zipball_url ?? '';
    }

    /**
     * Inject update info into WordPress's update transient.
     *
     * This is the core hook — WordPress calls this when checking for updates.
     *
     * @param object $transient The update_plugins transient.
     * @return object Modified transient.
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->fetch_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        $remote_version = ltrim( $release->tag_name, 'v' );

        if ( version_compare( EPROC_VERSION, $remote_version, '<' ) ) {
            $download_url = $this->get_download_url( $release );

            if ( $download_url ) {
                // Best-effort SHA-256 checksum verification (security fix C-04).
                // If a `eprocurement.zip.sha256` asset is published alongside
                // the release, store it for the upgrader_post_install hook to
                // verify the downloaded package before WordPress installs it.
                $checksum = $this->fetch_release_checksum( $release );

                $transient->response[ $this->plugin_basename ] = (object) [
                    'slug'        => $this->plugin_slug,
                    'plugin'      => $this->plugin_basename,
                    'new_version' => $remote_version,
                    'url'         => $release->html_url,
                    'package'     => $download_url,
                    'icons'       => [],
                    'banners'     => [],
                    // Plugin-internal extras (consumed by upgrader_post_install).
                    'eproc_expected_sha256' => $checksum,
                ];
            }
        }

        return $transient;
    }

    /**
     * Fetch the published SHA-256 checksum for a release, if any.
     *
     * The release pipeline publishes `eprocurement.zip.sha256` containing the
     * hex-encoded digest. If absent, returns null — installs proceed without
     * checksum verification (with a visible warning in the plugin info modal).
     *
     * @since 2.13.1  Security fix C-04 — supply-chain integrity.
     * @param object $release GitHub release object.
     * @return string|null 64-char hex SHA-256, or null if not published.
     */
    private function fetch_release_checksum( object $release ): ?string {
        if ( empty( $release->assets ) ) {
            return null;
        }

        $checksum_url = null;
        foreach ( $release->assets as $asset ) {
            if ( $asset->name === 'eprocurement.zip.sha256' ) {
                $checksum_url = $asset->browser_download_url;
                break;
            }
        }

        if ( ! $checksum_url ) {
            return null;
        }

        $response = wp_remote_get( $checksum_url, [
            'timeout' => 10,
            'headers' => [ 'User-Agent' => 'eProcurement-WP-Updater/' . EPROC_VERSION ],
        ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        // Accept "hash  filename" or bare hash formats.
        if ( preg_match( '/\b([a-f0-9]{64})\b/', $body, $m ) ) {
            return $m[1];
        }

        return null;
    }

    /**
     * Provide plugin details when user clicks "View Details" in wp-admin.
     *
     * @param false|object|array $result Default result.
     * @param string             $action API action.
     * @param object             $args   Request args.
     * @return false|object Plugin info or false.
     */
    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        if ( ( $args->slug ?? '' ) !== $this->plugin_slug ) {
            return $result;
        }

        $release = $this->fetch_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $remote_version = ltrim( $release->tag_name, 'v' );

        return (object) [
            'name'          => 'eProcurement',
            'slug'          => $this->plugin_slug,
            'version'       => $remote_version,
            'author'        => '<a href="https://www.myblisstech.com">MyBliss Technologies</a>',
            'author_profile'=> 'https://www.myblisstech.com',
            'homepage'      => 'https://www.myblisstech.com/eprocurement',
            'requires'      => '6.0',
            'requires_php'  => '8.1',
            'tested'        => '6.7',
            'downloaded'    => 0,
            'last_updated'  => $release->published_at ?? '',
            'sections'      => [
                'description' => 'A mini-CRM WordPress plugin for government/corporate procurement processes. Manages bid/tender notices, structured communication, cloud-based document storage, and role-based access control.',
                'changelog'   => self::format_changelog( $release->body ?? '' ),
            ],
            'download_link' => $this->get_download_url( $release ),
        ];
    }

    /**
     * Verify the downloaded ZIP's SHA-256 BEFORE extraction.
     *
     * Audit fix A17 + A18: the previous post_install checksum verification
     * was dead code because it hashed a directory instead of a file. This
     * hook fires after download but before extraction, with $source = path
     * to the downloaded ZIP file. We hash the actual ZIP and compare
     * against the expected SHA-256 published alongside the release.
     *
     * Audit fix A18: if no checksum was published (the release omitted the
     * .sha256 asset), we now HARD-FAIL the update. Previously verification
     * was silently skipped, allowing a compromised release to bypass all
     * integrity checks simply by omitting the checksum asset.
     *
     * @param string       $source      Path to the downloaded ZIP file.
     * @param string       $remote_src  Remote source (unused).
     * @param WP_Upgrader  $upgrader    Upgrader instance.
     * @param array        $hook_extra  Extra args (contains 'plugin').
     * @return string|WP_Error $source on success, WP_Error on mismatch.
     */
    public function verify_package_checksum( $source, $remote_src, $upgrader, $hook_extra ) {
        // Only verify our own plugin updates.
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
            return $source;
        }

        // Only verify actual files (not directories).
        if ( ! $source || ! file_exists( $source ) || is_dir( $source ) ) {
            return $source;
        }

        $update_transient = get_site_transient( 'update_plugins' );
        $expected_sha     = $update_transient->response[ $this->plugin_basename ]->eproc_expected_sha256 ?? null;

        // Audit fix A18: HARD-FAIL if no checksum was published.
        // A release without a checksum asset can no longer bypass
        // integrity verification.
        if ( ! $expected_sha ) {
            return new \WP_Error(
                'eproc_no_checksum',
                __( 'eProcurement update rejected: the release does not publish a SHA-256 checksum asset. Refusing to install an unverified package. Contact the plugin maintainer.', 'eprocurement' )
            );
        }

        $actual_sha = hash_file( 'sha256', $source );
        if ( ! $actual_sha ) {
            return new \WP_Error(
                'eproc_checksum_read_failed',
                __( 'eProcurement update rejected: could not compute SHA-256 of the downloaded package.', 'eprocurement' )
            );
        }

        if ( ! hash_equals( strtolower( $expected_sha ), strtolower( $actual_sha ) ) ) {
            return new \WP_Error(
                'eproc_checksum_mismatch',
                sprintf(
                    /* translators: 1: expected SHA-256, 2: actual SHA-256 */
                    __( 'eProcurement update rejected: package checksum mismatch (expected %1$s, got %2$s). The downloaded package may have been tampered with.', 'eprocurement' ),
                    $expected_sha,
                    $actual_sha
                )
            );
        }

        return $source;
    }

    /**
     * After WordPress extracts the update ZIP, ensure the folder name
     * matches our plugin slug. GitHub zipballs use "owner-repo-hash"
     * as the folder name, which would break the plugin path.
     *
     * @param bool|WP_Error $response   Install response.
     * @param array          $hook_extra Extra args.
     * @param array          $result     Install result.
     * @return array|WP_Error Modified result.
     */
    public function post_install( $response, $hook_extra, $result ) {
        // Only act on our plugin
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
            return $result;
        }

        // Audit fix A17: SHA-256 verification has been moved to
        // verify_package_checksum() which fires BEFORE extraction via
        // the upgrader_source_selection filter. The previous code here
        // hashed a directory (the extracted result), which always
        // returned false — making the integrity check dead code.

        global $wp_filesystem;

        $proper_destination = trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_slug;

        // Normalize both paths for comparison (remove trailing slashes)
        $current = untrailingslashit( $result['destination'] );
        $target  = untrailingslashit( $proper_destination );

        // If the extracted folder doesn't match our slug, rename it
        if ( $current !== $target ) {
            // Remove stale destination if it exists (leftover from a failed update)
            if ( $wp_filesystem->is_dir( $target ) ) {
                $wp_filesystem->delete( $target, true );
            }

            $moved = $wp_filesystem->move( $current, $target );
            if ( ! $moved ) {
                return new \WP_Error(
                    'eproc_update_move_failed',
                    sprintf( 'Could not move plugin from %s to %s.', $current, $target )
                );
            }

            $result['destination']     = $target;
            $result['destination_name'] = $this->plugin_slug;
        }

        // Re-activate the plugin after update
        if ( is_plugin_active( $this->plugin_basename ) ) {
            activate_plugin( $this->plugin_basename );
        }

        // Clear the release cache so next check fetches fresh data
        delete_transient( 'eproc_github_latest_release' );

        return $result;
    }

    /**
     * Add a "Check for updates" link in the plugins list.
     */
    public function plugin_row_meta( array $links, string $file ): array {
        if ( $file !== $this->plugin_basename ) {
            return $links;
        }

        $links[] = '<a href="' . esc_url( wp_nonce_url(
            admin_url( 'update-core.php?force-check=1' ),
            'force-check'
        ) ) . '">' . esc_html__( 'Check for updates', 'eprocurement' ) . '</a>';

        return $links;
    }

    /**
     * Convert GitHub markdown release notes to safe HTML.
     *
     * The result is always passed through wp_kses_post() to prevent
     * supply-chain XSS from a compromised GitHub repository or a
     * tampered API response (security fix C-04).
     *
     * @param string $markdown Raw release body from GitHub API.
     * @return string Sanitised HTML safe for wp-admin rendering.
     */
    private static function format_changelog( string $markdown ): string {
        if ( empty( $markdown ) ) {
            return '<p>No changelog provided for this release.</p>';
        }

        // Strip raw HTML tags from the source — we only consume plain markdown.
        // This neutralises any <script> payloads in the release body before
        // our markdown pass even runs.
        $markdown = wp_strip_all_tags( $markdown );

        // Convert markdown headers
        $html = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $markdown );
        $html = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $html );

        // Convert markdown lists
        $html = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html );
        $html = preg_replace( '/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $html );

        // Convert bold
        $html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );

        // Convert inline code
        $html = preg_replace( '/`(.+?)`/', '<code>$1</code>', $html );

        // Line breaks
        $html = nl2br( $html );

        // CRITICAL: sanitise through wp_kses_post so only a safe HTML subset
        // is preserved. This is the defence against a compromised upstream
        // repository injecting <script> into the admin "View details" modal.
        return wp_kses_post( $html );
    }
}
