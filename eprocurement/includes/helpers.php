<?php
/**
 * Shared helper functions for the eProcurement plugin.
 *
 * These functions consolidate patterns that were previously duplicated
 * across the codebase (fix DRY violations). They are loaded by the
 * bootstrap on `plugins_loaded`.
 *
 * @package Eprocurement
 * @since   2.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'eprocurement_get_slug' ) ) {
    /**
     * Get the frontend page slug, cached per-request.
     *
     * Previously this was called 40+ times across the codebase via
     * `get_option( 'eprocurement_frontend_page_slug', 'tenders' )`.
     * The helper caches the result in a static variable for the request.
     *
     * @since 2.14.0
     * @return string Frontend slug (default 'tenders').
     */
    function eprocurement_get_slug(): string {
        static $slug = null;
        if ( $slug === null ) {
            $slug = get_option( 'eprocurement_frontend_page_slug', 'tenders' );
            if ( ! $slug ) {
                $slug = 'tenders';
            }
        }
        return $slug;
    }
}

if ( ! function_exists( 'eprocurement_generate_unique_username' ) ) {
    /**
     * Generate a unique WordPress username from a base string.
     *
     * Previously duplicated in class-bidder.php, class-external-db.php,
     * class-admin.php, and class-admin-rest-api.php.
     *
     * @since 2.14.0
     * @param string $base Suggested username (e.g., 'first.last' or email local-part).
     * @return string Sanitised, unique username.
     */
    function eprocurement_generate_unique_username( string $base ): string {
        $username = sanitize_user( strtolower( $base ), true );
        $original = $username;
        $i        = 1;

        while ( username_exists( $username ) ) {
            $username = $original . $i;
            $i++;
        }

        return $username;
    }
}

if ( ! function_exists( 'eprocurement_create_user_from_email' ) ) {
    /**
     * Create a WordPress user from an email address with a random password.
     *
     * Used by the contact-person save flow and external DB sync. The user
     * receives the standard "Set your password" email from WordPress.
     *
     * @since 2.14.0
     * @param string $email    User email.
     * @param string $name     Display name.
     * @param string $role     WordPress role slug.
     * @return int|\WP_Error User ID on success, WP_Error on failure.
     */
    function eprocurement_create_user_from_email( string $email, string $name, string $role ): int|\WP_Error {
        $existing = get_user_by( 'email', $email );
        if ( $existing ) {
            return $existing->ID;
        }

        $username = eprocurement_generate_unique_username( explode( '@', $email )[0] );

        $user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password( 16 ),
            'display_name' => $name ?: $username,
            'first_name'   => $name ? explode( ' ', $name )[0] : '',
            'role'         => $role,
        ] );

        if ( ! is_wp_error( $user_id ) ) {
            wp_new_user_notification( $user_id, null, 'user' );
        }

        return $user_id;
    }
}

if ( ! function_exists( 'eprocurement_format_bytes' ) ) {
    /**
     * Format a byte count as a human-readable string.
     *
     * @since 2.14.0
     * @param int $bytes    Size in bytes.
     * @param int $decimals Decimal places (default 1).
     * @return string Formatted size (e.g., "1.5 MB").
     */
    function eprocurement_format_bytes( int $bytes, int $decimals = 1 ): string {
        if ( $bytes <= 0 ) {
            return '0 B';
        }
        $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
        $factor = (int) floor( log( $bytes, 1024 ) );
        $factor = min( $factor, count( $units ) - 1 );
        return sprintf( "%.{$decimals}f %s", $bytes / pow( 1024, $factor ), $units[ $factor ] );
    }
}

if ( ! function_exists( 'eprocurement_parse_date_input' ) ) {
    /**
     * Parse a date input string into MySQL datetime format.
     *
     * Accepts `dd/mm/yyyy HH:mm` (admin UI format) or `Y-m-d\TH:i`
     * (HTML datetime-local). Returns null on parse failure.
     *
     * @since 2.14.0
     * @param string $raw Raw date input.
     * @return string|null MySQL datetime (Y-m-d H:i:s) or null.
     */
    function eprocurement_parse_date_input( string $raw ): ?string {
        $raw = sanitize_text_field( $raw );
        if ( $raw === '' ) {
            return null;
        }

        $dt = \DateTime::createFromFormat( 'd/m/Y H:i', $raw );
        if ( $dt ) {
            return $dt->format( 'Y-m-d H:i:s' );
        }

        $dt = \DateTime::createFromFormat( 'Y-m-d\TH:i', $raw );
        if ( $dt ) {
            return $dt->format( 'Y-m-d H:i:s' );
        }

        return null;
    }
}

if ( ! function_exists( 'eprocurement_load_template' ) ) {
    /**
     * Load a template, allowing theme override via locate_template().
     *
     * Themes can override plugin templates by placing a file at
     * `/wp-content/themes/{theme}/eprocurement/{template_name}`.
     * Falls back to the plugin's bundled template.
     *
     * @since 2.14.0
     * @param string $template_name Relative path inside templates/ (e.g., 'email/briefing-invite.php').
     * @param array  $vars          Variables to extract into the template scope.
     * @return void Output is echoed.
     */
    function eprocurement_load_template( string $template_name, array $vars = [] ): void {
        // Allow themes to override.
        $located = locate_template( 'eprocurement/' . $template_name );

        if ( ! $located ) {
            $plugin_template = EPROC_PLUGIN_DIR . 'templates/' . $template_name;
            if ( file_exists( $plugin_template ) ) {
                $located = $plugin_template;
            }
        }

        if ( ! $located ) {
            return;
        }

        if ( ! empty( $vars ) ) {
            // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
            extract( $vars, EXTR_SKIP );
        }

        include $located;
    }
}

if ( ! function_exists( 'eprocurement_avatar' ) ) {
    /**
     * Render an initials-based avatar for a user.
     *
     * Produces a circular coloured badge with the user's initials.
     * Falls back to a generic user icon if no name is available.
     * The colour is deterministically derived from the user's name
     * so the same user always gets the same colour.
     *
     * @since 2.14.0
     * @param int|null $user_id  WordPress user ID (null for guest).
     * @param string   $name     Fallback display name (used if user_id is 0/null).
     * @param int      $size     Avatar diameter in pixels (default 36).
     * @param array    $attrs    Optional. Extra HTML attributes ['class' => '', 'data-attr' => 'val'].
     * @return string HTML markup.
     */
    function eprocurement_avatar( ?int $user_id, string $name = '', int $size = 36, array $attrs = [] ): string {
        // Resolve name + email from user_id if available.
        $email = '';
        if ( $user_id ) {
            $user = get_userdata( $user_id );
            if ( $user ) {
                if ( ! $name ) {
                    $name = $user->display_name ?: $user->user_login;
                }
                $email = $user->user_email;
            }
        }

        $name = trim( $name );
        $initials = eprocurement_get_initials( $name );
        $color = eprocurement_get_avatar_color( $name ?: $email ?: 'unknown' );

        $extra_class = $attrs['class'] ?? '';
        $extra_attrs = '';
        foreach ( $attrs as $k => $v ) {
            if ( $k === 'class' ) continue;
            $extra_attrs .= ' ' . sanitize_key( $k ) . '="' . esc_attr( $v ) . '"';
        }

        // Try Gravatar first (if email available) — opt-in only via filter,
        // disabled by default to avoid leaking emails to third parties.
        $use_gravatar = apply_filters( 'eprocurement_use_gravatar', false );
        if ( $use_gravatar && $email ) {
            $hash = md5( strtolower( trim( $email ) ) );
            $url = "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=404";
            return sprintf(
                '<img src="%s" alt="%s" width="%d" height="%d" class="eproc-avatar %s" loading="lazy" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline-flex\'" /><span class="eproc-avatar eproc-avatar--initials %s" style="background:%s;width:%dpx;height:%dpx;display:none">%s</span>%s',
                esc_url( $url ),
                esc_attr( $name ),
                $size,
                $size,
                $extra_class,
                $extra_class,
                $color,
                $size,
                $size,
                esc_html( $initials ),
                $extra_attrs
            );
        }

        return sprintf(
            '<span class="eproc-avatar eproc-avatar--initials %s" style="background:%s;width:%dpx;height:%dpx;font-size:%dpx"%s aria-label="%s" role="img">%s</span>',
            $extra_class,
            $color,
            $size,
            $size,
            max( 11, (int) ( $size * 0.36 ) ),
            $extra_attrs,
            esc_attr( $name ),
            esc_html( $initials )
        );
    }
}

if ( ! function_exists( 'eprocurement_get_initials' ) ) {
    /**
     * Get up to 2 initials from a display name.
     *
     * @since 2.14.0
     * @param string $name Display name.
     * @return string Up to 2 uppercase initials (e.g., "JD" for "John Doe").
     */
    function eprocurement_get_initials( string $name ): string {
        $name = trim( $name );
        if ( $name === '' ) {
            return '?';
        }

        // Handle "Last, First" format.
        if ( strpos( $name, ',' ) !== false ) {
            $parts = explode( ',', $name, 2 );
            $name = trim( $parts[1] . ' ' . $parts[0] );
        }

        $parts = preg_split( '/[\s\-_]+/', $name );
        $parts = array_filter( array_map( 'trim', $parts ) );

        if ( empty( $parts ) ) {
            return strtoupper( mb_substr( $name, 0, 1 ) );
        }

        if ( count( $parts ) === 1 ) {
            return strtoupper( mb_substr( $parts[0], 0, 2 ) );
        }

        $first = mb_substr( $parts[0], 0, 1 );
        $last  = mb_substr( end( $parts ), 0, 1 );
        return strtoupper( $first . $last );
    }
}

if ( ! function_exists( 'eprocurement_get_avatar_color' ) ) {
    /**
     * Pick a deterministic colour for a name.
     *
     * Returns an HSL string. The colour is stable per name so users
     * always get the same colour — useful for visual recognition.
     *
     * @since 2.14.0
     * @param string $seed Seed string (name or email).
     * @return string CSS colour (hsl).
     */
    function eprocurement_get_avatar_color( string $seed ): string {
        // Curated palette of premium-feeling colours — full-saturation,
        // mid-lightness for good contrast against white initials.
        $palette = [
            'hsl(4, 71%, 52%)',     // Red — maroon-ish
            'hsl(24, 80%, 52%)',    // Orange
            'hsl(42, 86%, 48%)',    // Amber
            'hsl(152, 56%, 40%)',   // Emerald
            'hsl(174, 62%, 38%)',   // Teal
            'hsl(199, 70%, 46%)',   // Sky blue
            'hsl(217, 76%, 52%)',   // Blue
            'hsl(243, 60%, 56%)',   // Indigo
            'hsl(262, 60%, 56%)',   // Violet
            'hsl(289, 56%, 52%)',   // Magenta
            'hsl(330, 65%, 52%)',   // Pink
            'hsl(70, 60%, 42%)',    // Lime
        ];

        // Stable hash — same seed always yields same colour.
        $hash = 0;
        for ( $i = 0; $i < strlen( $seed ); $i++ ) {
            $hash = ( $hash * 31 + ord( $seed[ $i ] ) ) & 0x7fffffff;
        }

        return $palette[ $hash % count( $palette ) ];
    }
}

if ( ! function_exists( 'eprocurement_file_icon' ) ) {
    /**
     * Render a file-type icon for a given filename or extension.
     *
     * Returns an inline-SVG badge with the file extension as a label,
     * coloured per file type (red for PDF, blue for DOC, green for XLS, etc.).
     *
     * @since 2.14.0
     * @param string $filename File name or extension.
     * @return string HTML markup.
     */
    function eprocurement_file_icon( string $filename ): string {
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

        $types = [
            'pdf'  => [ 'label' => 'PDF', 'color' => '#dc2626', 'bg' => '#fef2f2' ],
            'doc'  => [ 'label' => 'DOC', 'color' => '#2563eb', 'bg' => '#eff6ff' ],
            'docx' => [ 'label' => 'DOC', 'color' => '#2563eb', 'bg' => '#eff6ff' ],
            'xls'  => [ 'label' => 'XLS', 'color' => '#16a34a', 'bg' => '#f0fdf4' ],
            'xlsx' => [ 'label' => 'XLS', 'color' => '#16a34a', 'bg' => '#f0fdf4' ],
            'csv'  => [ 'label' => 'CSV', 'color' => '#16a34a', 'bg' => '#f0fdf4' ],
            'jpg'  => [ 'label' => 'IMG', 'color' => '#7c3aed', 'bg' => '#f5f3ff' ],
            'jpeg' => [ 'label' => 'IMG', 'color' => '#7c3aed', 'bg' => '#f5f3ff' ],
            'png'  => [ 'label' => 'IMG', 'color' => '#7c3aed', 'bg' => '#f5f3ff' ],
            'gif'  => [ 'label' => 'IMG', 'color' => '#7c3aed', 'bg' => '#f5f3ff' ],
            'zip'  => [ 'label' => 'ZIP', 'color' => '#a16207', 'bg' => '#fefce8' ],
            'txt'  => [ 'label' => 'TXT', 'color' => '#6b7280', 'bg' => '#f9fafb' ],
        ];

        $info = $types[ $ext ] ?? [ 'label' => strtoupper( $ext ?: 'FILE' ), 'color' => '#6b7280', 'bg' => '#f9fafb' ];

        return sprintf(
            '<span class="eproc-file-icon" style="color:%s;background:%s" title="%s"><span class="eproc-file-icon-label">%s</span></span>',
            esc_attr( $info['color'] ),
            esc_attr( $info['bg'] ),
            esc_attr( strtoupper( $ext ?: 'FILE' ) ),
            esc_html( $info['label'] )
        );
    }
}

if ( ! function_exists( 'eprocurement_breadcrumbs' ) ) {
    /**
     * Render a breadcrumb trail.
     *
     * @since 2.14.0
     * @param array $items [{label, url}, ...] — last item is current page (no link).
     * @return string HTML markup.
     */
    function eprocurement_breadcrumbs( array $items ): string {
        if ( empty( $items ) ) {
            return '';
        }

        $output = '<nav class="eproc-breadcrumbs" aria-label="Breadcrumb"><ol>';
        $count = count( $items );
        $i = 0;

        foreach ( $items as $item ) {
            $i++;
            $is_last = ( $i === $count );
            $label = esc_html( $item['label'] ?? '' );

            if ( $is_last || empty( $item['url'] ) ) {
                $output .= "<li class=\"eproc-breadcrumb-current\" aria-current=\"page\">{$label}</li>";
            } else {
                $url = esc_url( $item['url'] );
                $output .= "<li><a href=\"{$url}\">{$label}</a></li>";
            }

            if ( ! $is_last ) {
                $output .= '<li class="eproc-breadcrumb-separator" aria-hidden="true">›</li>';
            }
        }

        $output .= '</ol></nav>';
        return $output;
    }
}

if ( ! function_exists( 'eprocurement_status_badge' ) ) {
    /**
     * Render a status badge with an icon.
     *
     * @since 2.14.0
     * @param string $status Status slug (draft, open, closed, cancelled, archived, etc.).
     * @param string $label  Optional custom label (defaults to ucwords(status)).
     * @return string HTML.
     */
    function eprocurement_status_badge( string $status, string $label = '' ): string {
        $status = sanitize_key( $status );
        $label = $label ?: ucwords( $status );

        $icons = [
            'draft'     => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2-2a2 2 0 00-2 2v10a2 2 0 002 2h6a2 2 0 002-2V4a2 2 0 00-2-2H6z"/><path d="M14 6h2a2 2 0 012 2v8a2 2 0 01-2 2H8a2 2 0 01-2-2v-2"/></svg>',
            'open'      => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm3.707 7.293a1 1 0 00-1.414-1.414L9 11.172 7.707 9.879a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>',
            'closed'    => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm3.707 6.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>',
            'cancelled' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm3.536 4.464a1 1 0 00-1.414 0L10 8.586 7.879 6.464a1 1 0 10-1.414 1.414L8.586 10l-2.121 2.121a1 1 0 101.414 1.414L10 11.414l2.121 2.121a1 1 0 001.414-1.414L11.414 10l2.121-2.121a1 1 0 000-1.415z"/></svg>',
            'archived'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 3a1 1 0 000 2h14a1 1 0 100-2H3zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm1 5a1 1 0 100 2h12a1 1 0 100-2H4z"/></svg>',
            'verified'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>',
            'unverified'=> '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 110-12 6 6 0 010 12zm0-9a1 1 0 011 1v3a1 1 0 11-2 0V8a1 1 0 011-1zm0 7a1 1 0 100-2 1 1 0 000 2z"/></svg>',
            'public'    => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 110-12 6 6 0 010 12zm-1-9a1 1 0 112 0v4a1 1 0 11-2 0V7zm1 7a1 1 0 100-2 1 1 0 000 2z"/></svg>',
            'private'   => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M5 9V7a5 5 0 0110 0v2h1a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2v-6a2 2 0 012-2h1zm2 0h6V7a3 3 0 00-6 0v2z"/></svg>',
        ];

        $icon = $icons[ $status ] ?? '';

        return sprintf(
            '<span class="eproc-badge eproc-badge-%s"><span class="eproc-badge-icon">%s</span><span class="eproc-badge-text">%s</span></span>',
            esc_attr( $status ),
            $icon,
            esc_html( $label )
        );
    }
}

if ( ! function_exists( 'eprocurement_relative_time' ) ) {
    /**
     * Format a datetime as a relative time ("2 hours ago", "just now", etc.).
     *
     * @since 2.14.0
     * @param string|null $datetime MySQL datetime.
     * @return string Relative time string.
     */
    function eprocurement_relative_time( ?string $datetime ): string {
        if ( ! $datetime || $datetime === '0000-00-00 00:00:00' ) {
            return '';
        }

        $timestamp = strtotime( $datetime );
        if ( ! $timestamp ) {
            return '';
        }

        $now  = current_time( 'timestamp' );
        $diff = $now - $timestamp;

        if ( $diff < 60 ) {
            return __( 'just now', 'eprocurement' );
        }

        if ( $diff < 3600 ) {
            $mins = (int) ( $diff / 60 );
            /* translators: %d: minutes */
            return sprintf( _n( '%d min ago', '%d mins ago', $mins, 'eprocurement' ), $mins );
        }

        if ( $diff < 86400 ) {
            $hours = (int) ( $diff / 3600 );
            /* translators: %d: hours */
            return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'eprocurement' ), $hours );
        }

        if ( $diff < 604800 ) {
            $days = (int) ( $diff / 86400 );
            /* translators: %d: days */
            return sprintf( _n( '%d day ago', '%d days ago', $days, 'eprocurement' ), $days );
        }

        if ( $diff < 2592000 ) {
            $weeks = (int) ( $diff / 604800 );
            /* translators: %d: weeks */
            return sprintf( _n( '%d week ago', '%d weeks ago', $weeks, 'eprocurement' ), $weeks );
        }

        // Beyond 30 days — show the actual date.
        return wp_date( 'j M Y', $timestamp );
    }
}
