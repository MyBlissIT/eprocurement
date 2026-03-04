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
                $transient->response[ $this->plugin_basename ] = (object) [
                    'slug'        => $this->plugin_slug,
                    'plugin'      => $this->plugin_basename,
                    'new_version' => $remote_version,
                    'url'         => $release->html_url,
                    'package'     => $download_url,
                    'icons'       => [],
                    'banners'     => [],
                ];
            }
        }

        return $transient;
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
            'requires_php'  => '8.0',
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
     * After WordPress extracts the update ZIP, ensure the folder name
     * matches our plugin slug. GitHub zipballs use "owner-repo-hash"
     * as the folder name.
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

        global $wp_filesystem;

        $proper_destination = WP_PLUGIN_DIR . '/' . $this->plugin_slug;

        // If the extracted folder doesn't match our slug, rename it
        if ( $result['destination'] !== $proper_destination ) {
            $wp_filesystem->move( $result['destination'], $proper_destination );
            $result['destination'] = $proper_destination;
        }

        // Re-activate the plugin after update
        $active = is_plugin_active( $this->plugin_basename );
        if ( $active ) {
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
     * Convert GitHub markdown release notes to basic HTML.
     */
    private static function format_changelog( string $markdown ): string {
        if ( empty( $markdown ) ) {
            return '<p>No changelog provided for this release.</p>';
        }

        // Convert markdown headers
        $html = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $markdown );
        $html = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $markdown );

        // Convert markdown lists
        $html = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $html );
        $html = preg_replace( '/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $html );

        // Convert bold
        $html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );

        // Convert inline code
        $html = preg_replace( '/`(.+?)`/', '<code>$1</code>', $html );

        // Line breaks
        $html = nl2br( $html );

        return $html;
    }
}
